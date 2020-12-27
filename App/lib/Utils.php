<?php
namespace App\lib;

use App\Base\BaseModel;
use App\Utility\Log\Log;
use EasySwoole\Component\Singleton;

/**
 * 通用工具类
 */
class Utils {
    use Singleton;
	/**
     * 生成的唯一性key
     * @param string $str
     * @return string 
    */
    public static function getFileKey($str) {
        return substr(md5(self::makeRandomString() . $str . time() . rand(0, 9999)), 8, 16);
    }

    /**
     * 生成随机字符串
     * @param string $length 长度
     * @return string 生成的随机字符串
     */
    public static function makeRandomString($length = 1) { 
  		
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for($i=0; $i<$length; $i++) {
            $str .= $strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
  }


    /**
     * @param BaseModel $model
     * @param string $where
     * @param null $bind
     * @param string $fields
     * @param bool $singleRow
     * @param null $orderOrGroup
     * @param null $kv
     * @param int $page
     * @param int $size
     * @return array|bool|mixed
     * @throws \Throwable
     */
    public static function queryHandler(BaseModel $model, string $where, $bind = null,
                                        string $fields = '*', bool $singleRow = true, $orderOrGroup = null, $kv = null,
                                        int $page = 0, int $size = 0)
    {
        $needPager = $page > 0 && $size > 0;
        $sqlTemplate = 'select %s from `' . $model->getTableName() . '` as a %s';
        if (empty($fields)) $fields = '*';
        if (!empty($where)) $where = ' where ' . $where;
        $order = $group = '';
        if (!empty($orderOrGroup)) {
            if (!is_array($orderOrGroup) || !empty($orderOrGroup['order'])) {
                $order = ' order by ' . (is_array($orderOrGroup) ? $orderOrGroup['order'] : $orderOrGroup);
            } elseif (!empty($orderOrGroup['group'])) {
                $group = ' group by ' . $orderOrGroup['group'];
            }
        }
        if (!empty($group)) $order = $group . $order;
        if (is_null($bind)) $bind = [];
        if (!is_array($bind)) $bind = [intval($bind)];
        $limit = $needPager ? ' limit ' . (($page - 1) * $size) . ',' . $size : '';
        $sql = sprintf($sqlTemplate, $fields, $where . $order . $limit);
        $tmp = $model->func(function ($builder) use ($sql, $bind) {
            $builder->raw($sql, $bind);
            return true;
        });
        $kv = explode(',', preg_replace('/\s+/', '', $kv));
        if ($singleRow) {
            $tmp = empty($tmp[0]) ? false : $tmp[0];
        } else {
            if (count($kv) >= 2) {
                $mapper = [];
                [$k, $v] = $kv;
                $isInt = isset($kv[2]) && intval($kv[2]) > 0;
                foreach ($tmp as $x) {
                    $key = $x[$k];
                    if ($isInt) $key = intval($key);
                    $mapper[$key] = $v == '*' ? $x : $x[$v];
                }
                $tmp = $mapper;
            }
            if ($needPager) {
                $list = $tmp;
                $sql = sprintf($sqlTemplate, 'count(*) total', $where);
                $tmp = $model->func(function ($builder) use ($sql, $bind) {
                    $builder->raw($sql, $bind);
                    return true;
                });
                $total = empty($tmp[0]['total']) ? 0 : intval($tmp[0]['total']);
                return ['list' => $list, 'total' => $total];
            }
        }
        return $tmp;
    }
}