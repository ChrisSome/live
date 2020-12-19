<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;

class Broad extends FrontUserController
{
    public bool $needCheckToken = true;

    public function getList()
    {

    }
}