<?php

namespace App\Utility\RoleGroup;

use App\Model\AdminRole;
use App\Model\AdminRule;

class RoleGroup
{
	private $roleId;
	
	public function __construct($roleId)
	{
		$this->roleId = $roleId;
	}
	
	public function hasRule($rule): bool
	{
		if (empty($rule)) return true;
		if (!isset($this->rules)) {
			$data = AdminRole::getInstance()->findOne($this->roleId);
			$this->rules = !empty($data['rules']) ? AdminRule::getInstance()->getIdsInNode($data['rules']) : [];
		}
		return in_array($rule, $this->rules);
	}
}