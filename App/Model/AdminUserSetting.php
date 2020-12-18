<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserSetting extends BaseModel
{

    const STATUS_NORMAL = 1;
    const STATUS_DEL = 2;
    protected $tableName = "admin_user_setting";


}