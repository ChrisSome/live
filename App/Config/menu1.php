<?php

//后台menu设置成动态
return [
    'notice' => [
        'name' => '通知信息',
        'list' => [
            'notice.privacy' => [
                'name' => '通知信息',
                'fa' => 'fa-user',
                'menu' => [
                    ['url' => '/setting/privacy', 'rule' => 'setting.privacy', 'name' => '隐私与协议'],
                    ['url' => '/setting/problem', 'rule' => 'setting.problem', 'name' => '问题管理'],
                    ['url' => '/setting/notice', 'rule' => 'setting.notice', 'name' => '公告管理'],
                    ['url' => '/setting/sensitive', 'rule' => 'setting.sensitive', 'name' => '敏感词管理'],
                ]
            ],
//            'notice.problem' => [
//                'name' => '问题反馈',
//                'fa' => 'fa-users',
//                'menu' => [
//                    ['url' => '/setting/problem', 'rule' => 'setting.problem', 'name' => '问题反馈'],
//                    ['url' => '/role/add', 'rule' => 'auth.role.add', 'name' => '添加角色'],
//                ]
//            ],
//            'auth.rule' => [
//                'name' => '权限管理',
//                'fa' => 'fa-key fa-fw',
//                'menu' => [
//                    ['url' => '/rule', 'rule' => 'auth.rule.view', 'name' => '权限列表'],
//                    ['url' => '/rule/add', 'rule' => 'auth.rule.add', 'name' => '添加权限'],
//                ]
//            ]
        ]
    ],
];