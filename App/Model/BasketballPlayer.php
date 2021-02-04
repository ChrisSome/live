<?php

namespace App\Model;

use App\Base\BaseModel;

class BasketballPlayer extends BaseModel
{
    protected $tableName = "basketball_player_list";

    public function teamInfo()
    {
        if ($team = $this->hasOne(BasketballTeam::class, null, 'team_id', 'team_id')) {
            return $team->field(['team_id', 'logo', 'name_zh', 'short_name_zh']);
        } else {
            return [];
        }

    }

    public function getSeasonList()
    {
        if ($team = $this->hasOne(BasketballTeam::class, null, 'team_id', 'team_id')) {
            if ($competition = $team->competitionInfo()) {
                return $competition->getSeasonList();
            }
        } else {
            return [];
        }
    }

    public function getTeamList()
    {
        if ($team = $this->hasOne(BasketballTeam::class, null, 'team_id', 'team_id')) {
            if ($competition = $team->competitionInfo()) {
                return $competition->getTeamList();
            }
        } else {
            return [];
        }
    }


}
