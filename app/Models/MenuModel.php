<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuModel extends Model{
    protected $table = 'menu';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'category_serial', 'is_active', 'name', 'price', 'desc', 'is_displayed', 'soldout', 'image', 'sort_order'];
}