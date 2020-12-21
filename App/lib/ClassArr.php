<?php

namespace App\lib;

class ClassArr
{
	public function uploadClassStat(): array
	{
		return [
			'image' => '\App\lib\Upload\Image',
			'video' => '\App\lib\Upload\Video',
		];
	}
	
	/**
	 * @param          $type
	 * @param          $supportedClass
	 * @param array    $params
	 * @param boolean  $needInstance
	 * @return mixed
	 * @throws
	 */
	public function initClass($type, $supportedClass, $params = [], $needInstance = true)
	{
		if (!array_key_exists($type, $supportedClass)) return null;
		$className = $supportedClass[$type];
		return $needInstance ? (new \ReflectionClass($className))->newInstanceArgs($params) : $className;
	}
}