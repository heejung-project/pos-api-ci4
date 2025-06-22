<?php

namespace App\Models;

use CodeIgniter\Model;

class StoreModel extends Model{
    protected $table = 'store';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'is_active', 'name', 'address', 'address_detail'];
}