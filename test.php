<?php
$a = '{\"front_token\":\"748eadf3c9d24127eabfe63dd5a5cef1\",\"front_id\":4,\"front_time\":1610182754}';
$e = '{\"front_token\":\"748eadf3c9d24127eabfe63dd5a5cef1\",\"front_id\":4,\"front_time\":1610182754}';
//$b = ['front_token' => '748eadf3c9d24127eabfe63dd5a5cef1', 'front_id' => 4, 'front_time' => 1610182754];
//var_dump(json_encode($b));
var_dump(json_decode(stripslashes($e), true));