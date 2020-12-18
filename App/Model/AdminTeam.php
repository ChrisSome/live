<?php
namespace App\Model;

use App\Base\BaseModel;

class AdminTeam  extends BaseModel
{
    protected $tableName = "admin_team_list";

    public function getCountry()
    {
        return $this->hasOne(AdminCountryList::class, null, 'country_id', 'country_id');
    }

    public function getCompetition()
    {
        return $this->hasOne(AdminCompetition::class, null, 'competition_id', 'competition_id');

    }

    public function getLimit($page, $limit)
    {
        return $this->order('market_value', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function getManager()
    {
        return $this->hasOne(AdminManagerList::class, null, 'manager_id', 'manager_id');

    }
}