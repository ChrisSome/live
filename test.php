<?php

//player_id 球员id，point 得分 steal抢断  要求抢断榜(保留player_id  steal)，得分榜(保留player_id steal)
$arr = [
    ['player_id'=>1,'point'=>'2','steal'=>10],
    ['player_id'=>2,'point'=>'5','steal'=>12],
    ['player_id'=>3,'point'=>'30','steal'=>4],
    //........
];



print_r(array_column($arr, 'sex'));