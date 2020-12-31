<?php

$a = [1,2,3,4,5];
$new_a = [1,2,3,4,5,6,7,8,9];
$count = count($a);
$new_tlive = array_slice($new_a, $count);
var_dump($new_tlive);