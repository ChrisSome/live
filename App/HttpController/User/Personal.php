<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;

class Personal extends FrontUserController
{
	public $needCheckToken = true;
}