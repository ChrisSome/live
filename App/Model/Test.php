<?php

namespace App\Model;

use App\Base\BaseModel;
use App\Base\FatherModel;
use EasySwoole\ORM\AbstractModel;

class Test extends FatherModel {
    public $tableName = "admin_match_list";

    /**
     * 获取主队名称
     * @return string
     * @throws \Throwable
     */
    public function homeTeamName()
    {
        return $this->hasOne(AdminTeam::class, null, 'home_team_id', 'team_id');


    }

    /**
     * 获取客队名称
     * @return string
     * @throws \Throwable
     */
    public function awayTeamName()
    {
        return $this->hasOne(AdminTeam::class, null, 'away_team_id', 'team_id');


    }

    /**
     * 获取赛事名
     * @return string
     * @throws \Throwable
     */
    public function competitionName()
    {
        return $this->hasOne(AdminCompetition::class, null, 'competition_id', 'competition_id');

    }

    public function steamLink()
    {
        return $this->hasOne(AdminSteam::class, null, 'match_id', 'match_id');

    }
}