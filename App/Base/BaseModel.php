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
	 * @param null   $order
	 * @return mixed
	 * @throws
	 */
	public function findOne($where, $fields = '*', $order = null)
	{
		$data = null;
		$self = self::create();
		// 查询字段
		$fields = empty($fields) || $fields == '*' || !(is_array($fields) || is_string($fields)) ? null : $fields;
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
						if (count($v) == 2) $self = $self->order(...$v);
					}
				}
			} elseif (is_string($order) && count(explode(',', $order)) == 2) {
				$self = $self->order(...explode(',', $order));
			}
		}
		// 查询条件
		if (!empty($where)) {
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
	 * @param null   $where        条件格式:键值对,
	 *                             键->查询字段(可以是多字段|分割,仅限模糊查询使用), 若键名为or则键值为局部或关系查询, 查询条件也可以为纯字符串
	 *                             值->1数组时(in,like,between等时第一个元素为范围或搜索词,第二个元素为类型(like,in,between)) 2数字/字符串(字段匹配的值)
	 * @param null   $fields
	 * @param null   $orderOrGroup 1排序:参数为数组且中包含order的部分 或 参数是一个字符串(格式:字段,desc/asc)   2分组:参数为数组,其包含group部分
	 * @param bool   $isPager      是否分页
	 * @param int    $page
	 * @param int    $size
	 * @param string $keyAndValue  搜索后的数据集按指定字段的值做键
	 *                             格式:以值作键的字段名,键值对应的字段清单(*或空为全部字段,只有一个字段时键值为该字段的值,否则为数组),是否将键名转为数字类型
	 * @return array
	 * @throws
	 */
	public function findAll($where = null, $fields = null, $orderOrGroup = null,
	                        bool $isPager = false, $page = 0, $size = 10,
	                        string $keyAndValue = ''): array
	{
		$page = $isPager && (empty($page) || intval($page) < 1) ? 0 : intval($page);
		$size = empty($size) || intval($size) < 1 ? 10 : intval($size);
		$self = $this;
		// 查询条件
		if (!empty($where)) {
			if (is_array($where)) {
				foreach ($where as $field => $v) {
					if (empty($v)) continue;
					
					if ($field == 'or') {
						if (is_array($v)) $self->where('(' . join(' or ', $v) . ')');
						continue;
					}
					
					if (is_int($field) && is_string($v)) {
						$self = $self->where($v);
						continue;
					}
					
					if (is_string($field)) {
						$extra = empty($v[1]) ? '' : strtolower(trim($v[1]));
						if ($extra == 'like') {
							$strArr = [];
							$fieldsTmp = explode('|', trim(preg_replace('/\s/', '', strtolower($field)), ' |'));
							foreach ($fieldsTmp as $f) {
								$strArr[] = $f . ' like "%' . $v[0] . '%"';
							}
							$strArr = join(' or ', $strArr);
							$self = $self->where('(' . $strArr . ')');
							continue;
						}
						if (!empty($extra)) {
							if (!is_array($v)) return $isPager ? [[], 0] : [];
							if ($extra == 'in') foreach ($v[0] as $kk => $vv) {
								$v[0][$kk] = intval($vv);
							}
							$self = $self->where($field, ...$v);
						} else {
							if (is_array($v)) return $isPager ? [[], 0] : [];
							$fieldsTmp = explode('|', trim(preg_replace('/\s/', '', strtolower($field)), ' |'));
							if (isset($fieldsTmp[1])) {
								$fqs = [];
								foreach ($fieldsTmp as $f) {
									$fqs[] = $f . '="' . $v . '"';
								}
								$self = $self->where('(' . join(' or ', $fqs) . ')');
							} else {
								$self = $self->where($field, $v);
							}
						}
					} else {
						return $isPager ? [[], 0] : [];
					}
				}
			} elseif (is_string($where)) {
				$where = trim($where);
				if (empty($where)) return $isPager ? [[], 0] : [];
				$self = $self->where($where);
			}
		}
		// 查询字段
		$fields = empty($fields) || $fields == '*' || !(is_array($fields) || is_string($fields)) ? null : $fields;
		if (!empty($fields)) $self = $self->field($fields);
		// 排序/分组部分
		if (!empty($orderOrGroup) && (is_array($orderOrGroup) || is_string($orderOrGroup))) {
			if (is_string($orderOrGroup)) {
				$order = explode(',', trim(preg_replace('/\s+,\s+/', ',', $orderOrGroup), ','));
				if (count($order) == 2) $self = $self->order(...$order);
			} else {
				if (isset($orderOrGroup['order']) || isset($orderOrGroup['group'])) {
					if (!empty($orderOrGroup['order']) && is_string($orderOrGroup['order'])) {
						$order = explode(',', trim(preg_replace('/\s+,\s+/', ',', $orderOrGroup), ','));
						if (count($order) == 2) $self = $self->order(...$order);
					}
					if (!empty($orderOrGroup['group']) && is_string($orderOrGroup['group'])) {
						$self->group($orderOrGroup['group']);
					}
				} else {
					$order = array_values($orderOrGroup);
					if (is_string($order[0])) {
						if (count($order) == 2) $self = $self->order(...$order);
					} elseif (is_array($order[0])) {
						foreach ($order as $k => $v) {
							if (count($v) == 2) $self = $self->order(...$v);
						}
					}
				}
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
		$keyAndValue = empty($keyAndValue) ? '' : trim(strtolower($keyAndValue));
		if (preg_match('/^[a-z_]+\s*,\s*[^,]*(\s*,\s*(true|false))?$/', $keyAndValue)) {
			$keyAndValue = preg_replace('/\s/', '', $keyAndValue);
			[$keyField, $valueFields] = $tmp = explode(',', $keyAndValue);
			$isIntKey = !empty($tmp[2]) && $tmp[2] == 'true';
			$items = [];
			foreach ($list as $v) {
				$key = !empty($v[$keyField]) ? $v[$keyField] : '';
				if (empty($key)) continue;
				$valueFieldsTmp = empty($valueFields) || $valueFields == '*' ? '' : explode('.', $valueFields);
				$item = $v;
				if (!empty($valueFieldsTmp)) {
					$item = [];
					foreach ($valueFieldsTmp as $vv) {
						if (empty($vv)) continue;
						$item[$vv] = empty($v[$vv]) ? '' : $v[$vv];
					}
				}
				$keyFieldValue = $v[$keyField];
				if ($isIntKey) $keyFieldValue = intval($keyFieldValue);
				$items[$keyFieldValue] = is_array($valueFieldsTmp) && count($valueFieldsTmp) == 1 ? $item[$valueFieldsTmp[0]] : $item;
			}
			$list = $items;
		}
		return empty($list) ? [] : $list;
	}
}