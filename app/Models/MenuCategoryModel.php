<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuCategoryModel extends Model{
    protected $table = 'menu_category';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'store_serial', 'is_active', 'name', 'is_displayed', 'sort_order'];
}