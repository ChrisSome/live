<?php

namespace App\Model;

use App\Base\BaseModel;

class BasketBallCompetition extends BaseModel
{
    protected $tableName = "basketball_competition_list";

    public function getLimit($page, $limit, $order, $sort)
    {

        return $this->order($order, $sort)
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getSeasonList()
    {
        if ($seasons = $this->hasMany(BasketballSeasonList::class, null, 'competition_id', 'competition_id')) {
            return $seasons;
        } else {
            return [];
        }
    }

    public function getTeamList()
    {
        if ($team = $this->hasMany(BasketballTeam::class, null, 'competition_id', 'competition_id')) {
            return $team;
        } else {
            return [];
        }
    }

}
