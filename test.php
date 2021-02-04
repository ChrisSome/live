<?php


$array = [
    'hot' => [],
    'C' => [
        ['short_name_zh' => 'CBA', 'logo' => 'https://cdn.sportnanoapi.com/basketball/competition/4bcdfa94d226fd5d7c740b463c182aa0.jpg', 'competition_id' => 3],

    ],
    'N' => [
        ['short_name_zh' => 'NBA', 'logo' => 'https://cdn.sportnanoapi.com/basketball/competition/aa6ac10ab514aba38a86c57d34e64f31.jpg', 'competition_id' => 1],
        ['short_name_zh' => 'NBL', 'logo' => 'https://cdn.sportnanoapi.com/basketball/competition/697d591130d4536044eeb4b45ce225cd.png', 'competition_id' => 4],
    ],
];
var_dump(json_encode($array));