<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminInformation extends BaseModel
{
    const STATUS_DELETE = 0;
    const STATUS_NORMAL = 1;
    const STATUS_REPORTED = 2;

    const REPORTED_REASON = [
        ['type' => 1, 'content' => '广告欺诈'],
        ['type' => 2, 'content' => '恶意评论'],
        ['type' => 3, 'content' => '低俗色情'],
        ['type' => 4, 'content' => '刷屏'],
        ['type' => 5, 'content' => '恶意抄袭'],
        ['type' => 6, 'content' => '辱骂攻击'],
        ['type' => 7, 'content' => '侵犯隐私'],
        ['type' => 8, 'content' => '其他'],
    ];
    protected $tableName = "admin_information";

    public function getLimit($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function getMatch()
    {
        return $this->hasOne(AdminMatch::class, null, 'match_id', 'match_id');
    }

    public function getCompetition($sportType = 1)
    {
        return $this->hasOne(($sportType == 1) ? AdminCompetition::class : BasketBallCompetition::class, null, 'competition_id', 'competition_id');

    }

    public function user_info()
    {
        return $this->hasOne(AdminUser::class, null, 'user_id', 'id')->field(['id', 'nickname', 'photo', 'is_offical', 'level']);

    }




}
