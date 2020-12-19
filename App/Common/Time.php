<?php

namespace App\Common;

class Time
{
	public static function isBeforeNow($time): bool
	{
		$timeStamp = strtotime($time);
		if (time() > $timeStamp) return false;
		return true;
	}
	
	public static function isBetween($timeOne, $timeTwo): bool
	{
		if (!$timeOne || !$timeTwo) return true;
		$timeStampOne = strtotime($timeOne);
		$timeStampTwo = strtotime($timeTwo);
		if (time() >= $timeStampOne && time() <= $timeStampTwo) return true;
		return false;
	}
}