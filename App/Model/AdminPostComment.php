<?php
/**
 * @property boolean isRookie   是否新手资产
 */
namespace App\Model;

use App\Base\BaseModel;
use App\lib\Tool;
use EasySwoole\Mysqli\QueryBuilder;
use App\Utility\Log\Log;
use think\db\Query;


class AdminPostComment extends BaseModel
{
    protected $tableName = "admin_user_post_comments";
    protected $relationT = "admin_user";

    const STATUS_NORMAL = 0;        //正常
    const STATUS_REPORTED = 1;       //被举报
    const STATUS_DEL = 2;           //删除
    const STATUS_ADMIN_DEL = 3;           //后台删除

    const SHOW_IN_FRONT = [self::STATUS_NORMAL, self::STATUS_REPORTED, self::STATUS_ADMIN_DEL];
    /**
     * $value mixed 是原值
     * $data  array 是当前model所有的值
     */
    protected function getContentAttr($value, $data)
    {
        return base64_decode($data['content']);

    }
    public function findAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }

    public function getAll($page, $limit)
    {
        return $this->order('created_at', 'DESC')
            ->limit(($page - 1) * $limit, $limit)
            ->withTotalCount();
    }

    public function saveIdData($id, $data)
    {
        return $this->where('id', $id)->update($data);
    }

    //回复用户信息
    public function uInfo()
    {

        if ($user = $this->hasOne(AdminUser::class, null, 'user_id', 'id')) {
            return $user->field(['id', 'mobile', 'photo', 'nickname', 'level', 'is_offical']);
        }
        return [];
    }

    //被回复用户信息
    public function tuInfo()
    {
        if ($user = $this->hasOne(AdminUser::class, null, 't_u_id', 'id')) {
            return $user->field(['id', 'mobile', 'photo', 'nickname', 'level', 'is_offical']);
        } else {
            return [];
        }

    }

    public function postInfo()
    {
        return $this->hasOne(AdminUserPost::class, null, 'post_id', 'id')->field(['id', 'title', 'content', 'created_at']);

    }

    /**
     * 是否点赞
     * @param $uid
     * @param $cid
     * @return mixed|null
     * @throws \Throwable
     */
    public function isFabolus($uid, $cid)
    {
        return $this->hasOne(AdminUserOperate::class, function (QueryBuilder $queryBuilder) use($uid, $cid) {
            $queryBuilder->where('type', 1);
            $queryBuilder->where('user_id', $uid);
            $queryBuilder->where('item_id', $cid);
            $queryBuilder->where('is_cancel', 0);
            $queryBuilder->where('item_type', 2);
        }, 'id', 'item_id');
    }


    public function belong()
    {
        return $this->belongsToMany(AdminUser::class, 'admin_user','id', 'id', function(QueryBuilder $query){
            $query->limit(10);
        });
    }


    /**
     * @param $parentId
     * @return mixed|null
     * @throws \Throwable
     */
    public function getParentContent()
    {
       return $this->hasOne(self::class, null, 'parent_id', 'id');


    }

    public function getTopComment()
    {
        return $this->hasOne(self::class, null, 'top_comment_id', 'id');

    }

}
