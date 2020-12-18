<?php

namespace App\lib;

use App\Utility\Message\Status;
use OSS\OssClient;
use OSS\Core\OssException;
class OssService
{

    public $ossClient;
    private $accessKey = 'LTAI4G9zzxgELnbGmgo8PjoT';                  //RAM账号
    private $accessKeySecret = 'r0wzZ49VArVptmPmET6NnqrjpmivE1';      //RAM密钥

    private $endpoint = 'oss-cn-hongkong.aliyuncs.com';
    public $bucketList = [
        'avatar' => 'live-broadcast-avatar',
        'system' => 'live-broadcast-system',
        'option' => 'live-broadcast-option',
        'other'  => 'ive-broadcast-other'
    ];
    private $objectName = 'live-boradcast';
    private $bucket;
    public function __construct($bucket)
    {

        if (!array_key_exists($bucket, $this->bucketList))
        {
            return false;
        }

        $ossClient = new OssClient($this->accessKey, $this->accessKeySecret, $this->endpoint);
        $ossClient->setTimeout(3600);
        // 设置建立连接的超时时间，单位秒，默认10秒。
        $ossClient->setConnectTimeout(10);
        // 设置存储空间的存储类型为低频访问类型，默认是标准类型。
        $options = array(
            OssClient::OSS_STORAGE => OssClient::OSS_STORAGE_IA
        );
        if (!$ossClient->doesBucketExist($this->bucketList[$bucket])) {
            // 设置存储空间的权限为公共读，默认是私有读写。
            $ossClient->createBucket($this->bucketList[$bucket], OssClient::OSS_ACL_TYPE_PUBLIC_READ, $options);
        }
        $this->ossClient = $ossClient;
        $this->bucket = $this->bucketList[$bucket];

    }

    /**
     * @param $fileName
     * @param $tmpFile
     * @return mixed
     */
    public function uploadFile($fileName, $tmpFile)
    {
        try{
            $ossRes = $this->ossClient->uploadFile($this->bucket, $fileName, $tmpFile);
            $returnData['status'] = Status::CODE_OK;
            $returnData['imgUrl'] = $ossRes['info']['url'];
            $returnData['msg'] = '';

        } catch (OssException $e) {
            $returnData['status'] = Status::CODE_ERR;
            $returnData['msg'] = $e->getMessage();

        }
        return $returnData;
    }

    public function createBucket($bucket)
    {
        $ossClient = new OssClient($this->accessKey, $this->accessKeySecret, $this->endpoint);
        $ossClient->createBucket($this->bucketList[$bucket], OssClient::OSS_ACL_TYPE_PUBLIC_READ);
//        return $this->ossClient->createBucket($bucket, OssClient::OSS_ACL_TYPE_PUBLIC_READ);
    }

    public function checkIfExists($bucket)
    {
        return $this->ossClient->doesBucketExist('live-broadcast-avatar');

    }
}