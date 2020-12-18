<?php

namespace App\GeTui;
use IGeTui;
class Test{

    public function __construct()
    {
        include_once __DIR__ . 'IGt.Batch.php';
        include_once __DIR__ . 'IGt.Push.php';
    }

    public  function index()
    {

        $igt = new IGeTui('', '', '');

    }
}

$getui = new Test();
$getui->index();