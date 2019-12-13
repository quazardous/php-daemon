<?php

namespace Quazardous\Daemon;

/**
 * Base class handling a child process in the parent context.
 * This implementation uses pcntl functions to execute the given task in a child process.
 */
class Process implements ProcessInterface
{
    protected $task;
    protected $group;
    public function __construct($group, callable $task)
    {
        $this->group = $group;
        $this->task = $task;
    }
    
    /**
     * 
     * @return callable|TaskInterface
     */
    public function getTask()
    {
        return $this->task;
    }
    
    public function getGroup()
    {
        return $this->group;
    }
    
    protected $pid;
    public function getPid()
    {
        return $this->pid;
    }
    
    protected $running;
    public function execute()
    {
        $this->triggerEvent(self::EVENT_EXECUTE);
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new \RuntimeException('Cannot fork()');
        }
        if (0 === $pid) {
            // child
            $this->triggerEvent(self::EVENT_FORK_CHILD);
            $code = call_user_func($this->task);
            // the child exits with a code and terminate the process
            die($code);
        }
        $this->triggerEvent(self::EVENT_FORK_PARENT);
        // parent
        $this->running = true;
        return $this->pid = $pid;
    }
    
    protected $infos = [];
    public function checkRunning($wait = false)
    {
        $ops = WUNTRACED;
        if (!$wait) {
            $ops = $ops | WNOHANG;
        }
        $status = null;
        $ret = pcntl_waitpid($this->getPid(), $status, $ops);
        if ($ret === 0) {
            // with WNOHANG, 0 means process is still running
            return $this->running;
        }
        
        $this->running = false;
        $this->infos = [];
        if (pcntl_wifexited($status)) {
            $this->infos['exitstatus'] = pcntl_wexitstatus($status);
        }
        if (pcntl_wifsignaled($status)) {
            $this->infos['termsig'] = pcntl_wtermsig($status);
        }
        if (pcntl_wifstopped($status)) {
            $this->infos['stopsig'] = pcntl_wstopsig($status);
        }

        $this->triggerEvent(self::EVENT_TERMINATE);
        return $this->running;
    }
    
    public function wait()
    {
        return $this->checkRunning(true);
    }
    
    public function getInfo($what = null)
    {
        if ($what) {
            if (isset($this->infos[$what])) {
                return $this->infos[$what];
            }
            return null;
        }
        return $this->infos;
    }
    
    public function getExitStatus()
    {
        return $this->getInfo('exitstatus');
    }
    public function getTermSig()
    {
        return $this->getInfo('termsig');
    }
    public function getStopSig()
    {
        return $this->getInfo('stopsig');
    }
    
    public function isRunning()
    {
        return $this->running;
    }
    
    // parent context, just before fork
    public const EVENT_EXECUTE = 'execute';
    // parent context, just after fork
    public const EVENT_FORK_PARENT = 'fork-parent';
    // child context, just after fork
    public const EVENT_FORK_CHILD = 'fork-child';
    // parent context, when child has terminated
    public const EVENT_TERMINATE = 'terminate';
    // parent context, after removing process reference from children array
    public const EVENT_CLEANUP = 'cleanup';
    
    protected $events = [];
    public function addEvent($type, callable $callback)
    {
        $this->events[$type][] = $callback;
    }
    
    /**
     * 
     * @param string $type
     */
    public function triggerEvent($type)
    {
        if (empty($this->events[$type])) return;
        foreach ($this->events[$type] as $callback) {
            call_user_func($callback, $this);
        }
    }
}