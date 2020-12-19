<?php

namespace App\Base;

use Throwable;
use EasySwoole\ORM\AbstractModel;
use EasySwoole\ORM\Exception\Exception;
use EasySwoole\Component\CoroutineSingleTon;

abstract class BaseModel extends AbstractModel
{
	use CoroutineSingleTon;
	
	/**
	 * @param string $filedName
	 * @param        $value
	 * @param array  $where
	 * @return bool
	 * @throws Exception | Throwable
	 */
	public function setValue(string $filedName, $value, $where = []): bool
	{
		if (empty($filedName)) return false;
		return self::create()->update([$filedName => $value], $where);
	}
	
	/**
	 * 新增数据
	 * @param array $data
	 * @return int
	 * @throws Exception | Throwable
	 */
	public function insert(array $data): int
	{
		if (empty($data)) return 0;
		$id = self::create($data)->save();
		return empty($id) || $id < 1 ? 0 : $id;
	}
	
	/**
	 * @param $options
	 * @return array|null
	 * @throws Exception | Throwable
	 */
	public function find($options): ?array
	{
		$data = null;
		if (!is_array($options)) {
			$id = intval($options);
			if ($id < 1) return null;
			$data = self::create()->get($id);
		} else {
			$data = self::create()->where($options)->get();
		}
		return empty($data) ? null : $data->toArray();
	}
	
	/**
	 * 设置排序
	 * @param mixed ...$args
	 * @return AbstractModel
	 */
	public function orderBy(...$args): AbstractModel
	{
		return parent::order($args);
	}
}