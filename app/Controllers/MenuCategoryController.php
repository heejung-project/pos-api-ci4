<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\MenuCategoryModel;
use App\models\MenuModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MenuCategoryController extends ResourceController
{

    protected $format = 'json';
    protected $adminModel;
    protected $menuCategoryModel;
    protected $menuModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->menuCategoryModel = new MenuCategoryModel();
        $this->menuModel = new MenuModel();
    }

    //메뉴 카테고리 리스트 조회
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

            // 조회
            $category_list = $this->menuCategoryModel
                ->where('store_serial',$store_serial)
                ->where('is_active','Y')
                ->findAll();
            if($category_list === false){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 조회 성공',
                'data' => $category_list
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //특정 메뉴 카테고리 조회
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

            //카테고리 조회
            $category = $this->menuCategoryModel
                ->where('serial',$id)
                ->find();
            if(!$category){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 조회 성공',
                'data' => $category
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 카테고리 생성
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

            $category_name = $this->request->getVar('category_name');
            $is_displayed = $this->request->getVar('is_displayed');

            if(empty($category_name)){
                return $this->failUnauthorized('카테고리 명을 입력해 주세요.');
            }
            if(empty($is_displayed)){
                $is_displayed = 'Y';
            }

            //카테고리 명 중복체크
            $exists = $this->menuCategoryModel
                ->where('store_serial', $store_serial)
                ->where('name', $category_name)
                ->first();

            if ($exists) {
                return $this->fail('같은 이름의 카테고리가 이미 존재합니다.', 409);
            }

            //순서
            $last_index = $this->menuCategoryModel
                ->where('store_serial',$store_serial)
                ->orderBy('sort_order', 'DESC')
                ->first();
            if($last_index === false){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 생성 실패');
            }
            if(empty($last_index)){
                $sort_order = 1;
            }else{
                $sort_order = $last_index['sort_order'] + 1;
            }

            //카테고리 정보 저장
            $result = $this->menuCategoryModel->insert([
                'store_serial' => $store_serial,
                'name' => $category_name,
                'is_displayed' => $is_displayed,
                'sort_order' => $sort_order
            ]);
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 생성 실패');
            }

            return $this->respondCreated([
                'code' => 'S01',
                'message' => '메뉴 카테고리 생성 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 카테고리 정보 수정
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
            $store_serial = $admin['store_serial'];

            if(empty($id)){
                return $this->failUnauthorized('입력값이 없습니다.');
            }

            $data = $this->request->getJson();
            $category_name = $data->category_name;
            $is_displayed = $data->is_displayed;

            if(empty($category_name)){
                return $this->failUnauthorized('카테고리 명을 입력해 주세요.');
            }
            if(empty($is_displayed)){
                $is_displayed = 'Y';
            }

            //카테고리 명 중복체크
            $exists = $this->menuCategoryModel
                ->where('store_serial', $store_serial)
                ->where('name', $category_name)
                ->where('serial !=', $id)
                ->first();

            if ($exists) {
                return $this->fail('같은 이름의 카테고리가 이미 존재합니다.', 409);
            }

            //카테고리 정보 수정
            $result = $this->menuCategoryModel->update($id,[
                'name' => $category_name,
                'is_displayed' => $is_displayed
            ]);
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 수정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 수정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 카테고리 삭제
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

            $this->db->transStart();

            //카테고리 삭제
            $result = $this->menuCategoryModel->delete($id);
            if(!$result){
                $dbError = $this->MenuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 삭제 실패');
            }

            //메뉴 삭제
            $result = $this->menuModel
                ->where('category_serial', $category_serial)
                ->delete();
            if(!$result){
                $dbError = $this->menuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 삭제 실패');
            }

            $this->db->transComplete();

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 삭제 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 카테고리 순서 올리기
    public function orderUp($id = null){
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

            //해당 카테고리 조회
            $current = $this->menuCategoryModel
                ->select('sort_order')
                ->where('serial',$id)
                ->first();
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 카테고리를 찾을 수 없습니다.');
            }
            if($current['sort_order'] == 1){
                throw new Exception('더 이상 순서를 올릴 수 없습니다.');
            }

            //해당 카테고리 바로 앞 순서의 카테고리 조회
            $previous = $this->menuCategoryModel
                ->where('store_serial', $id)
                ->where('sort_order <', $current['sort_order'])
                ->orderBy('sort_order', 'DESC')
                ->first();
            if($previous === false){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 카테고리를 찾을 수 없습니다.');
            }
            if(!empty($previous)){//전 순서의 카테고리 순서 +1
                $result = $this->menuCategoryModel->update($previous['serial'], [
                    'sort_order' => $previous['sort_order'] + 1
                ]);
                if(!$result){
                    $dbError = $this->menuCategoryModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 카테고리 순서 조정 실패');
                }
            }
            //해당 카테고리 순서 -1
            $result = $this->menuCategoryModel->update($id,[
                'sort_order' => $current['sort_order'] - 1
            ]);
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 순서 조정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 순서 조정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 카테고리 순서 내리기
    public function orderDown($id = null){
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

            //해당 카테고리 조회
            $current = $this->menuCategoryModel
                ->select('sort_order')
                ->where('serial',$id)
                ->first();
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 카테고리를 찾을 수 없습니다.');
            }

            //해당 카테고리 바로 뒷 순서의 카테고리 조회
            $next = $this->menuCategoryModel
                ->where('store_serial', $id)
                ->where('sort_order >', $current['sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->first();
            if($next === false){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 카테고리를 찾을 수 없습니다.');
            }
            if(empty($next)){
                throw new Exception('더 이상 순서를 내릴 수 없습니다.');
            }else{//뒷 순서의 카테고리 순서 -1
                $result = $this->menuCategoryModel->update($next['serial'], [
                    'sort_order' => $next['sort_order'] - 1
                ]);
                if(!$result){
                    $dbError = $this->menuCategoryModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 카테고리 순서 조정 실패');
                }
            }
            //해당 카테고리 순서 +1
            $result = $this->menuCategoryModel->update($id,[
                'sort_order' => $current['sort_order'] + 1
            ]);
            if(!$result){
                $dbError = $this->menuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 순서 조정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 카테고리 순서 조정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}