<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model{
    protected $table = 'order';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'table_number', 'table_name', 'status', 'total_price', 'payment_type', 'registered_date', 'cancel_date', 'payment_date'];
}