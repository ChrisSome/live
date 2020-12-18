<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminPlayerChangeClub extends BaseModel
{

    protected $tableName = "admin_player_change_club";

    public function fromTeamInfo()
    {
        return $this->hasOne(AdminTeam::class, null, 'from_team_id', 'team_id');
    }

    public function ToTeamInfo()
    {
        return $this->hasOne(AdminTeam::class, null, 'to_team_id', 'team_id');
    }

    public function getLimit($page, $limit)
    {
        return $this->order('transfer_time', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
}
