<?php

namespace App\Utility\RoleGroup;

class RoleGroup1  extends RoleGroup
{
	public function __construct($roleId)
	{
		return ;
	}
	
	public function hasRule($rule)
	{
		return true;
	}
}