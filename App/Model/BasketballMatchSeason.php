<?php

namespace App\Model;

use App\Base\BaseModel;

class BasketballMatchSeason extends BaseModel
{
    protected $tableName = "basketball_match_season";

    /**
     * @param $page
     * @param $limit
     * @return BasketballMatchSeason
     */
    public function getLimit($page, $limit, $sortColumn, $sort = 'DESC')
    {
        return $this->order($sortColumn, $sort)
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

}
