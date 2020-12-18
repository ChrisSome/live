<?php

//后台menu设置成动态
return [
//    'auth' => [
//        'name' => '管理员及权限',
//        'list' => [
//            'auth.auth' => [
//                'name' => '管理员列表',
//                'fa' => 'fa-user',
//                'menu' => [
//                    ['url' => '/auth', 'rule' => 'auth.auth.view', 'name' => '管理员列表'],
//                    ['url' => '/auth/add', 'rule' => 'auth.auth.add', 'name' => '添加管理员'],
//                ]
//            ],
//            'auth.role' => [
//                'name' => '角色管理',
//                'fa' => 'fa-users',
//                'menu' => [
//                    ['url' => '/role', 'rule' => 'auth.role.view', 'name' => '角色列表'],
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
//        ]
//    ],
    'user' => [
        'name' => '用户管理',
        'list' => [
            'user.user' => [
                'name' => '用户管理',
                'fa' => 'fa-user',
                'menu' => [
                    ['url' => '/user', 'rule' => 'auth.user', 'name' => '用户列表'],
                    ['url' => '/user/add', 'rule' => 'user.user.add', 'name' => '添加用户'],
                    ['url' => '/user/online', 'rule' => 'user.user.online', 'name' => '在线用户列表'],
                    ['url' => '/user/statistics', 'rule' => 'user.user.statistics', 'name' => '用户统计'],
                    ['url' => '/user/apply', 'rule' => 'user.user.apply', 'name' => '用户信息审核'],
                ]
            ]
        ]
    ],
    'talking' => [
        'name' => '聊天管理',
        'list' => [
            'talking.manage' => [
                'name' => '聊天管理',
                'fa' => 'fa-talking',
                'menu' => [
                    ['url' => '/content', 'rule' => 'auth.content', 'name' => '聊天内容列表'],
                ]
            ]
        ]
    ],
    'post' => [
        'name' => '社区管理',
        'list' => [
            'post.manage' => [
                'name' => '社区资讯管理',
                'fa' => 'fa-post',
                'menu' => [
                    ['url' => '/user/post', 'rule' => 'user.post', 'name' => '帖子列表'],
//                    ['url' => '/user/post/add', 'rule' => 'user.post.accusation', 'name' => '帖子'],
                    ['url' => '/user/post/examine', 'rule' => 'user.post.examine', 'name' => '帖子审核管理'],
                ]
            ]
        ]
    ],
    'core' => [
        'name' => '核心管理',
        'list' => [
            'core.play' => [
                'name' => '播放源管理',
                'fa' => 'fa-key fa-fw',
                'menu' => [
                    ['url' => '/core/play', 'rule' => 'auth.user', 'name' => '播放源列表'],
                    ['url' => '/core/play/add', 'rule' => 'user.user.add', 'name' => '添加播放源'],
                ]
            ],
            'core.match' => [
                'name' => '赛事管理',
                'fa' => 'fa-balance-scale',
                'menu' => [
                    ['url' => '/core/competition/manage', 'rule' => 'auth.user', 'name' => '赛事配置'],
                ]
            ]
        ]
    ],
    'setting' => [
        'name' => '系统设置',
        'list' => [
            'setting.sys' => [
                'name' => '配置设置',
                'fa' => 'fa-balance-scale',
                'menu' => [
                    ['url' => '/setting/sys', 'rule' => 'setting.sys', 'name' => '配置管理'],
                    ['url' => '/setting/user/option', 'rule' => 'setting.option', 'name' => '投诉管理'],
                ]
            ],
            'setting.content' => [
                'name' => '消息管理',
                'fa' => ' fa-arrows-alt',
                'menu' => [
                    ['url' => '/setting/category', 'rule' => 'setting.content.category', 'name' => '消息类型'],
                    ['url' => '/setting/message', 'rule' => 'setting.content.message', 'name' => '消息列表'],
                    ['url' => '/setting/phonecode', 'rule' => 'setting.content.phonecode', 'name' => '短信列表'],
                ]
            ]
        ]
    ]
];