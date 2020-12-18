<?php

namespace App\lib\Upload;

class BaseUpload
{
    private $type = '';

    public $request;

    //允许上传的文件
    const ALLOWED_MIME_TYPES = [];
    //允许上传文件最大
    const MAX_UPLOAD_SIZE = 50;

    public function __construct($request)
    {
        $this->request = $request;
        if(empty($type)) {
            $files = $this->request->getSwooleRequest()->files;
            var_dump($files);
            $types = array_keys($files);
            $this->type = $types[0];
        } else {
            $this->type = $type;
        }
    }


    public function moveTo()
    {
        $oUploadedFile = $this->request->getUploadedFile($this->type);
        if (!$this->type || ! $oUploadedFile) {
           throw new \Exception('没有要上传的文件', 404);
        }
        //var_dump($oUploadedFile);
        //mime类型判断
        $sMediatype = $oUploadedFile->getClientMediaType();
        if (!in_array($sMediatype, self::ALLOWED_MIME_TYPES)) {
            throw new \Exception('未知的文件类型', 403);
        }
        //大小判断
        $iSize = $oUploadedFile->getSize();
        if ($iSize > self::MAX_UPLOAD_SIZE) {
            throw new \Exception('上传文件过大', 406);
        }
        //上传到目录
        $aMtype = explode('/', $sMediatype);
        $sTargetDir = __DIR__.'/resources/'.$sMediatype.'/'.date('Y-m-d').'/'.uniqid(microtime(true)).'.'
            .$aMtype[1];
        // $sTargetDir = __DIR__.'/resources/'.$sMediatype.'/'.date('Y-m-d').'/'.uniqid(microtime(true)).'.mp4';
        if ($mUploadResult = $oUploadedFile->moveTo($sTargetDir)) {
            return $this->writeJson(200, '上传成功', ['file' => $sTargetDir]);
        } else {
            return $this->writeJson(500, '上传文件失败', ['error' => $oUploadedFile->getError()]);
        }
    }

}