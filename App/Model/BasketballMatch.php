<?php

namespace App\Model;

use App\Base\BaseModel;

class BasketballMatch extends BaseModel
{
    protected $tableName = "basketball_match_list";

    public function getCompetition()
    {
        return $this->hasOne(BasketBallCompetition::class, null, 'competition_id', 'competition_id');
    }
}
