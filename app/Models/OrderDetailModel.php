<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderDetailModel extends Model{
    protected $table = 'order_detail';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'order_serial', 'menu_serial', 'menu_name', 'price', 'count', 'total_price', 'is_canceled'];
}