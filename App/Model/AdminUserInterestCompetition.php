<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminUserInterestCompetition extends BaseModel
{
    const FOOTBALL_TYPE = 1;
    const BASKETBALL_TYPE = 2;
    protected $tableName = 'admin_user_interest_competition';
}