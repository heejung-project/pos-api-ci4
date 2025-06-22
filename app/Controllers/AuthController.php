<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\StoreModel;
use App\models\RefreshTokenModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends ResourceController
{

    protected $format = 'json';
    protected $key;
    protected $adminModel;
    protected $storeModel;
    protected $refreshTokenModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->storeModel = new StoreModel();
        $this->refreshTokenModel = new RefreshTokenModel();

        $this->key = getenv('JWT_SECRET_KEY');
    }

    public function index()
    {
        return view('templates/header')
        . view('pages/register_admin')
        . view('templates/footer');
    }

    public function view($page)
    {
        if (! is_file(APPPATH . 'Views/pages/' . $page . '.php')) {
            // Whoops, we don't have a page for that!
            throw new \CodeIgniter\Exceptions\PageNotFoundException($page);
        }

        return view('templates/header')
            . view('pages/' . $page)
            . view('templates/footer');
    }

    public function registerAdmin(){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
            $rules = [
                'admin_name' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => '관리자 이름을 입력해주세요.',
                    ]
                ],
                'id'    => [
                    'rules' => 'required|valid_email|is_unique[admin.id]',
                    'errors' => [
                        'required' => '아이디를 입력해주세요.',
                        'valid_email' => '아이디를 이메일 형식으로 입력해 주세요.',
                        'is_unique' => '사용할 수 없는 아이디입니다.'
                    ]
                ],
                'password' => [
                    'rules' => 'required|min_length[8]',
                    'errors' => [
                        'required' => '비밀번호를 입력해주세요.',
                        'min_length' => '비밀번호는 8자리 이상이어야 합니다.'
                    ]
                ],
                'store_name' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => '상호명을 입력해주세요.'
                    ]
                ],
                'address' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => '주소를 입력해주세요.'
                    ]
                ],
                'address_detail' => [
                    'rules' => 'required',
                    'errors' => [
                        'required' => '상세 주소를 입력해주세요.'
                    ]
                ],
                'phone' => [
                    'rules' => 'required|regex_match[/^01[0|1|6|7|8|9]-?\d{3,4}-?\d{4}$/]',
                    'errors' => [
                        'required' => '전화번호를 입력해주세요.',
                        'regex_match' => '유효한 전화번호 형식이 아닙니다.'
                    ]
                ]
            ];
    
            if (!$this->validate($rules)) {
                $errors = $this->validator->getErrors();
                $firstError = reset($errors); // 배열의 첫 번째 값만 꺼냄
                return $this->failValidationErrors($firstError);
            }
    
            $this->db->transStart();

            $store_data = [
                'name' => $this->request->getVar('store_name'),
                'address'    => $this->request->getVar('address'),
                'address_detail' => $this->request->getVar('address_detail'),
            ];
            $insertId = $this->storeModel->insert($store_data);
            if(!$insertId){
                $dbError = $this->storeModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }

                throw new Exception('회원가입에 실패했습니다.');
            }

            $admin_data = [
                'store_serial' => $insertId,
                'name' => $this->request->getVar('admin_name'),
                'id' => $this->request->getVar('id'),
                'pw' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT),
                'phone'    => $this->request->getVar('phone')
            ];
            $result = $this->adminModel->insert($admin_data);
            if(!$result){
                $dbError = $this->adminModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }

                throw new Exception('회원가입에 실패했습니다.');
            }

            $this->db->transComplete();
    
            return $this->respondCreated([
                'code' => 'S01',
                'message' => '회원가입 성공'
            ]);
        }catch(Exception $e){
            log_message('error', $e->getMessage());
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function login(){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
            $rules = [
                'id' => 'required',
                'password' => 'required'
            ];

            if (!$this->validate($rules)) {
                return $this->failValidationErrors([
                    'message' => '아이디 또는 비밀번호를 입력해주세요.'
                ]);
            }

            $id = $this->request->getVar('id');
            $pw = $this->request->getVar('password');

            $admin = $this->adminModel->where('id', $id)->first();

            if (!$admin || !password_verify($pw, $admin['pw'])) {
                return $this->failUnauthorized('아이디 또는 비밀번호가 일치하지 않습니다.');
            }
            if($admin['is_active'] !== 'Y'){
                return $this->failForbidden('사용할 수 없는 계정입니다.');
            }

            $store = $this->storeModel->where('serial', $admin['store_serial'])->first();

            if(!$store || $store['is_active'] !== 'Y'){
                return $this->failForbidden('사용할 수 없는 계정입니다.');
            }

            //jwt
            $accessPayload = [
                'iat' => time(),
                'exp' => time() + 60 * 60, // 1시간
                'admin_serial' => $admin['serial']
            ];

            $refreshPayload = [
                'iat' => time(),
                'exp' => time() + 60 * 60 * 24 * 14, // 14일
                'admin_serial' => $admin['serial']
            ];

            $accessToken = JWT::encode($accessPayload, $this->key, 'HS256');
            $refreshToken = JWT::encode($refreshPayload, $this->key, 'HS256');

            //refreshToken 저장
            $result = $this->refreshTokenModel->insert([
                'admin_serial' => $admin['serial'],
                'refresh_token' => $refreshToken,
                'expire_date' => date('Y-m-d H:i:s', $refreshPayload['exp'])
            ]);
            if(!$result){
                $dbError = $this->refreshTokenModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }

                throw new Exception('로그인 중 문제가 생겼습니다.');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '로그인 성공',
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken
            ]);
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function logout(){
        try{
            $authHeader = $this->request->getHeaderLine('Authorization'); // Bearer xxxx
    
            if ($authHeader) {
                $token = str_replace('Bearer ', '', $authHeader);
                try {
                    $decoded = JWT::decode($token, new Key(env('JWT_SECRET_KEY'), 'HS256'));
                } catch (\Exception $e) {
                    return $this->failUnauthorized('토큰이 유효하지 않습니다.');
                }
            }else{
                return $this->failUnauthorized('토큰이 유효하지 않습니다.');
            }
    
            $deleted = $this->refreshTokenModel
                ->where('admin_serial', $decoded->admin_serial)
                ->delete();
    
            if($deleted) {
                return $this->respond([
                    'code' => 'S01',
                    'message' => '로그아웃 되었습니다.'
                ]);
            }else{
                return $this->failServerError('로그아웃 실패');
            }
    
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //token 재발급
    public function refreshToken(){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $refreshToken = $this->request->getVar('refresh_token');
            if (empty($refreshToken)) {
                return $this->failUnauthorized('refresh token is null');
            }

            $decoded = JWT::decode($refreshToken, new Key(env('JWT_SECRET_KEY'), 'HS256'));
            $admin_serial = $decoded->admin_serial;
            if (empty($admin_serial)) {
                return $this->failUnauthorized('토큰 인증 실패.');
            }

            $row = $this->refreshTokenModel
            ->where('admin_serial',$admin_serial)
            ->where('refresh_token',$refreshToken)
            ->first();
            if(!$row){//일치하는 refresh token이 없을 경우 - 재로그인
                return $this->failUnauthorized('토큰 인증 실패. 다시 로그인 해주세요.');
            }
            if($row['expire_date'] < date('Y-m-d H:i:s')){//refresh_token이 만료된 경우 - access token, refresh token 재발급
                
                $accessPayload = [
                    'iat' => time(),
                    'exp' => time() + 60 * 60, // 1시간
                    'admin_serial' => $row['admin_serial']
                ];
                
                $refreshPayload = [
                    'iat' => time(),
                    'exp' => time() + 60 * 60 * 24 * 14, // 14일
                    'admin_serial' => $row['admin_serial']
                ];
                
                $accessToken = JWT::encode($accessPayload, $this->key, 'HS256');
                $refreshToken = JWT::encode($refreshPayload, $this->key, 'HS256');
                
                //refreshToken update
                $result = $this->refreshTokenModel->update($row['serial'],[
                    'refresh_token' => $refreshToken,
                    'expire_date' => date('Y-m-d H:i:s', $refreshPayload['exp'])
                ]);
                if(!$result){
                    $dbError = $this->refreshTokenModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
    
                    throw new Exception('서버에 오류가 생겼습니다.');
                }

            }else{//유효한 refresh token일 경우 - access token 재발급

                $accessPayload = [
                    'iat' => time(),
                    'exp' => time() + 60 * 60, // 1시간
                    'admin_serial' => $row['admin_serial']
                ];

                $accessToken = JWT::encode($accessPayload, $this->key, 'HS256');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '토큰 재발급 성공',
                'access_token' => $accessToken
            ]);
            
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}
