<?php

namespace App\Base;

use EasySwoole\ORM\AbstractModel;
use EasySwoole\Component\CoroutineSingleTon;

abstract class FatherModel extends AbstractModel
{
	use CoroutineSingleTon;
}
