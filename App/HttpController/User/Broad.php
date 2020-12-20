<?php

namespace App\HttpController\User;

use App\Base\FrontUserController;

class Broad extends FrontUserController
{
	//protected $isCheckSign = false;
	protected $needCheckToken = true;
}