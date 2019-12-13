<?php

namespace Quazardous\Daemon\Tests;

use Quazardous\Daemon\Daemon;
use Quazardous\Daemon\Process;
use Psr\Log\LogLevel;

class TestDaemon extends Daemon
{
    protected $loopInterval = 3;

    protected $tests = [];
    protected $callbacks = [];
    public function __construct(array $tests, array $callbacks, $maxByGroup)
    {
        $this->logger = new Logger();
        $this->tests = $tests;
        $this->callbacks = $callbacks;
        foreach ($maxByGroup as $group => $max) {
            $this->maxProcessesByGroup[$group] = $max;
        }
    }
    
    protected $started = false;
    protected function do()
    {
        while (list(,$test) = each($this->tests)) {
            $process = $this->task(function () use ($test) {
                $sleep = rand(3, 5);
                $this->log(LogLevel::INFO, sprintf('Sleep %d...', $sleep));
                sleep($sleep);
                return $test[1];
            }, function (Process $child) use ($test) {
                call_user_func($this->callbacks[1], $child, $test[1]);
            }, $test[2] ?? 'test');
            call_user_func($this->callbacks[0], $process, $test[0]);
        }
        $this->started = true;
    }
    
    public function isRunning()
    {
        if (!$this->started) return true;
        return count($this->children) > 0;
    }
}