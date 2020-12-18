<?php
namespace App\Task;

class MatchTask
{
    protected $taskData;
    public function __construct($taskData)
    {
        $this->taskData = $taskData;
    }

    function run()
    {

    }
}