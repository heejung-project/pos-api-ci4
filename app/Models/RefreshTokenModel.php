<?php

namespace App\Models;

use CodeIgniter\Model;

class RefreshTokenModel extends Model{
    protected $table = 'refresh_token';
    protected $primaryKey = 'serial';
    protected $allowedFields = ['serial', 'admin_serial', 'refresh_token', 'expire_date'];
}