<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;

class Broad extends FrontUserController
{
	protected $needCheckToken = true;
	
	public function getList()
	{
	}
}