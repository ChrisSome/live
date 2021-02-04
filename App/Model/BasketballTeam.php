<?php

namespace App\Model;

use App\Base\BaseModel;

class BasketballTeam extends BaseModel
{
    protected $tableName = "basketball_team";

    public function competitionInfo()
    {
        if ($competition = $this->hasOne(BasketBallCompetition::class, null, 'competition_id', 'competition_id')) {
            return $competition;
        } else {
            return [];
        }

    }
}
