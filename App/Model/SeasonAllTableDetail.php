<?php

namespace App\Model;
use App\Base\BaseModel;
use App\Base\FatherModel;
use EasySwoole\Mysqli\QueryBuilder;

class SeasonAllTableDetail  extends BaseModel
{
    //获取赛季积分榜数据-全量
    protected $tableName = "season_all_table_detail";


    public function getLimit($page, $limit)
    {
        return $this->order('match_time', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }






}