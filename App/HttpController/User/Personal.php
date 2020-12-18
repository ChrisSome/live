<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;

class Personal extends FrontUserController
{
    public $needCheckToken = true;

    public function index()
    {
        $this->render('front.personal.index');
    }

}