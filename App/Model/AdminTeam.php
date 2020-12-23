<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminTeam extends BaseModel
{
	protected $tableName = 'admin_team_list';
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getCountry()
	{
		return $this->hasOne(AdminCountryList::class, null, 'country_id', 'country_id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getCompetition()
	{
		return $this->hasOne(AdminCompetition::class, null, 'competition_id', 'competition_id');
	}
	
	/**
	 * @return mixed
	 * @throws
	 */
	public function getManager()
	{
		return $this->hasOne(AdminManagerList::class, null, 'manager_id', 'manager_id');
	}
}