<?php

namespace App\Base;

use EasySwoole\ORM\AbstractModel;

abstract class BaseModel extends AbstractModel
{
	// private static $instance = [];
	
	/**
	 * @param mixed ...$args
	 * @return BaseModel
	 * @throws
	 */
	static function getInstance(...$args): BaseModel
	{
		// $obj_name = static::class;
		// if (!isset(self::$instance[$obj_name])) {
		// 	self::$instance[$obj_name] = new static(...$args);
		// }
		// return self::$instance[$obj_name];
		return self::create();
	}
	
	/**
	 * @param string $filed
	 * @param        $value
	 * @param        $where
	 * @return bool
	 * @throws
	 */
	public function setField(string $filed, $value, $where = []): bool
	{
		if (empty($filed)) return false;
		return self::create()->update([$filed => $value], $where);
	}
	
	/**
	 * @param $id
	 * @param $data
	 * @return bool
	 * @throws
	 */
	public function saveDataById(int $id, array $data): bool
	{
		return empty($data) || $id < 1 ? false : $this->update($data, $id);
	}
	
	/**
	 * 新增数据
	 * @param array $data
	 * @return int
	 * @throws
	 */
	public function insert(array $data): int
	{
		if (empty($data)) return 0;
		$id = self::create($data)->save();
		return empty($id) || $id < 1 ? 0 : $id;
	}
	
	/**
	 * @param        $where
	 * @param string $fields
	 * @return mixed
	 * @throws
	 */
	public function findOne($where, $fields = '*')
	{
		$data = null;
		// 查询字段
		$fields = empty($fields) || $fields == '*' || !(is_array($fields) || is_string($fields)) ? null : $fields;
		if (!empty($where)) {
			$self = self::create();
			if (!empty($fields)) $self = $self->field($fields);
			if (is_array($where)) {
				foreach ($where as $field => $v) {
					if (is_string($v)) $v = [$v];
					if (!is_array($v)) continue;
					if (isset($v[1])) {
						$type = strtolower($v[1]);
						if ($type == 'like') {
							$v[0] = '%' . trim($v[0], '% ') . '%';
						} elseif ($type == 'in' && is_string($v[0])) {
							$str = trim($v[0]);
							$v[0] = array_filter(array_unique(array_filter(explode(',', $str))));
							if (preg_match('/^\d+(,\d+)*$/', $str)) {
								$v[0] = array_map(function ($x) {
									return intval($x);
								}, $v[0]);
							}
							if (empty($v[0])) $v = ['-1'];
						}
					}
					$self = $self->where($field, ...$v);
				}
			} elseif (is_string($where) || is_integer($where)) {
				$where = trim($where . '');
				if (preg_match('/^\d+$/', $where)) {
					$where = intval($where);
					if ($where > 0) $data = $self->get($where);
					return empty($data) ? null : $data;
				} elseif (!empty($where)) {
					$self = $self->where($where);
				}
			}
			$data = $self->get();
		}
		return empty($data) ? null : $data;
	}
	
	/**
	 * @param null $where
	 * @param null $fields
	 * @param null $order
	 * @param bool $isPager
	 * @param      $page
	 * @param      $size
	 * @return array
	 * @throws
	 */
	public function findAll($where = null, $fields = null, $order = null, bool $isPager = false, $page = 0, $size = 10): array
	{
		$page = $isPager && (empty($page) || intval($page) < 1) ? 0 : intval($page);
		$size = empty($size) || intval($size) < 1 ? 10 : intval($size);
		$self = $this;
		// 查询条件
		if (!empty($where)) {
			if (is_array($where)) {
				foreach ($where as $field => $v) {
					if (is_string($v)) $v = [$v];
					if (!is_array($v)) continue;
					if (isset($v[1])) {
						$type = strtolower($v[1]);
						if ($type == 'like') {
							$v[0] = '%' . trim($v[0], '% ') . '%';
						} elseif ($type == 'in' && is_string($v[0])) {
							$str = trim($v[0]);
							$v[0] = array_filter(array_unique(array_filter(explode(',', $str))));
							if (preg_match('/^\d+(,\d+)*$/', $str)) {
								$v[0] = array_map(function ($x) {
									return intval($x);
								}, $v[0]);
							}
							if (empty($v[0])) $v = ['-1'];
						}
					}
					$self = $self->where($field, ...$v);
				}
			} elseif (is_string($where)) {
				$where = trim($where);
				if (!empty($where)) $self = $self->where($where);
			}
		}
		// 查询字段
		$fields = empty($fields) || $fields == '*' || !(is_array($fields) || is_string($fields)) ? null : $fields;
		if (!empty($fields)) $self = $self->field($fields);
		// 排序部分
		if (!empty($order) && is_string($order)) {
			$order = trim(preg_replace('/\s+,\s+/', ',', $order), ',');
		}
		if (!empty($order)) {
			if (is_array($order)) {
				$order = array_values($order);
				if (is_string($order[0])) {
					if (count($order) == 2) $self = $self->order(...$order);
				} elseif (is_array($order[0])) {
					foreach ($order as $v) {
						if (count($v) == 2) {
							$self = $self->order(...$v);
						}
					}
				}
			} elseif (is_string($order) && count(explode(',', $order)) == 2) {
				$self = $self->order(...explode(',', $order));
			}
		}
		// 获取清单
		if ($isPager) {
			$tmp = $self->page($page, $size)->withTotalCount();
			$list = $tmp->all();
			if (empty($list)) $list = [];
			$count = $tmp->lastQueryResult()->getTotalCount();
			return [$list, $count];
		}
		if ($page > 0 && $size > 0) $self = $self->page($page, $size);
		$list = $self->all();
		return empty($list) ? [] : $list;
	}
}