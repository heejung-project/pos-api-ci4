<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\TableModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TableController extends ResourceController
{

    protected $format = 'json';
    protected $adminModel;
    protected $tableModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->tableModel = new TableModel();
    }

    public function index(){

    }

    //테이블 리스트 조회
    public function list(){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $authHeader = $this->request->getHeaderLine('Authorization');  

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
            // admin 정보 확인
            $admin = $this->adminModel
                ->where('serial', $decoded->admin_serial)
                ->where('is_active', 'Y')
                ->first();
            if(!$admin){
                return $this->failUnauthorized('접근 권한이 없습니다.');
            }
            $store_serial = $admin['store_serial'];

            log_message('info', $store_serial);
            //테이블 조회
            $table_list = $this->tableModel
                ->where('store_serial',$store_serial)
                ->where('is_active','Y')
                ->findAll();
            if($table_list === false){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '테이블 조회 성공',
                'data' => $table_list
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //특정 테이블 조회
    public function info($id = null){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $authHeader = $this->request->getHeaderLine('Authorization');  

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
            // admin 정보 확인
            $admin = $this->adminModel
                ->where('serial', $decoded->admin_serial)
                ->where('is_active', 'Y')
                ->first();
            if(!$admin){
                return $this->failUnauthorized('토큰이 유효하지 않습니다.');
            }

            //테이블 조회
            $table = $this->tableModel
                ->where('serial',$id)
                ->find();
            if(!$table){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '테이블 조회 성공',
                'data' => $table
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //테이블 생성
    public function create(){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $authHeader = $this->request->getHeaderLine('Authorization');  

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
            // admin 정보 확인
            $admin = $this->adminModel
                ->where('serial', $decoded->admin_serial)
                ->where('is_active', 'Y')
                ->first();
            if(!$admin){
                return $this->failUnauthorized('토큰이 유효하지 않습니다.');
            }
            $store_serial = $admin['store_serial'];

            $table_number = $this->request->getVar('table_number');
            $table_name = $this->request->getVar('table_name');

            if(empty($table_number)){
                return $this->failUnauthorized('테이블 번호를 입력해 주세요.');
            }
            if(empty($table_name)){
                $table_name = $table_number;
            }

            //테이블 번호 중복체크
            $exists = $this->tableModel
                ->where('store_serial', $store_serial)
                ->where('table_number', $table_number)
                ->first();

            if ($exists) {
                return $this->fail('같은 번호의 테이블이 이미 존재합니다.', 409);
            }

            //테이블 정보 저장
            $result = $this->tableModel->insert([
                'store_serial' => $store_serial,
                'table_number' => $table_number,
                'table_name' => $table_name
            ]);
            if(!$result){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 생성 실패');
            }

            return $this->respondCreated([
                'code' => 'S01',
                'message' => '테이블 생성 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //테이블 정보 수정
    public function update($id = null){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $authHeader = $this->request->getHeaderLine('Authorization');  

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
            // admin 정보 확인
            $admin = $this->adminModel
                ->where('serial', $decoded->admin_serial)
                ->where('is_active', 'Y')
                ->first();
            if(!$admin){
                return $this->failUnauthorized('토큰이 유효하지 않습니다.');
            }

            if(empty($id)){
                return $this->failUnauthorized('입력값이 없습니다.');
            }

            $data = $this->request->getRawInput();
            $table_name = $data['table_name'];

            //테이블 번호 중복체크
            $exists = $this->tableModel
                ->where('store_serial', $store_serial)
                ->where('table_number', $table_number)
                ->where('serial !=', $id)
                ->first();

            if ($exists) {
                return $this->fail('같은 번호의 테이블이 이미 존재합니다.', 409);
            }

            //테이블 정보 수정
            $result = $this->tableModel->update($id,[
                'table_name' => $table_name
            ]);
            if(!$result){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 수정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '테이블 수정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //테이블 삭제
    public function delete($id = null){
        try{
            log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());

            $authHeader = $this->request->getHeaderLine('Authorization');  

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
            // admin 정보 확인
            $admin = $this->adminModel
                ->where('serial', $decoded->admin_serial)
                ->where('is_active', 'Y')
                ->first();
            if(!$admin){
                return $this->failUnauthorized('토큰이 유효하지 않습니다.');
            }

            //테이블 삭제
            $result = $this->tableModel->delete($id);
            if(!$result){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 삭제 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '테이블 삭제 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}