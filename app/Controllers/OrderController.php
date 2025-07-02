<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\OrderModel;
use App\models\OrderDetailModel;
use App\models\TableModel;
use App\models\MenuModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OrderController extends ResourceController
{

    protected $format = 'json';
    protected $adminModel;
    protected $orderModel;
    protected $orderDetailModel;
    protected $tableModel;
    protected $menuModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->orderModel = new OrderModel();
        $this->orderDetailModel = new OrderDetailModel();
        $this->tableModel = new TableModel();
        $this->menuModel = new MenuModel();
    }

    //주문
    public function order(){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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
    
            $table_number = $this->request->getVar('table_number');
            $table_name = $this->request->getVar('table_name');
            $menusJson = $this->request->getVar('menus');//배열
            $total_price = $this->request->getVar('total_price');
    
            if(empty($table_number)){
                return $this->failUnauthorized('테이블 번호 누락');
            }
            if(empty($table_name)){
                return $this->failUnauthorized('테이블 이름 누락');
            }
            if(empty($menusJson)){
                return $this->failUnauthorized('메뉴를 선택해 주세요.');
            }
            if(empty($total_price)){
                return $this->failUnauthorized('가격 누락');
            }

            //유효한 테이블인지 확인
            $result = $this->tableModel
                ->where('store_serial', $store_serial)
                ->where('table_number', $table_number)
                ->where('table_name', $table_name)
                ->where('is_active', 'Y')
                ->first();
            if(!$result){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('테이블 조회 실패');
            }

            //총 금액 확인
            $menus = json_decode($menusJson, true);
            $check_price = 0;
            foreach($menus as $menu){
                //유효한 메뉴인지 확인
                $result = $this->menuModel
                    ->where('serial', $menu['serial'])
                    ->where('is_active', 'Y')
                    ->first();
                if(!$result){
                    $dbError = $this->menuModel->errors();
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    throw new Exception('메뉴 조회 실패');
                }

                $check_price += $menu['total_price'];
            }
            if($check_price != $total_price){
                throw new Exception('총 금액이 맞지 않습니다.');
            }

            $this->db->transStart();
    
            //주문 등록
            $insertId = $this->orderModel->insert([
                'store_serial' => $store_serial,
                'table_number' => $table_number,
                'table_name' => $table_name,
                'total_price' => $total_price,
                'status' => 'WAIT',
                'registered_date' => date('Y-m-d H:i:s')
            ]);
            if(!$insertId){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 실패');
            }
    
            //주문 상세 등록
            foreach($menus as $menu){
                $result = $this->orderDetailModel->insert([
                    'store_serial' => $store_serial,
                    'order_serial' => $insertId,
                    'menu_serial' => $menu['serial'],
                    'menu_name' => $menu['name'],
                    'price' => $menu['price'],
                    'count' => $menu['count'],
                    'total_price' => $menu['total_price']
                ]);
                if(!$result){
                    $dbError = $this->orderDetailModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('주문 실패');
                }
            }
    
            $this->db->transComplete();

            return $this->respondCreated([
                'code' => 'S01',
                'message' => '주문 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }

    }

    //주문 메뉴 변경
    public function orderMenuModify(){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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

            $order_serial = $this->request->getVar('order_serial');
            $menusJson = $this->request->getVar('menus');
            $total_price = $this->request->getVar('total_price');
            // menus[
            //     { "serial": 1, "name": "아메리카노", "count": 2, "price" : 5000, "total_price" : "10000"},  // 기존 메뉴 (수량 변경)
            //     { "serial": 3, "name": "카라멜라떼", "count": 1, "price" : 5000, "total_price" : "5000" },  // 신규 메뉴
            //     { "serial": 4, "delete": true } // 삭제할 메뉴
            // ]

            if(empty($order_serial) || empty($total_price)){
                return $this->failUnauthorized('주문 정보 누락');
            }
            if(empty($menusJson)){
                return $this->failUnauthorized('메뉴를 선택해 주세요.');
            }

            $this->db->transStart();

            //총 금액 확인, 유효한 메뉴인지 확인
            $menus = json_decode($menusJson, true);
            $check_price = 0;
            foreach($menus as $menu){
                if($menu['delete'] === false){
                    $result = $this->menuModel
                        ->where('serial', $menu['serial'])
                        ->where('is_active', 'Y')
                        ->first();
                    if(!$result){
                        $dbError = $this->menuModel->errors();
                        if(!empty($dbError)){
                            log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                        }
                        throw new Exception('메뉴 조회 실패');
                    }
    
                    $check_price += $menu['total_price'];
                }
            }
            if($check_price != $total_price){
                throw new Exception('총 금액이 맞지 않습니다.');
            }

            //메뉴 변경
            foreach ($menus as $menu) {
                if ($menu['delete'] === true) {
                    // 메뉴 취소
                    $result = $this->orderDetailModel
                        ->where('order_serial', $order_serial)
                        ->where('menu_serial', $menu['serial'])
                        ->set('is_canceled', 'Y')
                        ->update();
                    if(!$result){
                        $dbError = $this->orderDetailModel->errors();
                        if(!empty($dbError)){
                            log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                        }
                        throw new Exception('주문 변경 실패');
                    }
                }else{// 메뉴 수량 수정, 신규 메뉴 추가
                    $existing = $this->orderDetailModel
                        ->where('order_serial', $order_serial)
                        ->where('menu_serial', $menu['serial'])
                        ->where('is_canceled', 'N')
                        ->first();
                
                    if ($existing) {
                        // 수량 변경
                        $this->orderDetailModel
                            ->where('serial', $existing['serial'])
                            ->set([
                                'count' => $menu['count'],
                                'total_price' => $menu['total_price']
                            ])
                            ->update();
                    } else {
                        // 신규 추가
                        $this->orderDetailModel->insert([
                            'store_serial' => $store_serial,
                            'order_serial' => $order_serial,
                            'menu_serial' => $menu['serial'],
                            'menu_name' => $menu['name'],
                            'price' => $menu['price'],
                            'count' => $menu['count'],
                            'total_price' => $menu['total_price'],
                            'is_canceled' => 'N'
                        ]);
                    }
                }
            
            }

            $menu_list = $this->orderDetailModel
                ->where('order_serial', $order_serial)
                ->where('is_canceled', 'N')
                ->findAll();
            if($menu_list === false){
                $dbError = $this->orderDetailModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 변경 실패');
            }
            if(empty($menu_list)){
                //주문 취소
                $result = $this->orderModel
                    ->where('serial', $order_serial)
                    ->set([
                        'status' => 'CANCEL',
                        'cancel_date' => date('Y-m-d H:i:s')
                        ])
                    ->update();
                if(!$result){
                    $dbError = $this->orderModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('주문 변경 실패');
                }
            }else{
                $total_price = 0;
                foreach($menu_list as $menu){
                    $total_price += $menu['total_price'];
                }
    
                //주문 정보 업데이트
                $result = $this->orderModel
                    ->where('serial', $order_serial)
                    ->set([
                        'total_price' => $total_price
                    ])
                    ->update();
                if(!$result){
                    $dbError = $this->orderModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('주문 변경 실패');
                }
            }

            $this->db->transComplete();

            return $this->respond([
                'code' => 'S01',
                'message' => '주문 변경 성공'
            ]);
            

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    

    //테이블 주문 전체 취소
    public function tableOrderCancel($order_serial=null){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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

            // 메뉴 취소
            $result = $this->orderDetailModel
                ->where('order_serial', $order_serial)
                ->set('is_canceled', 'Y')
                ->update();
            if(!$result){
                $dbError = $this->orderDetailModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 취소 실패');
            }

            //주문 상태 변경
            $result = $this->orderModel
                ->where('serial', $order_serial)
                ->set([
                    'status' => 'CANCEL',
                    'cancel_date' => date('Y-m-d H:i:s')
                    ])
                ->update();
            if(!$result){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 취소 실패');
            }
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function orderList(){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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

            $start_date = $this->request->getVar('start_date');
            $end_date = $this->request->getVar('end_date');

            if(empty($start_date) || empty($end_date)){
                return $this->failUnauthorized('날짜를 선택해 주세요.');
            }

            $order_list = $this->orderModel
                ->where('store_serial', $store_serial)
                ->where('registered_date >=', $start_date)
                ->where('registered_date <=', $end_date)
                ->findAll();
            if($order_list === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 조회 실패');
            }
            foreach($order_list as $order){
                $menu_list = $this->orderDetailModel
                    ->where('order_serial', $order['serial'])
                    ->where('is_canceled', 'N')
                    ->findAll();
                if($menu_list === false){
                    $dbError = $this->orderDetailModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('주문 조회 실패');
                }
                $order_list['menus'][] = $menu_list;
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '주문 조회 성공',
                'data' => $order_list
            ]);
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //특정 테이블 주문 내역
    public function tableOrderList($table_number){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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

            if(empty($table_number)){
                return $this->failUnauthorized('테이블 정보 누락');
            }

            $order_list = $this->orderModel
                ->where('store_serial', $store_serial)
                ->where('table_number', $table_number)
                ->where('status', 'WAIT')
                ->findAll();
            if($order_list === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 조회 실패');
            }

            foreach($order_list as $order){
                $menu_list = $this->orderDetailModel
                    ->where('order_serial', $order['serial'])
                    ->where('is_canceled', 'N')
                    ->findAll();
                if($menu_list === false){
                    $dbError = $this->orderDetailModel->errors();
                    if(!empty($dbError)){
                        log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                    }
                    throw new Exception('주문 조회 실패');
                }
                $order_list['menus'][] = $menu_list;
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '주문 조회 성공',
                'data' => $order_list
            ]);
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function orderMove(){
        log_message('info', '요청 URI: ' . $this->request->getUri()->getPath());
        try{
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

            $order_serial = $this->request->getVar('order_serial');
            $table_number = $this->request->getVar('table_number');
            $table_name = $this->request->getVar('table_name');

            if(empty($order_serial) || empty($table_number) || empty($table_name)){
                return $this->failUnauthorized('필수 정보 누락');
            }

            //유효한 테이블인지 확인
            $result = $this->tableModel
                ->where('store_serial', $store_serial)
                ->where('table_number', $table_number)
                ->where('table_name', $table_name)
                ->where('is_active', 'Y')
                ->first();
            if(!$result){
                $dbError = $this->tableModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('사용할 수 없는 테이블입니다.');
            }

            $result = $this->orderModel->update($order_serial,[
                'table_number' => $table_number,
                'table_name' => $table_name
            ]);
            if(!$result){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 이동 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'message' => '주문 이동 성공'
            ]);
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    
}