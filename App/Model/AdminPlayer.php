<?php

namespace App\Model;
use App\Base\BaseModel;

class AdminPlayer  extends BaseModel
{
    protected $tableName = "admin_player_list";

    public function getTeam()
    {
        return $this->hasOne(AdminTeam::class, null, 'team_id', 'team_id');
    }

    public function getCountry()
    {
        return $this->hasOne(AdminCountryList::class, null, 'country_id', 'country_id');

    }

    public function getLimit($page, $limit)
    {
        return $this->order('market_value', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

}