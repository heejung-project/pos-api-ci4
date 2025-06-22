<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminModel extends Model{
    protected $table = 'admin';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'is_active', 'id', 'pw', 'name', 'phone'];
}