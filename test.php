<?php

$a = '[
        {
            "item_id": 5,
            "type": 1
        },
        {
            "item_id": 20,
            "type": 1
        },
        {
            "item_id": 22,
            "type": 1
        },
        {
            "item_id": 157,
            "type": 1
        },
        {
            "item_id": 85,
            "type": 1
        },
        {
            "item_id": 169,
            "type": 1
        },
        {
            "item_id": 168,
            "type": 1
        },
        {
            "item_id": 43,
            "type": 1
        },
        {
            "item_id": 157,
            "type": 2
        },
        {
            "item_id": 63,
            "type": 2
        }
    ]';
$decode = json_decode($a, true);
$decode = array_values($decode);
$t = ['item' => 63, 'type' => 2];
foreach ($decode as $item) {
    if ($item == $t) {
        echo 1;
    }
}