<?php

namespace App\Controllers;

use Exception;
use CodeIgniter\RESTful\ResourceController;
use App\models\AdminModel;
use App\models\OrderModel;
use App\models\OrderDetailModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PaymentController extends ResourceController
{

    protected $format = 'json';
    protected $adminModel;
    protected $orderModel;
    protected $orderDetailModel;

    public function __construct()
    {
        $this->db = \Config\Database::connect();

        $this->adminModel = new AdminModel();
        $this->orderModel = new OrderModel();
        $this->orderDetailModel = new OrderDetailModel();
    }

    //결제
    public function payment($order_serial=null){
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

            //주문 확인
            $result = $this->orderModel->find($order_serial);
            if(!$result){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 주문 정보가 존재하지 않습니다.');
            }

            //현금결제
            $result = $this->orderModel->where('serial', $order_serial)
                ->set([
                    'status' => 'PAYMENT',
                    'payment_type' => 'CASH',
                    'payment_date' => date('Y-m-d H:i:s')
                ])
                ->update();
                log_message('info', '쿼리 로그: ' . $this->db->getLastQuery());
            if(!$result){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('결제 실패');
            }
    
    
            return $this->respond([
                'code' => 'S01',
                'message' => '결제 성공'
            ]);

        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }

    }

    //결제 취소
    public function paymentCancel($order_serial=null){
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

            //주문 확인
            $result = $this->orderModel->find($order_serial);
            if(!$result){
                $dbError = $this->orderModel->errors();
                if(!empty($dbError)){
                    log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                }
                throw new Exception('해당 주문 정보가 존재하지 않습니다.');
            }

            if($result['status'] == 'CANCEL'){
                throw new Exception('이미 취소된 주문입니다.');
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
                log_message('error', 'DB 실패: ' . implode(', ', $dbError));
                throw new Exception('결제 취소 실패');
            }
        }catch(Exception $e){
            return $this->fail([
                'code' => 'E01',
                'message' => $e->getMessage()
            ]);
        }
    }
}