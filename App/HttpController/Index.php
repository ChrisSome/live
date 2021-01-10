<?php
namespace App\HttpController;

use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\Utility\AnnotationDoc;

class Index extends AnnotationController
{
    function index()
    {
        $doc = new AnnotationDoc();
        $string = $doc->scan2Html(EASYSWOOLE_ROOT.'/App/HttpController');
        $this->response()->withAddedHeader('Content-type',"text/html;charset=utf-8");
        $this->response()->write($string);
        $data = $this->request()->getRequestParam();
        $this->response()->write("your name is 111");
    }
}