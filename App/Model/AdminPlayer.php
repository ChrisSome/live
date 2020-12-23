<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminPlayer extends BaseModel
{
	protected $tableName = 'admin_player_list';
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getTeam()
	{
		return $this->hasOne(AdminTeam::class, null, 'team_id', 'team_id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getCountry()
	{
		return $this->hasOne(AdminCountryList::class, null, 'country_id', 'country_id');
	}
}