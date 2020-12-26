<?php

$a = '{"params":{"content":"111","roomId":1,"type":"text","at_user_id":0}, "event":"broadcast-roomBroadcast"}
{"event":"broadcast-roomBroadcast","params":{"match_id":1,"content":"888","sender_user_id":41,"type":"text","at_user_id":2}}';
var_dump(json_decode($a, true));
