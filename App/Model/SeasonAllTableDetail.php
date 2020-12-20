<?php

namespace App\Model;

use App\Base\BaseModel;

class SeasonAllTableDetail extends BaseModel
{
	//获取赛季积分榜数据-全量
	protected $tableName = 'season_all_table_detail';
	
	public function getLimit($page, $limit): SeasonAllTableDetail
	{
		return $this->order('match_time', 'DESC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
	}
}