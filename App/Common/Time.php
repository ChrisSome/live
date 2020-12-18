<?php

namespace App\Common;

class Time
{

    public static function isBeforeNow($time)
    {
        $timeStamp = strtotime($time);
        if (time() > $timeStamp) {
            return false;
        } else {
            return true;
        }
    }


    public static function isBetween($timeOne, $timeTwo)
    {
        if (!$timeOne || !$timeTwo) return true;
        $timeStampOne = strtotime($timeOne);
        $timeStampTwo = strtotime($timeTwo);
        if (time() >= $timeStampOne && time() <= $timeStampTwo) {
            return true;
        } else {
            return false;
        }
    }
}