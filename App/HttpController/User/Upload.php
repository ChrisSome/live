<?php

namespace App\HttpController\User;

use App\lib\Utils;
use App\lib\ClassArr;
use App\lib\OssService;
use App\Utility\Message\Status;
use App\Base\FrontUserController;

class Upload extends FrontUserController
{
	protected $isCheckSign = false;
	protected $needCheckToken = true;
	
	/**
	 * 普通上传
	 * @throws
	 */
	public function index()
	{
		// 参数校验
		$request = $this->request();
		// 图片文件
		$file = $request->getUploadedFile('file');
		if (empty($file)) $this->output(Status::CODE_ERR, '上传图片为空');
		// 上传类型
		$uploadType = $request->getRequestParam('type');
		if (empty($uploadType) || !in_array($uploadType, ['avatar', 'system', 'option', 'other'])) {
			$this->output(Status::CODE_ERR, '未知的上传类型');
		}
		// 文件格式校验
		$isImage = getimagesize($file->getTempName());
		if (empty($isImage)) $this->output(400, '上传文件不合法');
		try {
			$classObj = new ClassArr();
			$classStats = $classObj->uploadClassStat();
			$uploadObj = $classObj->initClass('image', $classStats, [$request, 'image']);
			$uploadObj->upload_type = $uploadType;
			$file = $uploadObj->upload();
		} catch (\Exception $e) {
			$this->output(400, $e->getMessage(), []);
		}
		if (empty($file)) $this->output(400, "上传失败", []);
		// 输出数据
		$result = ['code' => Status::CODE_OK, 'data' => ['src' => $file, 'title' => '上传图片']];
		$this->output(200, '', $result, true);
	}
	
	/**
	 * OSS上传
	 * @throws
	 */
	public function ossUpload()
	{
		$request = $this->request();
		// 图片文件
		$file = $request->getUploadedFile('file');
		// 参数校验
		$uploadType = $request->getRequestParam('type');
		if (empty($uploadType) || !in_array($uploadType, ['avatar', 'system', 'option', 'other'])) {
			$this->output(Status::CODE_ERR, '未知的上传类型');
		}
		$filename = $file->getClientFileName();
		$suffix = pathinfo($filename)['extension'];
		$baseName = Utils::getFileKey($filename) . '.' . $suffix;
		// 上传文件
		$client = new OssService($uploadType);
		$res = $client->uploadFile($baseName, $file->getTempName());
		if ($res['status'] != Status::CODE_OK) $this->output(Status::CODE_ERR, $res['msg']);
		// 上传成功
		$this->output(Status::CODE_OK, '', ['imgUrl' => $res['imgUrl']]);
	}
}