<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\OrderModel;
use App\models\OrderDetailModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class SalesController extends ResourceController{
    protected $format = 'json';
    protected $adminModel;
    protected $orderModel;
    protected $orderDetailModel;

    public function __construct(){
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->orderModel = new OrderModel();
        $this->orderDetailModel = new OrderDetailModel();
    }

    public function salesInfo(){
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

            $data = array();

            //주문 리스트
            $order_list = $this->orderModel
            ->where('store_serial', $store_serial)
            ->where('registered_date >= ', $start_date)
            ->where('registered_date <= ', $end_date)
            ->findAll();
            if($order_list === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 조회 실패');
            }

            $data['order'] = $order_list;

            //총 매출금액
            $row = $this->orderModel
                ->selectSum('total_price')
                ->where('store_serial', $store_serial)
                ->where('registered_date >=', $start_date)
                ->where('registered_date <=', $end_date)
                ->where('status', 'PAYMENT')
                ->first();
            if($row === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 날짜에 주문이 없습니다.');
            }
            $total_price = $row['total_price'] ?? 0;

            $data['total_price'] = $total_price;

            return $this->respond([
                'code' => 'S01',
                'data' => $data
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function orderDetail($order_serial = null){
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

            //주문 상세
            $order = $this->orderDetailModel
            ->where('order_serial', $order_serial)
            ->where('is_canceled ', 'N')
            ->findAll();
            if($order === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 상세 조회 실패');
            }

            return $this->respond([
                'code' => 'S01',
                'data' => $order
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }

    //메뉴 판매 순위 리스트
    public function menuSales(){
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

            $sql = "SELECT D.menu_serial, D.menu_name, SUM(D.count) as total_count
                    FROM `order` as O, order_detail as D
                    WHERE O.registered_date between ? and ?
                    AND O.status = 'PAYMENT' AND O.serial = D.order_serial AND D.is_canceled = 'N'
                    GROUP BY D.menu_serial, D.menu_name
                    ORDER BY total_count DESC";
            $query = $this->db->query($sql, array($start_date, $end_date));
            if($query === false){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('주문 조회 실패');
            }

            $result_array = $query->getResultArray();

            return $this->respond([
                'code' => 'S01',
                'data' => $result_array
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}