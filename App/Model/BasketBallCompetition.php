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

}
