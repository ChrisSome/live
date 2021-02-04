<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminInterestMatches  extends BaseModel{

    const STATUS_NORMAL = 1;
    const FOOTBALL_TYPE = 1;
    const BASKETBALL_TYPE = 2;
    protected $tableName  = "admin_user_interest_matches";

}