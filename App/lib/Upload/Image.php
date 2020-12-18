<?php

namespace App\lib\Upload;



class Image extends Base
{

    /**
     * fileType
     * @var string
     */
    public $fileType = "image";

    public $maxSize = 122;

    /**
     * 文件后缀的medaiTypw
     * @var [type]
     */
    public $fileExtTypes = [
        'png',
        'jpeg',
        // todo
    ];
}