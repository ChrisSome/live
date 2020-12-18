<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;

class Broad extends FrontUserController
{
    public $needCheckToken = true;

    public function index()
    {
      return $this->render('front.broad.list');
    }


    public function getList()
    {

    }
}