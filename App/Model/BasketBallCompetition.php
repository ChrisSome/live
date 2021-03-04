<?php

namespace App\Model;

use App\Base\BaseModel;
use EasySwoole\Mysqli\QueryBuilder;

class BasketBallCompetition extends BaseModel
{
    protected $tableName = "basketball_competition_list";

    public function getLimit($page, $limit, $order, $sort)
    {

        return $this->order($order, $sort)
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getSeasonList()
    {
        if ($seasons = $this->hasMany(BasketballSeasonList::class, null, 'competition_id', 'competition_id')) {
            return $seasons;
        } else {
            return [];
        }
    }

    public function getTeamList()
    {
        if ($team = $this->hasMany(BasketballTeam::class, null, 'competition_id', 'competition_id')) {
            return $team;
        } else {
            return [];
        }
    }

    public function getCurrentSeason()
    {
        return $this->hasOne(BasketballSeasonList::class, function (QueryBuilder  $queryBuilder) {
            $queryBuilder->where('is_current', 1);
        } , 'competition_id', 'competition_id');
        //return $this->hasOne(AdminUserOperate::class, function (QueryBuilder $queryBuilder) use($uid, $cid) {
        //            $queryBuilder->where('type', 1);
        //            $queryBuilder->where('user_id', $uid);
        //            $queryBuilder->where('item_id', $cid);
        //            $queryBuilder->where('is_cancel', 0);
        //            $queryBuilder->where('item_type', 2);
        //        }, 'id', 'item_id');
    }

}
