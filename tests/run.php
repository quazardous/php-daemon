<?php

use Quazardous\Daemon\Tests\TestDaemon;
use Quazardous\Daemon\Process;

include_once __DIR__ . '/bootstrap.php';

$daemon = new TestDaemon([
    /*[create, return]*/
    [true, 5],
    [true, 6],
    [true, 9],
    [false, -1],
], [
    // create callback
    function ($process, $expected) {
        if ($process)
            echo sprintf("Child was created (expected=%d)\n", $expected);
        else
            echo sprintf("Child was NOT created (expected=%d)\n", $expected);
    },
    // return callback
    function (Process $child, $expected) {
        echo sprintf("END: %d returned %d (expected was %d)\n", $child->getPid(), $child->getExitStatus(), $expected);
    },
],
['test' => 3]);
    
$daemon->run();