<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\MenuModel;
use App\models\MenuCategoryModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class MenuController extends ResourceController
{

    protected $format = 'json';
    protected $adminModel;
    protected $MenuModel;
    protected $MenuCategoryModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->MenuModel = new MenuModel();
        $this->MenuCategoryModel = new MenuCategoryModel();
    }

    //메뉴 리스트 조회
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
            $menu_list = $this->MenuModel
                ->where('store_serial',$store_serial)
                ->where('is_active','Y')
                ->findAll();
            if($menu_list === false){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 조회 성공',
                'data' => $menu_list
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //특정 메뉴 조회
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

            //메뉴 조회
            $menu = $this->MenuModel
                ->where('serial',$id)
                ->find();
            if(!$menu){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 조회 성공',
                'data' => $menu
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 생성
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

            $category_serial = $this->request->getVar('category_serial');
            $menu_name = $this->request->getVar('menu_name');
            $is_displayed = $this->request->getVar('is_displayed');
            $price = $this->request->getVar('price');
            $desc = $this->request->getVar('desc');
            $soldout = $this->request->getVar('soldout');
            $imageFile = $this->request->getFile('image');

            if(empty($category_serial)){
                return $this->failUnauthorized('카테고리를 선택해 주세요.');
            }
            if(empty($menu_name)){
                return $this->failUnauthorized('메뉴 명을 입력해 주세요.');
            }
            if(empty($is_displayed)){
                $is_displayed = 'Y';
            }
            if(empty($price)){
                return $this->failUnauthorized('가격을 입력해 주세요.');
            }
            if(empty($soldout)){
                $soldout = 'N';
            }

            //메뉴 명 중복체크
            $exists = $this->MenuModel
                ->where('store_serial', $store_serial)
                ->where('name', $menu_name)
                ->first();

            if ($exists) {
                return $this->fail('같은 이름의 메뉴가 이미 존재합니다.', 409);
            }

            //순서
            $last_index = $this->MenuModel
                ->where('store_serial',$store_serial)
                ->where('category_serial',$category_serial)
                ->orderBy('sort_order', 'DESC')
                ->first();
            if($last_index === false){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 생성 실패');
            }
            if(empty($last_index)){
                $sort_order = 1;
            }else{
                $sort_order = $last_index['sort_order'] + 1;
            }

            //메뉴 정보 저장
            if ($imageFile && $imageFile->isValid() && !$imageFile->hasMoved()) {

                $uploadPath = WRITEPATH . 'uploads/menu_image/';
            
                // 파일 저장
                $newName = $imageFile->getRandomName();
                $imageFile->move($uploadPath, $newName);
                $imagePath = 'uploads/menu_image/' . $newName;

                $result = $this->MenuModel->insert([
                    'store_serial' => $store_serial,
                    'category_serial' => $category_serial,
                    'name' => $menu_name,
                    'price' => $price,
                    'desc' => $desc,
                    'is_displayed' => $is_displayed,
                    'soldout' => $soldout,
                    'sort_order' => $sort_order,
                    'image' => $imagePath
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 생성 실패');
                }
            }else{
                $result = $this->MenuModel->insert([
                    'store_serial' => $store_serial,
                    'category_serial' => $category_serial,
                    'name' => $menu_name,
                    'price' => $price,
                    'desc' => $desc,
                    'is_displayed' => $is_displayed,
                    'soldout' => $soldout,
                    'sort_order' => $sort_order
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 생성 실패');
                }
            }

            return $this->respondCreated([
                'code' => 'S01',
                'message' => '메뉴 생성 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 정보 수정
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

            $category_serial = $this->request->getVar('category_serial');
            $menu_name = $this->request->getVar('menu_name');
            $is_displayed = $this->request->getVar('is_displayed');
            $price = $this->request->getVar('price');
            $desc = $this->request->getVar('desc');
            $soldout = $this->request->getVar('soldout');
            $imageFile = $this->request->getFile('image');

            if(empty($menu_name)){
                return $this->failUnauthorized('메뉴 명을 입력해 주세요.');
            }
            if(empty($is_displayed)){
                $is_displayed = 'Y';
            }

            //메뉴 명 중복체크
            $exists = $this->MenuModel
                ->where('store_serial', $store_serial)
                ->where('name', $menu_name)
                ->where('serial !=', $id)
                ->first();

            if ($exists) {
                return $this->fail('같은 이름의 메뉴가 이미 존재합니다.', 409);
            }

            //카테고리 변경 확인
            $existsCate = $this->MenuModel
                ->select('category_serial')
                ->where('serial', $id)
                ->first();

            $this->db->transStart();

            //카테고리 수정 된거면 순서 새로 구하기
            if($existsCate['category_serial'] != $category_serial){
                $last_index = $this->MenuModel
                    ->where('store_serial',$store_serial)
                    ->where('category_serial',$category_serial)
                    ->orderBy('sort_order', 'DESC')
                    ->first();
                if($last_index === false){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 생성 실패');
                }
                if(empty($last_index)){
                    $sort_order = 1;
                }else{
                    $sort_order = $last_index['sort_order'] + 1;
                }

                //메뉴 정보 수정
                $result = $this->MenuModel->update($id,[
                    'category_serial' => $category_serial,
                    'name' => $menu_name,
                    'price' => $price,
                    'desc' => $desc,
                    'is_displayed' => $is_displayed,
                    'soldout' => $soldout,
                    'sort_order' => $sort_order
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 수정 실패');
                }
            }else{
                //메뉴 정보 수정
                $result = $this->MenuModel->update($id,[
                    'category_serial' => $category_serial,
                    'name' => $menu_name,
                    'price' => $price,
                    'desc' => $desc,
                    'is_displayed' => $is_displayed,
                    'soldout' => $soldout
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 수정 실패');
                }
            }

            if ($imageFile && $imageFile->isValid() && !$imageFile->hasMoved()) {//이미지 파일 포함

                $uploadPath = WRITEPATH . 'uploads/menu_image/';

                // 기존 이미지 파일 삭제
                $existsImg = $this->MenuModel
                ->select('image')
                ->where('serial', $id)
                ->first();
                if (!empty($existsImg['image']) && file_exists($uploadPath . $existsImg['image'])) {
                    unlink($uploadPath . $imagePath);
                }
            
                // 파일 저장
                $newName = $imageFile->getRandomName();
                $imageFile->move($uploadPath, $newName);
                $imagePath = 'uploads/menu_image/' . $newName;

                //메뉴 정보 수정
                $result = $this->MenuModel->update($id,[
                    'image' => $imagePath
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 수정 실패');
                }
            }

            $this->db->transComplete();

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 수정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 삭제
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

            //메뉴 삭제
            $result = $this->MenuModel->delete($id);
            if(!$result){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 삭제 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 삭제 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 순서 올리기
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
            $store_serial = $admin['store_serial'];

            if(empty($id)){
                return $this->failUnauthorized('입력값이 없습니다.');
            }

            //해당 메뉴 조회
            $current = $this->MenuModel
                ->select('sort_order, category_serial')
                ->where('serial',$id)
                ->first();
            if(!$current){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 메뉴를 찾을 수 없습니다.');
            }

            if($current['sort_order'] == 1){
                throw new Exception('더 이상 순서를 올릴 수 없습니다.');
            }

            //해당 메뉴 바로 앞 순서의 메뉴 조회
            $previous = $this->MenuModel
                ->where('store_serial', $store_serial)
                ->where('category_serial', $current['category_serial'])
                ->where('sort_order <', $current['sort_order'])
                ->orderBy('sort_order', 'DESC')
                ->first();
            if($previous === false){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 메뉴를 찾을 수 없습니다.');
            }
            if(!empty($previous)){//전 순서의 메뉴 순서 +1
                $result = $this->MenuModel->update($previous['serial'], [
                    'sort_order' => $previous['sort_order'] + 1
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 메뉴 순서 조정 실패');
                }
            }
            //해당 메뉴 순서 -1
            $result = $this->MenuModel->update($id,[
                'sort_order' => $current['sort_order'] - 1
            ]);
            if(!$result){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 메뉴 순서 조정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 메뉴 순서 조정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 순서 내리기
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
            $store_serial = $admin['store_serial'];

            if(empty($id)){
                return $this->failUnauthorized('입력값이 없습니다.');
            }

            //해당 메뉴 조회
            $current = $this->MenuModel
                ->select('sort_order, category_serial')
                ->where('serial',$id)
                ->first();
            if(!$current){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 메뉴를 찾을 수 없습니다.');
            }
            

            //해당 메뉴 바로 뒷 순서의 메뉴 조회
            $next = $this->MenuModel
                ->where('store_serial', $store_serial)
                ->where('category_serial', $current['category_serial'])
                ->where('sort_order >', $current['sort_order'])
                ->orderBy('sort_order', 'ASC')
                ->first();
            if($next === false){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 메뉴를 찾을 수 없습니다.');
            }
            if(empty($next)){
                throw new Exception('더 이상 순서를 내릴 수 없습니다.');
            }else{//뒷 순서의 메뉴 순서 -1
                $result = $this->MenuModel->update($next['serial'], [
                    'sort_order' => $next['sort_order'] - 1
                ]);
                if(!$result){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 메뉴 순서 조정 실패');
                }
            }
            //해당 메뉴 순서 +1
            $result = $this->MenuModel->update($id,[
                'sort_order' => $current['sort_order'] + 1
            ]);
            if(!$result){
                $dbError = $this->MenuModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 메뉴 순서 조정 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 메뉴 순서 조정 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //주문용 메뉴 리스트
    public function menusForOrder(){
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

            $data = array();

            // 카테고리 조회
            $category_list = $this->MenuCategoryModel
                ->where('store_serial',$store_serial)
                ->where('is_active','Y')
                ->where('is_displayed','Y')
                ->findAll();
            if($category_list === false){
                $dbError = $this->MenuCategoryModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('메뉴 카테고리 조회 실패');
            }

            if(!empty($category_list)){
                // 메뉴 조회
                $menu_list = $this->MenuModel
                    ->where('store_serial',$store_serial)
                    ->where('is_active','Y')
                    ->where('is_displayed','Y')
                    ->findAll();
                if($menu_list === false){
                    $dbError = $this->MenuModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('메뉴 조회 실패');
                }
                if(!empty($menu_list)){
                    foreach ($category_list as $category) {
                        $category_data = $category;
                        $category_data['menus'] = [];

                        foreach ($menu_list as $menu) {
                            if ($menu['category_serial'] == $category['serial']) {
                                $category_data['menus'][] = $menu;
                            }
                        }

                        $data[] = $category_data;
                    }
                }
            }


            return $this->respond([
                'code' => 'S01',
                'message' => '메뉴 조회 성공',
                'data' => $data
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}