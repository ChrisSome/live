<?php

namespace App\Model;
use App\Base\BaseModel;
use App\Base\FatherModel;
use EasySwoole\Mysqli\QueryBuilder;

class SeasonMatchList  extends BaseModel
{
    protected $tableName = "match_season_list";


    public function getLimit($page, $limit)
    {
        return $this->order('match_time', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    /**
     * 获取主队名称
     * @return string
     * @throws \Throwable
     */
    public function homeTeam()
    {
        return $this->hasOne(AdminTeam::class, null, 'home_team_id', 'team_id')->field(['team_id', 'name_zh']);


    }

    /**
     * 获取客队名称
     * @return string
     * @throws \Throwable
     */
    public function awayTeam()
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
        return $this->hasOne(AdminCompetition::class, null, 'competition_id', 'competition_id')->field(['competition_id', 'short_name_zh']);

    }

    public function steamLink()
    {
        return $this->hasOne(AdminSteam::class, null, 'match_id', 'match_id');

    }




}