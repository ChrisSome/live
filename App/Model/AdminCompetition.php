<?php
namespace App\Model;

use App\Base\BaseModel;
class AdminCompetition extends BaseModel
{
    protected $tableName = "admin_competition_list";

    public function getSeason()
    {
        return $this->hasMany(AdminSeason::class, null, 'competition_id', 'competition_id');


    }

    public function getLimit($page, $limit) {
        return $this->order('competition_id', 'ASC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }
}