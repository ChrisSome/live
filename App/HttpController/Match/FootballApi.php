<?php


/**
 *                             _ooOoo_
 *                            o8888888o
 *                            88" . "88
 *                            (| -_- |)
 *                            O\  =  /O
 *                         ____/`---'\____
 *                       .'  \\|     |//  `.
 *                      /  \\|||  :  |||//  \
 *                     /  _||||| -:- |||||-  \
 *                     |   | \\\  -  /// |   |
 *                     | \_|  ''\---/''  |   |
 *                     \  .-\__  `-`  ___/-. /
 *                   ___`. .'  /--.--\  `. . __
 *                ."" '<  `.___\_<|>_/___.'  >'"".
 *               | | :  `- \`.;`\ _ /`;.`/ - ` : | |
 *               \  \ `-.   \_ __\ /__ _/   .-` /  /
 *          ======`-.____`-.___\_____/___.-`____.-'======
 *                             `=---='
 *          ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 *                     佛祖保佑        永无BUG
 */
namespace App\HttpController\Match;
use App\Base\FrontUserController;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\AdminClashHistory;
use App\Model\AdminCompetition;
use App\Model\AdminMatch;
use App\Model\AdminMatchTlive;
use App\Model\AdminSysSettings;
use App\Model\AdminUser;
use App\Model\AdminUserInterestCompetition;
use App\Model\SeasonAllTableDetail;
use App\Model\SeasonMatchList;
use App\Model\SignalMatchLineUp;
use App\Storage\OnlineUser;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use App\Model\AdminInterestMatches;
use EasySwoole\HttpAnnotation\AnnotationController;
use EasySwoole\HttpAnnotation\AnnotationTag\Api;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\Method;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiSuccess;

class FootballApi extends FrontUserController
{
    protected $lineUpDetail = 'https://open.sportnanoapi.com/api/v4/football/match/lineup/detail?user=%s&secret=%s&id=%s';
    protected $urlIntvalRank = 'https://open.sportnanoapi.com/api/v4/football/season/table/detail?user=%s&secret=%s&id=%s';
    protected $matchHistory = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s';



    protected $playerLogo = 'http://cdn.sportnanoapi.com/football/player/';
    public $needCheckToken = false;

    const STATUS_PLAYING = [2, 3, 4, 5, 7];
    const STATUS_SCHEDULE = [0, 1, 9];
    const STATUS_RESULT= [8, 9, 10, 11, 12, 13];

    const STATUS_NO_START = 1;
    const hotCompetition = [
        'hot' => [['competition_id' => 45, 'short_name_zh' => '欧洲杯'],
            ['competition_id'=>47, 'short_name_zh' =>'欧联杯'],
            ['competition_id'=>542, 'short_name_zh' =>'中超']],
        'A' => [
            ['competition_id' => 595, 'short_name_zh' => '澳南超'],
            ['competition_id' => 600, 'short_name_zh' => '澳南甲'],
            ['competition_id' => 1689, 'short_name_zh' => '阿尔U21'],
            ['competition_id' => 1858, 'short_name_zh' => '澳昆U20'],
            ['competition_id' => 1850, 'short_name_zh' => '澳南后备'],
        ],
        'B' => [
            ['competition_id' => 3007, 'short_name_zh' => '巴基挑杯'],
            ['competition_id' => 282, 'short_name_zh' => '冰岛乙'],
            ['competition_id' => 284, 'short_name_zh' => '冰岛杯'],
            ['competition_id' => 436, 'short_name_zh' => '巴西乙'],
            ['competition_id' => 1821, 'short_name_zh' => '巴丙'],
        ],
        'D' => [
            ['competition_id' => 1675, 'short_name_zh' => '德堡州联'],
            ['competition_id' => 132, 'short_name_zh' => '德地区北'],
        ],
        'E' => [
            ['competition_id' => 238, 'short_name_zh' => '俄超'],
            ['competition_id' => 241, 'short_name_zh' => '俄青联'],
            ['competition_id' => 240, 'short_name_zh' => '俄乙'],

        ],
        'F' => [
            ['competition_id' => 195, 'short_name_zh' => '芬超'],
            ['competition_id' => 1940, 'short_name_zh' => '芬丙'],
            ['competition_id' => 3053, 'short_name_zh' => '斐济女联'],
            ['competition_id' => 1932, 'short_name_zh' => '斐济杯'],
        ],
        'G' => [
            ['competition_id' => 486, 'short_name_zh' => '哥斯甲'],
            ['competition_id' => 385, 'short_name_zh' => '格鲁甲'],
            ['competition_id' => 386, 'short_name_zh' => '格鲁乙'],
        ],
        'H' => [
            ['competition_id' => 356, 'short_name_zh' => '哈萨超'],
            ['competition_id' => 357, 'short_name_zh' => '哈萨甲'],
        ],
        'J' => [
            ['competition_id' => 2984, 'short_name_zh' => '加拿职'],
        ],
        'K' => [
            ['competition_id' => 1785, 'short_name_zh' => '卡塔乙'],
        ],
        'L' => [
            ['competition_id' => 271, 'short_name_zh' => '罗乙'],
        ],
        'M' => [
            ['competition_id' => 465, 'short_name_zh' => '墨西超'],
            ['competition_id' => 466, 'short_name_zh' => '墨西乙'],
            ['competition_id' => 2115, 'short_name_zh' => '墨女超'],
        ],
        'N' => [
            ['competition_id' => 716, 'short_name_zh' => '南非超'],
            ['competition_id' => 203, 'short_name_zh' => '挪乙'],
        ],
        'O' => [
            ['competition_id' => 53, 'short_name_zh' => '欧青U19'],
        ],
        'Q' => [
            ['competition_id' => 24, 'short_name_zh' => '球会友谊'],
        ],
        'R' => [
            ['competition_id' => 568, 'short_name_zh' => '日职乙'],
            ['competition_id' => 569, 'short_name_zh' => '日足联'],
            ['competition_id' => 572, 'short_name_zh' => '日职丙'],
            ['competition_id' => 567, 'short_name_zh' => '日职联'],
        ],
        'S' => [
            ['competition_id' => 616, 'short_name_zh' => '沙特乙'],
            ['competition_id' => 615, 'short_name_zh' => '沙特甲'],
            ['competition_id' => 3164, 'short_name_zh' => '所罗岛杯'],
        ],
        'T' => [
            ['competition_id' => 1842, 'short_name_zh' => '泰乙'],
            ['competition_id' => 317, 'short_name_zh' => '土乙红'],
            ['competition_id' => 318, 'short_name_zh' => '土丙C'],
        ],
        'W' => [
            ['competition_id' => 674, 'short_name_zh' => '乌兹超'],
            ['competition_id' => 675, 'short_name_zh' => '乌兹甲'],
            ['competition_id' => 1736, 'short_name_zh' => '乌拉乙'],
        ],
        'X' => [
            ['competition_id' => 547, 'short_name_zh' => '香港甲'],
            ['competition_id' => 1732, 'short_name_zh' => '香港乙'],
        ],
        'Y' => [
            ['competition_id' => 349, 'short_name_zh' => '以乙北'],
            ['competition_id' => 491, 'short_name_zh' => '亚冠杯'],
        ],
        'Z' => [
            ['competition_id' => 543, 'short_name_zh' => '中甲'],
            ['competition_id' => 544, 'short_name_zh' => '中乙'],
        ],
    ];


    /**
     * 赛事列表
     * @Api(name="赛事列表",path="/api/footBall/competitionList",version="3.0")
     * @ApiDescription(value="serverClient for getCompetition")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "hot": [
        {
        "competition_id": 82,
        "short_name_zh": "英超",
        "is_notice": false
        }
        ]
        }
        })
     */
    public function getCompetition()
    {
        $recommend = [];
        if ($arr = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::RECOMMEND_COM)->get()) {
            $recommend = json_decode($arr->sys_value, true);
        }
        $uid = isset($this->auth['id']) ? $this->auth['id'] : 0;
        $user_interest = [];
        if ($res = AdminUserInterestCompetition::getInstance()->where('user_id', $uid)->get()) {
            $user_interest = json_decode($res['competition_ids'], true);
        }
        foreach ($recommend as $k => $items) {
            foreach ($items as $sk => $item) {
                if (in_array($item['competition_id'], $user_interest)) {
                    $recommend[$k][$sk]['is_notice'] = true;
                } else {
                    $recommend[$k][$sk]['is_notice'] = false;

                }
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $recommend);


    }

    /**
     * 进行中比赛列表
     * @Api(name="进行中比赛列表",path="/api/footBall/matchListPlaying",version="3.0")
     * @ApiDescription(value="serverClient for matchListPlaying")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "帕德博恩",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/90ef22ec8a0a605bba948378f870633a.png",
        "away_team_name": "奥厄",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/e12165981407f62a9616b5467292ef2f.png",
        "round": "",
        "competition_id": 130,
        "competition_name": "德乙",
        "competition_color": "#919292",
        "match_time": "20:30",
        "format_match_time": "2021-01-10 20:30",
        "user_num": 0,
        "match_id": 3418640,
        "is_start": true,
        "status_id": 2,
        "is_interest": false,
        "neutral": 0,
        "matching_time": "6",
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[1,1,0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ],
        "count": 10
        }
        })
     */
    public function matchListPlaying()
    {
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;

        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
        $response = ['list' => [], 'user_interest_count' => count($interestMatchArr)];
        if (!$selectCompetitionIdArr)   return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);

        $playingMatch = AdminMatch::create()->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('status_id', self::STATUS_PLAYING, 'in')
            ->order('match_time', 'ASC')
            ->all();

        $formatMatch = FrontService::formatMatchThree($playingMatch, $uid, $interestMatchArr);

        $return = ['list' => $formatMatch, 'user_interest_count' => count($interestMatchArr)];

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);
    }

    public function matchSchedule()
    {
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
        if (!$selectCompetitionIdArr) {
            $response = ['list' => [], 'count' => []];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);
        }
        $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
        $limit = isset($this->params['size']) ? (int)$this->params['size'] : 20;
        if ($this->params['time'] == date('Y-m-d')) {
            $is_today = true;
        } else {
            $is_today = false;
        }
        $start = strtotime($this->params['time']);
        $end = $start + 60 * 60 * 24;
        $model = AdminMatch::getInstance()->where('status_id', self::STATUS_SCHEDULE, 'in')
            ->where('match_time', $is_today ? time() : $start, '>=')->where('match_time', $end, '<')
            ->where('is_delete', 0)
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->order('match_time', 'ASC')->limit(($page - 1) * $limit, $limit)->withTotalCount();
        $list = $model->all(null);
        $total = $model->lastQueryResult()->getTotalCount();
        $formatMatch = FrontService::formatMatchThree($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }


    /**
     * time 2020-08-19
     * 赛果列表
     * @return bool
     */
    public function matchResult()
    {

        if (!isset($this->params['time'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);
        }
        $uid = isset($this->auth['id']) ? (int)$this->auth['id'] : 0;
        //需要展示的赛事id 以及用户关注的比赛
        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($uid);
        if (!$selectCompetitionIdArr) {
            $response = ['list' => [], 'total' => 0];
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);
        }
        $page = isset($this->params['page']) ? (int)$this->params['page'] : 1;
        $size = isset($this->params['size']) ? (int)$this->params['size'] : 20;
        $start = strtotime($this->params['time']);
        $end = $start + 60 * 60 * 24;
        $matches = AdminMatch::getInstance()
            ->where('match_time', $start, '>=')
            ->where('match_time', $end, '<')
            ->where('status_id', self::STATUS_RESULT, 'in')
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('is_delete', 0)
            ->order('match_time', 'DESC')->getLimit($page, $size);
        $list = $matches->all();
        $total = $matches->lastQueryResult()->getTotalCount();

        $formatMatch = FrontService::formatMatchThree($list, $uid, $interestMatchArr);
        $return = ['list' => $formatMatch, 'count' => $total];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);


    }



    /**
     * 关注的比赛列表
     * @Api(name="关注的比赛列表",path="/api/footBall/userInterestMatchList",version="3.0")
     * @ApiDescription(value="serverClient for userInterestMatchList")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "姆贝亚市",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/8dce5b6fcb73b07c8cbf0d7e8562a61d.png",
        "away_team_name": "基农多尼",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/6412312cc0a3796583d8e3ae993a67a3.png",
        "round": "",
        "competition_id": 1796,
        "competition_name": "坦桑超",
        "competition_color": "#dd2431",
        "match_time": "21:00",
        "format_match_time": "2021-01-02 21:00",
        "user_num": 0,
        "match_id": 3490466,
        "is_start": false,
        "status_id": 13,
        "is_interest": true,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0,0,0]",
        "away_scores": "[0,0,0,0,0,0,0]",
        "steamLink": ""
        }
        ],
        "count": 50
        }
        })
     */
    public function userInterestMatchList()
    {
        if (!$this->auth['id']) {
            return $this->writeJson(Status::CODE_VERIFY_ERR, '登陆令牌缺失或者已过期');

        }
        $res = AdminInterestMatches::getInstance()->where('uid', $this->auth['id'])->get();
        $matchIds = json_decode($res->match_ids, true);
        if (!$matchIds) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);

        $matches = AdminMatch::getInstance()->where('match_id', $matchIds, 'in')->order('match_time', 'ASC')->all();
        $data = FrontService::formatMatchThree($matches, $this->auth['id'], $matchIds);
        $count = count($data);
        $response = ['list' => $data, 'count' => $count];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $response);


    }

    /**
     * 单场比赛阵容
     * @Api(name="单场比赛阵容",path="/api/footBall/lineUpDetail",version="3.0")
     * @ApiDescription(value="serverClient for lineUpDetail")
     * @Method(allow="{GET}")
     * @Param(name="match_id",type="int",required="",description="比赛id")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "home": {
        "firstPlayers": [
        {
        "player_id": 1294609,
        "name": "卢卡·杰梅洛",
        "logo": "",
        "position": "G",
        "shirt_number": 1
        },
        {
        "player_id": 32816,
        "name": "马可·安吉莱瑞",
        "logo": "",
        "position": "",
        "shirt_number": 7
        },
        {
        "player_id": 25917,
        "name": "洛丽丝·达蒙特",
        "logo": "",
        "position": "",
        "shirt_number": 21
        },
        {
        "player_id": 34348,
        "name": "马塞洛·波森蒂",
        "logo": "",
        "position": "",
        "shirt_number": 24
        },
        {
        "player_id": 51273,
        "name": "大卫·古列尔莫蒂",
        "logo": "",
        "position": "",
        "shirt_number": 27
        },
        {
        "player_id": 1008763,
        "name": "罗伯托·拉涅利",
        "logo": "",
        "position": "",
        "shirt_number": 4
        },
        {
        "player_id": 1110219,
        "name": "阿尔芒·拉达",
        "logo": "",
        "position": "",
        "shirt_number": 20
        },
        {
        "player_id": 1294611,
        "name": "安东尼奥·埃斯波西托",
        "logo": "",
        "position": "",
        "shirt_number": 3
        },
        {
        "player_id": 32905,
        "name": "朱塞佩·乔文科",
        "logo": "",
        "position": "",
        "shirt_number": 10
        },
        {
        "player_id": 42444,
        "name": "弗朗西斯科·加鲁皮尼",
        "logo": "",
        "position": "",
        "shirt_number": 14
        },
        {
        "player_id": 1299436,
        "name": "汤米·麦斯特罗",
        "logo": "",
        "position": "",
        "shirt_number": 5
        }
        ],
        "alternatePlayers": [
        {
        "player_id": 1294603,
        "name": "法布里兹·巴赫里亚",
        "logo": "",
        "position": "",
        "shirt_number": 12
        },
        {
        "player_id": 1294523,
        "name": "里卡多·布尔吉奥",
        "logo": "",
        "position": "",
        "shirt_number": 23
        },
        {
        "player_id": 1294607,
        "name": "卡米拉·瑟纳",
        "logo": "",
        "position": "",
        "shirt_number": 25
        },
        {
        "player_id": 1294604,
        "name": "埃拉尔德·拉克蒂",
        "logo": "",
        "position": "",
        "shirt_number": 19
        },
        {
        "player_id": 31867,
        "name": "安东尼奥·马格利",
        "logo": "",
        "position": "",
        "shirt_number": 16
        },
        {
        "player_id": 1138298,
        "name": " 安德里亚·马拉菲尼",
        "logo": "",
        "position": "",
        "shirt_number": 6
        },
        {
        "player_id": 1295666,
        "name": "弗朗西斯·马拉诺",
        "logo": "",
        "position": "",
        "shirt_number": 30
        },
        {
        "player_id": 1294610,
        "name": "托马索·梅雷蒂",
        "logo": "",
        "position": "",
        "shirt_number": 2
        },
        {
        "player_id": 1299435,
        "name": "里卡多·桑托维托",
        "logo": "",
        "position": "",
        "shirt_number": 13
        },
        {
        "player_id": 1301391,
        "name": "雅各布·席尔瓦",
        "logo": "",
        "position": "",
        "shirt_number": 28
        },
        {
        "player_id": 1025865,
        "name": "洛伦佐·索伦蒂诺",
        "logo": "",
        "position": "",
        "shirt_number": 11
        }
        ],
        "homeFormation": "3-4-1-2"
        },
        "away": {
        "firstPlayers": [
        {
        "player_id": 1295276,
        "name": "亚历山德罗·利维里",
        "logo": "",
        "position": "G",
        "shirt_number": 22
        },
        {
        "player_id": 1282559,
        "name": "洛伦佐·朱比拉托",
        "logo": "",
        "position": "",
        "shirt_number": 23
        },
        {
        "player_id": 28528,
        "name": "西蒙尼.佩拉里尼",
        "logo": "http://cdn.sportnanoapi.com/football/player/1f00a5eed58e3c2500229ee7f66e2956.png",
        "position": "",
        "shirt_number": 5
        },
        {
        "player_id": 1295278,
        "name": "安德烈·博斯科",
        "logo": "",
        "position": "",
        "shirt_number": 25
        },
        {
        "player_id": 1359043,
        "name": "大卫·马格纳拉",
        "logo": "",
        "position": "",
        "shirt_number": 34
        },
        {
        "player_id": 1282560,
        "name": "卢西亚诺·瓜迪",
        "logo": "",
        "position": "",
        "shirt_number": 8
        },
        {
        "player_id": 1282558,
        "name": "托马索·加托尼",
        "logo": "",
        "position": "",
        "shirt_number": 4
        },
        {
        "player_id": 1282556,
        "name": "亚历山德罗·蒙诺",
        "logo": "",
        "position": "",
        "shirt_number": 6
        },
        {
        "player_id": 1282561,
        "name": "卢卡·帕莱西",
        "logo": "",
        "position": "",
        "shirt_number": 7
        },
        {
        "player_id": 66422,
        "name": "克里斯蒂安·穆托",
        "logo": "http://cdn.sportnanoapi.com/football/player/0e00315c4f499445ab5c7254ffcc96c9.png",
        "position": "",
        "shirt_number": 18
        },
        {
        "player_id": 1299443,
        "name": "卢卡·卡波奇",
        "logo": "",
        "position": "",
        "shirt_number": 10
        }
        ],
        "alternatePlayers": [
        {
        "player_id": 1354649,
        "name": "费德里科·贝尔托利",
        "logo": "",
        "position": "",
        "shirt_number": 15
        },
        {
        "player_id": 1295280,
        "name": "克里斯蒂安·马尔蒂尼",
        "logo": "",
        "position": "",
        "shirt_number": 3
        },
        {
        "player_id": 1282557,
        "name": "米切尔·弗朗科",
        "logo": "",
        "position": "",
        "shirt_number": 32
        },
        {
        "player_id": 1299440,
        "name": "克劳迪奥·马菲",
        "logo": "",
        "position": "",
        "shirt_number": 28
        },
        {
        "player_id": 1299444,
        "name": "迈克尔·恩图贝",
        "logo": "",
        "position": "",
        "shirt_number": 14
        },
        {
        "player_id": 1299439,
        "name": "费德里科·马尔凯西",
        "logo": "",
        "position": "",
        "shirt_number": 29
        },
        {
        "player_id": 1305167,
        "name": "爱德华多迷迭香",
        "logo": "",
        "position": "",
        "shirt_number": 16
        },
        {
        "player_id": 1299438,
        "name": "加布里埃尔·莫塔",
        "logo": "",
        "position": "",
        "shirt_number": 17
        },
        {
        "player_id": 1282555,
        "name": "亚历山德罗·斯皮尼斯",
        "logo": "",
        "position": "",
        "shirt_number": 9
        },
        {
        "player_id": 1282553,
        "name": "费德里科·费拉特德尔",
        "logo": "",
        "position": "",
        "shirt_number": 1
        },
        {
        "player_id": 1295277,
        "name": "马蒂诺·科米内蒂",
        "logo": "",
        "position": "",
        "shirt_number": 11
        }
        ],
        "awayFormation": "4-3-3"
        }
        }
        })
     */
    public function lineUpDetail()
    {
        $match_id = $this->params['match_id'] ?: 0;
        if (!$match_id) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }

        $homeFirstPlayers = $homeAlternatePlayers = $awayFirstPlayers = $awayAlternatePlayers = [];

        if ($signalMatchLineUp = SignalMatchLineUp::getInstance()->where('match_id', $match_id)->get()) {
            $homeFormation = json_decode($signalMatchLineUp->home_formation, true);
            $awayFormation = json_decode($signalMatchLineUp->away_formation, true);
            $home = json_decode($signalMatchLineUp->home, true);
            $away = json_decode($signalMatchLineUp->away, true);
        } else {
            $res = Tool::getInstance()->postApi(sprintf($this->lineUpDetail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $match_id));
            $decode = json_decode($res, true);

            if ($decode['code'] == 0 && $decode['results']) {
                $homeFormation = $decode['results']['home_formation'];
                $awayFormation = $decode['results']['away_formation'];
                $home = $decode['results']['home'];
                $away = $decode['results']['away'];
                //入库
                $signalMatchLineUp = SignalMatchLineUp::create();
                $signalMatchLineUp->home_formation = json_encode($homeFormation);
                $signalMatchLineUp->away_formation = json_encode($awayFormation);
                $signalMatchLineUp->home = json_encode($home);
                $signalMatchLineUp->away = json_encode($away);
                $signalMatchLineUp->match_id = $match_id;
                $signalMatchLineUp->confirmed = $decode['results']['confirmed'];
                $signalMatchLineUp->save();
            } else {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
            }
        }

        if (!empty($home)) {
            foreach ($home as $homeItem)
            {
                $homePlayer['player_id'] = $homeItem['id'];
                $homePlayer['name'] = $homeItem['name'];
                $homePlayer['logo'] = isset($home['logo']) ? $this->playerLogo . $homeItem['logo'] : '';
                $homePlayer['position'] = $homeItem['position'];
                $homePlayer['shirt_number'] = $homeItem['shirt_number'];
                if ($homeItem['first']) {
                    $homeFirstPlayers[] = $homePlayer; //首发
                } else {
                    $homeAlternatePlayers[] = $homePlayer; //替补
                }
                unset($homePlayer);
            }
        }

        if (!empty($away)) {
            foreach ($away as $awayItem) {
                $awayPlayer['player_id'] = $awayItem['id'];
                $awayPlayer['name'] = $awayItem['name'];
                $awayPlayer['logo'] = $awayItem['logo'] ? $this->playerLogo . $awayItem['logo'] : '';
                $awayPlayer['position'] = $awayItem['position'];
                $awayPlayer['shirt_number'] = $awayItem['shirt_number'];
                if ($awayItem['first']) {
                    $awayFirstPlayers[] = $awayPlayer; //首发
                } else {
                    $awayAlternatePlayers[] = $awayPlayer; //替补
                }
                unset($awayPlayer);
            }
        }

        $homeTeamInfo['firstPlayers'] = $homeFirstPlayers;
        $homeTeamInfo['alternatePlayers'] = $homeAlternatePlayers;
        $homeTeamInfo['homeFormation'] = $homeFormation;

        $awayTeamInfo['firstPlayers'] = $awayFirstPlayers;
        $awayTeamInfo['alternatePlayers'] = $awayAlternatePlayers;
        $awayTeamInfo['awayFormation'] = $awayFormation;

        $data = [
            'home' => $homeTeamInfo,
            'away' => $awayTeamInfo,
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $data);


    }


    /**
     * 直播间数据
     * @Api(name="直播间数据",path="/api/footBall/getClashHistory",version="3.0")
     * @ApiDescription(value="serverClient for getClashHistory")
     * @Method(allow="{GET}")
     * @Param(name="match_id",type="string",required="",description="")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "intvalRank": {
        "homeIntvalRank": {
        "team_id": 29045,
        "promotion_id": 0,
        "points": 38,
        "position": 1,
        "deduct_points": 0,
        "note_zh": "",
        "total": 17,
        "won": 12,
        "draw": 2,
        "loss": 3,
        "goals": 30,
        "goals_against": 16,
        "goal_diff": 14,
        "home_points": 18,
        "home_position": 2,
        "home_total": 8,
        "home_won": 6,
        "home_draw": 0,
        "home_loss": 2,
        "home_goals": 15,
        "home_goals_against": 8,
        "home_goal_diff": 7,
        "away_points": 20,
        "away_position": 1,
        "away_total": 9,
        "away_won": 6,
        "away_draw": 2,
        "away_loss": 1,
        "away_goals": 15,
        "away_goals_against": 8,
        "away_goal_diff": 7
        },
        "awayIntvalRank": {
        "team_id": 19223,
        "promotion_id": 0,
        "points": 24,
        "position": 11,
        "deduct_points": 0,
        "note_zh": "",
        "total": 17,
        "won": 7,
        "draw": 3,
        "loss": 7,
        "goals": 17,
        "goals_against": 20,
        "goal_diff": -3,
        "home_points": 11,
        "home_position": 12,
        "home_total": 9,
        "home_won": 3,
        "home_draw": 2,
        "home_loss": 4,
        "home_goals": 8,
        "home_goals_against": 8,
        "home_goal_diff": 0,
        "away_points": 13,
        "away_position": 9,
        "away_total": 8,
        "away_won": 4,
        "away_draw": 1,
        "away_loss": 3,
        "away_goals": 9,
        "away_goals_against": 12,
        "away_goal_diff": -3
        }
        },
        "historyResult": [],
        "recentResult": {
        "home": {
        "won_count": 11,
        "drawn_count": 2,
        "lost_count": 2,
        "rate": 0.7333
        },
        "away": {
        "won_count": 7,
        "drawn_count": 3,
        "lost_count": 5,
        "rate": 0.4667
        }
        },
        "history": [],
        "homeRecent": [
        {
        "home_team_name": "尤文图斯U23",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/085230960ba9f851b635b50ffa128fee.png",
        "away_team_name": "AC雷纳特",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/b29879cf9c844a43dde4ffa08203308d.png",
        "round": "",
        "competition_id": 110,
        "competition_name": "意丙",
        "competition_color": "",
        "match_time": "22:00",
        "format_match_time": "2020-12-23 22:00",
        "user_num": 0,
        "match_id": 3457081,
        "is_start": false,
        "status_id": 8,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[1,0,1,2,6,0,0]",
        "away_scores": "[2,0,0,6,3,0,0]",
        "coverage": "",
        "steamLink": ""
        }        ],
        "awayRecent": [
        {
        "home_team_name": "普罗塞斯托",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/3bd4017318837e92a66298c7855f4427.jpg",
        "away_team_name": "格罗瑟托",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/970af30e481057c48f87e101b61e6994.jpg",
        "round": "",
        "competition_id": 110,
        "competition_name": "意丙",
        "competition_color": "",
        "match_time": "22:00",
        "format_match_time": "2020-12-23 22:00",
        "user_num": 0,
        "match_id": 3457087,
        "is_start": false,
        "status_id": 8,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[1,0,0,2,6,0,0]",
        "away_scores": "[2,1,0,2,2,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ],
        "homeRecentSchedule": [
        {
        "home_team_name": "利沃诺",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/80a20f75ca09f28f3c6871366dca3867.png",
        "away_team_name": "普罗塞斯托",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/3bd4017318837e92a66298c7855f4427.jpg",
        "round": "",
        "competition_id": 110,
        "competition_name": "意丙",
        "competition_color": "",
        "match_time": "21:00",
        "format_match_time": "2021-04-25 21:00",
        "user_num": 0,
        "match_id": 3457294,
        "is_start": false,
        "status_id": 1,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,-1,0,0]",
        "away_scores": "[0,0,0,0,-1,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ],
        "awayRecentSchedule": [
        {
        "home_team_name": "AC雷纳特",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/b29879cf9c844a43dde4ffa08203308d.png",
        "away_team_name": "卢捷斯",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/a424ed4bd3a7d6aea720b86d4a360f75.gif",
        "round": "",
        "competition_id": 110,
        "competition_name": "意丙",
        "competition_color": "",
        "match_time": "21:00",
        "format_match_time": "2021-04-25 21:00",
        "user_num": 0,
        "match_id": 3457298,
        "is_start": false,
        "status_id": 1,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,-1,0,0]",
        "away_scores": "[0,0,0,0,-1,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ]
        }
        })
     */
    public function getClashHistory()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $matchId = $this->params['match_id'];
        $sensus = AdminClashHistory::getInstance()->where('match_id', $this->params['match_id'])->get();



        if (!$matchInfo = SeasonMatchList::getInstance()->where('match_id', $matchId)->where('is_delete', 0)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


        //积分排名
        $currentSeasonId = $matchInfo->competitionName()->cur_season_id;

        if (!$currentSeasonId) {
            $intvalRank = ['homeIntvalRank' => null, 'awayIntvalRank' => null];

        } else {
            $res = SeasonAllTableDetail::getInstance()->where('season_id', $currentSeasonId)->get();
            $decode = isset($res->tables) ? json_decode($res->tables, true) : [];
            $promotions = isset($res->promotions) ? json_decode($res->promotions, true) : [];

            $homeIntvalRank = $awayIntvalRank = null;
            if ($promotions) {
                $rows = isset($decode[0]['rows']) ? $decode[0]['rows'] : [];
                foreach ($rows as $item) {
                    if ($item['team_id'] == $matchInfo->home_team_id) {
                        $homeIntvalRank = $item;
                    } else if ($item['team_id'] == $matchInfo->away_team_id) {
                        $awayIntvalRank = $item;
                    }
                    if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) break;
                }

            } else {

                foreach ($decode as $item_row) {
                    foreach ($item_row['rows'] as $k_row) {
                        $team_ids[] = $k_row['team_id'];
                        if ($k_row['team_id'] == $matchInfo->home_team_id) {
                            $homeIntvalRank = $k_row;
                        }

                        if ($k_row['team_id'] == $matchInfo->away_team_id) {
                            $awayIntvalRank = $k_row;
                        }

                        if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) {

                            break;
                        }
                    }
                }
            }

            $intvalRank = ['homeIntvalRank' => $homeIntvalRank, 'awayIntvalRank' => $awayIntvalRank];

        }

        $homeTid = $matchInfo->home_team_id;
        $awayTid = $matchInfo->away_team_id;


        //历史交锋 与 近期战绩
        $match = SeasonMatchList::create()->where('status_id', 8)->where('(home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid . ')')
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();
        $formatHistoryMatches = $homeRecentMatches = $awayRecentMatches = [];
        foreach ($match as $itemMatch) {
            if (($itemMatch['home_team_id'] == $homeTid && $itemMatch['away_team_id'] == $awayTid) || ($itemMatch['home_team_id'] == $awayTid && $itemMatch['away_team_id'] == $homeTid)) {
                $formatHistoryMatches[] = $itemMatch;
            }

            if ($itemMatch['home_team_id'] == $homeTid || $itemMatch['away_team_id'] == $homeTid) {
                $homeRecentMatches[] = $itemMatch;
            }

            if ($itemMatch['home_team_id'] == $awayTid || $itemMatch['away_team_id'] == $awayTid) {
                $awayRecentMatches[] = $itemMatch;
            }
        }
        $homeRecentSchedule = $awayRecentSchedule = [];
        //近期赛程
        $statusArr = array_merge(self::STATUS_SCHEDULE, self::STATUS_PLAYING);
        $matchSchedule = SeasonMatchList::create()->where('status_id', $statusArr, 'in')
            ->where('(home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid . ')')
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();

        foreach ($matchSchedule as $scheduleItem) {
            if ($scheduleItem['home_team_id'] == $homeTid || $scheduleItem['away_team_id'] == $awayTid) {
                $homeRecentSchedule[] = $scheduleItem;
            }
            if ($scheduleItem['home_team_id'] == $awayTid || $scheduleItem['home_team_id'] == $homeTid) {
                $awayRecentSchedule[] = $scheduleItem;
            }
        }


        $returnData = [
            'intvalRank' => $intvalRank, //积分排名
            'historyResult' => !empty($sensus['history']) ? json_decode($sensus['history'], true) : [],//历史战绩
            'recentResult' => !empty($sensus['recent']) ? json_decode($sensus['recent'], true) : [],
            'history' => FrontService::formatMatchThree(array_slice($formatHistoryMatches, 0, 10), 0, []),//历史交锋
            'homeRecent' => FrontService::formatMatchThree(array_slice($homeRecentMatches, 0, 10), 0, []),//主队近期战绩
            'awayRecent' => FrontService::formatMatchThree(array_slice($awayRecentMatches, 0, 10), 0, []),//客队近期战绩
            'homeRecentSchedule' => FrontService::formatMatchThree(array_slice($homeRecentSchedule, 0, 10), 0, []),//主队近期赛程
            'awayRecentSchedule' => FrontService::formatMatchThree(array_slice($awayRecentSchedule, 0, 10), 0, []),//客队近期赛程
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }

    public function getClashHistoryBak()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }
        $matchId = $this->params['match_id'];
        $sensus = AdminClashHistory::getInstance()->where('match_id', $this->params['match_id'])->get();



        if (!$matchInfo = SeasonMatchList::getInstance()->where('match_id', $matchId)->where('is_delete', 0)->get()) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        }


        //积分排名
        $currentSeasonId = $matchInfo->competitionName()->cur_season_id;

        if (!$currentSeasonId) {
            $intvalRank = [];
        } else {
            $res = SeasonAllTableDetail::getInstance()->where('season_id', $currentSeasonId)->get();

            $decode = json_decode($res->tables, true);
            $promotions = json_decode($res->promotions, true);

            $homeIntvalRank = $awayIntvalRank = [];
            if ($promotions) {
                $rows = isset($decode[0]['rows']) ? $decode[0]['rows'] : [];
                foreach ($rows as $item) {
                    if ($item['team_id'] == $matchInfo->home_team_id) {
                        $homeIntvalRank = $item;
                    } else if ($item['team_id'] == $matchInfo->away_team_id) {
                        $awayIntvalRank = $item;
                    }
                    if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) break;
                }

            } else {

                foreach ($decode as $item_row) {
                    foreach ($item_row['rows'] as $k_row) {
                        $team_ids[] = $k_row['team_id'];
                        if ($k_row['team_id'] == $matchInfo->home_team_id) {
                            $homeIntvalRank = $k_row;
                        }

                        if ($k_row['team_id'] == $matchInfo->away_team_id) {
                            $awayIntvalRank = $k_row;
                        }

                        if (!empty($homeIntvalRank) && !empty($awayIntvalRank)) {

                            break;
                        }
                    }
                }
            }

            $intvalRank = ['homeIntvalRank' => $homeIntvalRank, 'awayIntvalRank' => $awayIntvalRank];

        }


        $homeTid = $matchInfo->home_team_id;
        $awayTid = $matchInfo->away_team_id;

        //历史交锋
        $matches = SeasonMatchList::getInstance()->where('status_id', 8)->where('((home_team_id=' . $homeTid . ' and away_team_id=' . $awayTid . ') or (home_team_id='.$awayTid.' and away_team_id='.$homeTid.'))')
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();

        $formatHistoryMatches = FrontService::formatMatchThree($matches, 0, []);

        //近期战绩

        $homeRecentMatches = SeasonMatchList::getInstance()->where('status_id', 8)->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();
        $awayRecentMatches = SeasonMatchList::getInstance()->where('status_id', 8)->where('home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->limit(10)->all();

        //近期赛程
        $matchSchedule = SeasonMatchList::create()->where('status_id', self::STATUS_SCHEDULE, 'in')->where('home_team_id='.$homeTid. ' or away_team_id='.$homeTid . ' or home_team_id='.$awayTid. ' or away_team_id='.$awayTid)
            ->where('is_delete', 0)->order('match_time', 'DESC')->all();

        foreach ($matchSchedule as $scheduleItem) {
            if ($scheduleItem['home_team_id'] == $homeTid || $scheduleItem['away_team_id'] == $awayTid) {
                $homeRecentSchedule[] = $scheduleItem;
            }
            if ($scheduleItem['home_team_id'] == $awayTid || $scheduleItem['home_team_id'] == $homeTid) {
                $awayRecentSchedule[] = $scheduleItem;
            }
        }




        $returnData = [
            'intvalRank' => $intvalRank, //积分排名
            'historyResult' => !empty($sensus['history']) ? json_decode($sensus['history'], true) : [],
            'recentResult' => !empty($sensus['recent']) ? json_decode($sensus['recent'], true) : [],
            'history' => array_slice($formatHistoryMatches, 9), //历史交锋
            'homeRecent' => FrontService::formatMatchThree(array_slice($homeRecentMatches, 9), 0, []),//主队近期战绩
            'awayRecent' => FrontService::formatMatchThree(array_slice($awayRecentMatches, 9), 0, []),//客队近期战绩
            'homeRecentSchedule' => FrontService::formatMatchThree($homeRecentSchedule, 0, []),//主队近期赛程
            'awayRecentSchedule' => FrontService::formatMatchThree($awayRecentSchedule, 0, []),//客队近期赛程
        ];
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $returnData);

    }
    /**
     * 直播间公告
     * @return bool
     */
    public function noticeInMatch()
    {
        $setting = AdminSysSettings::getInstance()->where('sys_key', AdminSysSettings::SETTING_MATCH_NOTICEMENT)->get();

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], isset($setting->sys_value) ? $setting->sys_value : []);

    }

    /**
     * 进行今日比赛列表
     * @Api(name="今日比赛列表",path="/api/footBall/getTodayAllMatch",version="3.0")
     * @ApiDescription(value="serverClient for getTodayAllMatch")
     * @Method(allow="{GET}")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "list": [
        {
        "home_team_name": "圣胡安莫扎里法",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/579dcb8e758b9e5fd6386eac6f74c76e.png",
        "away_team_name": "特鲁埃尔",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/579bb1d9cafd92b8d307a8c6943dc0aa.jpg",
        "round": "",
        "competition_id": 1679,
        "competition_name": "西丁",
        "competition_color": "",
        "match_time": "00:00",
        "format_match_time": "2021-01-11 00:00",
        "user_num": 0,
        "match_id": 3491859,
        "is_start": false,
        "status_id": 8,
        "is_interest": false,
        "neutral": 0,
        "matching_time": 0,
        "matching_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[0,0,0,0,0,0,0]",
        "away_scores": "[3,0,0,0,0,0,0]",
        "coverage": "",
        "steamLink": ""
        }
        ],
        "count": 92,
        "user_interest_count": 50
        }
        })
     */
    public function getTodayAllMatch()
    {
        $start = strtotime(date('Y-m-d'));
        $end = $start + 60 * 60 * 24;
        $order = 'CASE WHEN `status_id`=7 Then 1 ';  //点球
        $order .= 'WHEN `status_id`=5 Then 2 '; //加时赛
        $order .= 'WHEN `status_id`=4 Then 3 '; //下半场
        $order .= 'WHEN `status_id`=3 Then 4 '; //中场
        $order .= 'WHEN `status_id`=2 Then 5 '; //上半场
        $order .= 'WHEN `status_id`=1 Then 6 '; //未开赛
        $order .= 'WHEN `status_id`=8 Then 7 '; //完场
        $order .= 'WHEN `status_id`=9 Then 8 '; //推迟
        $order .= 'WHEN `status_id`=10 Then 9 '; //终断
        $order .= 'WHEN `status_id`=11 Then 10 '; //腰斩
        $order .= 'WHEN `status_id`=12 Then 11 '; //取消
        $order .= 'WHEN `status_id`=13 Then 12 ELSE 0 END'; //取消

        $page = !empty($this->params['page']) ? intval($this->params['page']) : 1;
        $size = !empty($this->params['size']) ? (int)$this->params['size'] : 15;
        $userId = !empty($this->auth['id']) ? (int)$this->auth['id'] : 0;

        list($selectCompetitionIdArr, $interestMatchArr) = AdminUser::getUserShowCompetitionId($userId);

        $todayMatch = AdminMatch::getInstance()->where('match_time', $start, '>=')
            ->where('competition_id', $selectCompetitionIdArr, 'in')
            ->where('status_id', 0, '<>')
            ->where('is_delete', 0)
            ->where('match_time', $end, '<')->order($order, 'ASC')
            ->order('match_time', 'ASC')
            ->page($page, $size)->withTotalCount();
        $list = $todayMatch->all(null);
        $total = $todayMatch->lastQueryResult()->getTotalCount();
        $formatTodayMatch = FrontService::formatMatchThree($list, $userId, $interestMatchArr);
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], ['list' => $formatTodayMatch, 'count' => $total, 'user_interest_count' => count($interestMatchArr)]);


    }


    /**
     * 比赛详情
     * @Api(name="比赛详情",path="/api/footBall/matchInfo",version="3.0")
     * @ApiDescription(value="serverClient for getMatchInfo")
     * @Method(allow="{GET}")
     * @Param(name="match_id",type="int",required="",description="")
     * @ApiSuccess({
        "code": 0,
        "msg": "ok",
        "data": {
        "home_team_name": "AC雷纳特",
        "home_team_logo": "https://cdn.sportnanoapi.com/football/team/b29879cf9c844a43dde4ffa08203308d.png",
        "away_team_name": "普罗塞斯托",
        "away_team_logo": "https://cdn.sportnanoapi.com/football/team/3bd4017318837e92a66298c7855f4427.jpg",
        "round": "",
        "competition_id": 110,
        "competition_name": "意丙",
        "competition_color": "",
        "match_time": "19:30",
        "format_match_time": "2021-01-10 19:30",
        "user_num": 0,
        "match_id": 3457098,
        "is_start": true,
        "status_id": 4,
        "is_interest": false,
        "neutral": 0,
        "matching_time": "58",
        "match_info": null,
        "has_living": 0,
        "living_url": {
        "liveUrl": "",
        "liveUrl2": "",
        "liveUrl3": ""
        },
        "note": "",
        "home_scores": "[2,2,0,0,3,0,0]",
        "away_scores": "[0,0,0,2,0,0,0]",
        "coverage": "",
        "steamLink": "",
        "competition_type": 1
        }
        })
     */
    public function getMatchInfo()
    {
        if (!isset($this->params['match_id'])) {
            return $this->writeJson(Status::CODE_W_PARAM, Status::$msg[Status::CODE_W_PARAM]);

        } else if (!$match = AdminMatch::getInstance()->where('match_id', $this->params['match_id'])->get()) {

            return $this->writeJson(Status::CODE_WRONG_MATCH, Status::$msg[Status::CODE_WRONG_MATCH]);

        }
        //用户关注的比赛
        $userInterestMatchArr = [];
        if ($userInterestMatches = AdminInterestMatches::create()->where('uid', $this->auth['id'])->get()) {
            $userInterestMatchArr = json_decode($userInterestMatches->match_ids, true);
        }
        $formatMatch = FrontService::formatMatchThree([$match], $this->auth['id'], $userInterestMatchArr);
        if (!$return = $formatMatch[0]) {
            return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES]);

        }
        $competition_id = $return['competition_id'];
        $return['competition_type'] = 0;
        if ($competition = AdminCompetition::create()->where('competition_id', $competition_id)->get()) {
            $return['competition_type'] = $competition->type;
        }

        $matchId = $return['match_id'];
        if (!$return['matching_info']) {
            if ($matchTlive = AdminMatchTlive::getInstance()->where('match_id', $return['match_id'])->get()) { //已结束并且同步数据
                $tlive = json_decode($matchTlive->tlive, true);
                $stats = json_decode($matchTlive->stats, true);
                $score = json_decode($matchTlive->score, true);
                $match_trend = json_decode($matchTlive->match_trend, true);
                $goal_tlive = [];
                $corner_tlive = [];
                $yellow_card_tlive = [];
                $red_card_tlive = [];
                if ($tlive) {
                    foreach ($tlive as $item) {
                        $item['time'] = intval($item['time']);
                        if ($item['type'] == 1) { //进球
                            $goal_tlive[] = $item;
                        } else if ($item['type'] == 2) { //角球
                            $corner_tlive[] = $item;
                        } else if ($item['type'] == 3) { //黄牌
                            $yellow_card_tlive[] = $item;
                        } else if ($item['type'] == 4) { //红牌
                            $red_card_tlive[] = $item;
                        } else {
                            continue;
                        }

                        unset($item);
                    }
                }

                $matchStats = [];
                if ($stats) {
                    foreach ($stats as $stat) {
                        if ($stat['type'] == 21 || $stat['type'] == 22 || $stat['type'] == 23 || $stat['type'] == 24 ||  $stat['type'] == 25) {
                            $matchStats[] = $stat;
                        }
                    }
                }

                $match_info = [
                    'signal_count' => ['goal' => $goal_tlive, 'corner' => $corner_tlive, 'yellow_card' => $yellow_card_tlive, 'red_card' => $red_card_tlive],
                    'match_trend' => $match_trend,
                    'match_id' => $matchId,
                    'time' => 0,
                    'status' => 8,
                    'match_stats' => $matchStats,
                    'score' => ['home' => $score[2], 'away' => $score[3]],

                ];
            } else {
                $match_info = [];
            }
            $return['matching_info'] = $match_info;

        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $return);

    }


    public function getMatchHistory()
    {


        $url = sprintf($this->matchHistory, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', 3478550);

        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true)['results'];


        if (!$matchTlive = AdminMatchTlive::getInstance()->where('match_id', $resp['id'])->get()) {
            $data = [
                'match_id' => $resp['id'],
                'score' => json_encode($resp['score']),
                'stats' => json_encode($resp['stats']),
                'incidents' => json_encode($resp['incidents']),
                'tlive' => json_encode($resp['tlive']),
            ];
            AdminMatchTlive::getInstance()->insert($data);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $resp);

    }


    public function getOnlineUserCount()
    {
        if (!$onlineUsers = OnlineUser::getInstance()->table()) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], []);
        }
        $formatUsers = [];
        foreach ($onlineUsers as $onlineUser) {
            $item['user_id'] = $onlineUser['user_id'];
            $item['match_id'] = $onlineUser['match_id'];
            $item['fd'] = $onlineUser['fd'];
            $formatUsers[] = $item;
            unset($item);
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $formatUsers);

    }

    public function test()

    {
        $r = $this->request();
        $cookie = $r->getCookieParams();
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $cookie);


    }

}
