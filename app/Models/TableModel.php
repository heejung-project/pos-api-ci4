<?php

namespace App\Models;

use CodeIgniter\Model;

class TableModel extends Model{
    protected $table = 'table';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'is_active', 'table_number', 'table_name', 'is_occupied'];
}