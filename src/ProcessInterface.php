<?php

namespace Quazardous\Daemon;

/**
 * Handle a process in the daemon context.
 */
interface ProcessInterface
{
    public function getGroup();

    public function getPid();

    public function isRunning();
    
    public function checkRunning($wait = false);
    
    public function getExitStatus();
    
    public function getTermSig();
    
    public function getStopSig();
    
    public function addEvent($type, callable $callback);

    public function triggerEvent($type);
    
    public function getInfo($what = null);
    
    public function wait();
    
    public function execute();
    
}