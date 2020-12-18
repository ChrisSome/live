<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminSeason extends BaseModel
{

    protected $tableName = "admin_season_list";

    public function getCompetition()
    {
        return $this->hasOne(AdminCompetition::class, null, 'competition_id', 'competition_id')->field(['competition_id', 'short_name_zh']);
    }
}
