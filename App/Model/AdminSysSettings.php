<?php

namespace App\Model;

use App\Base\BaseModel;

class AdminSysSettings extends BaseModel
{
    protected $tableName = "admin_sys_setting";

    const SYSTEM_SETTING_KEY = 'admin:system:%s';
    const SETTING_DATA_COMPETITION = 'data_competition';
    const SETTING_TITLE_BANNER = 'information_title_banner';
    const SETTING_BASKETBALL_TITLE_BANNER = 'basketball_title_banner';
    const SETTING_HOT_SEARCH = 'hot_setting';
    const SETTING_HOT_SEARCH_CONTENT = 'default_search_content';
    const SETTING_HOT_SEARCH_COMPETITION = 'hot_search_competition';  //热搜赛事
    const SETTING_MATCH_NOTICEMENT = 'match_noticement';  //热搜赛事
    const SETTING_OPEN_ADVER = 'open_adver';  //开屏广告页
    const COMPETITION_ARR = 'array_competition';  //左上角的赛事列表数组
    const RECOMMEND_COM = 'recommond_com'; //左上角的赛事列表
    const BASKETBALL_COMPETITION = 'array_competition_basketball'; //篮球赛事
    const JSON_BASKETBALL_COMPETITION = 'json_competition_basketball'; //篮球赛事
    public function findAll($page, $limit)
    {
        return $this
            ->order('created_at', 'desc ')
            ->limit(($page - 1) * $limit, $limit)
            ->all();
    }


    public function saveIdData($id, $data)
    {
        //需要修改对应配置项的值，更新redis
        return $this->where('id', $id)->update($data);
    }


}
