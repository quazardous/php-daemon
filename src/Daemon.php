<?php

namespace Quazardous\Daemon;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Main abstract daemon class.
 *
 */
abstract class Daemon
{
    protected $loopInterval = 60;
    
    protected $parent = true;
    abstract protected function do();
    
    protected $running = true;
    public function isRunning()
    {
        return $this->running;
    }
    
    public function stop()
    {
        $this->log(LogLevel::DEBUG, 'STOP !');
        $this->running = false;
    }
    
    public function run()
    {
        $this->log(LogLevel::DEBUG, 'startup...');
        $this->startup();
        $this->log(LogLevel::DEBUG, 'loop...');
        $this->loop();
        $this->log(LogLevel::DEBUG, 'shutdown...');
        $this->shutdown();
        $this->log(LogLevel::DEBUG, 'bye');
    }
    
    /**
     * System startup.
     */
    protected function startup()
    {
        $this->initMaxProcessesByGroup();
    }
    
    protected function loop()
    {
        while ($this->isRunning()) {
            $this->log(LogLevel::DEBUG, sprintf('running (load = %d%%)', $this->softLoad * 100));
            $this->do();
            if (!$this->isRunning()) return;
            $this->tock();
        }
    }
    
    /**
     * System shutdown.
     */
    protected function shutdown()
    {
        // wait for children to end
        $this->checkChildren(true);
    }
    
    /**
     * @param TaskInterface|callable $task if you want to use object you have to implement __invoke()
     * @param callable[] $events
     * @param string $group
     * @throws \RuntimeException
     * @return ProcessInterface|false
     */
    protected function task(callable $task, $events = [], $group = 'default')
    {
        $this->log(LogLevel::DEBUG, 'creating task...');
        if ($task instanceof TaskInterface) {
            $group = $task->getGroup();
        }
        if (!$this->canCreateProcess($group)) return false;
        $process = $this->createProcess($group, $task);
        if ($task instanceof TaskInterface) {
            $this->addProcessEvents($process, $task->getEnvents());
        }
        if (false === $process) return false;
        $process->addEvent(Process::EVENT_FORK_CHILD, function() {
            // just for log sanity
            $this->parent = false;
        });
        $this->addProcessEvents($process, $events);
        $pid = $process->execute();
        $this->children[$pid] = $process;
        $this->incrStats($process->getGroup());
        $this->log(LogLevel::DEBUG, sprintf('task has PID %d', $pid));
        return $process;
    }
    
    protected function addProcessEvents(Process $process, $events)
    {
        if (empty($events)) return;
        if (is_callable($events)) {
            $events = [$events];
        }
        foreach ($events as $type => $callbacks) {
            if (is_array($callbacks) && count($callbacks = 2) && is_string($callbacks[0]) && is_callable($callbacks[1])) {
                // adding event with [type, callback]
                $process->addEvent($callbacks[0], $callbacks[1]);
                continue;
            }
            if (is_callable($callbacks)) {
                $callbacks = [$callbacks];
            }
            if (!is_string($type)) {
                $type = Process::EVENT_TERMINATE;
            }
            foreach ($callbacks as $callback) {
                $process->addEvent($type, $callback);
            }
        }
    }
    
    /**
     * You can override this one to use your own Process class.
     * @param string $group
     * @param callable $task
     * @return ProcessInterface
     */
    protected function createProcess($group, callable $task)
    {
        return new Process($group, $task);
    }
    
    protected $processesByGroup = [];
    protected function incrStats($group)
    {
        if (empty($this->processesByGroup[$group])) {
            $this->processesByGroup[$group] = 0;
        }
        $this->processesByGroup[$group]++;
        $this->updateSoftLoad();
    }
    
    protected function decrStats($group)
    {
        if (empty($this->processesByGroup[$group])) {
            throw new \LogicException('Something is wrong: cannot decr before incr !');
        }
        $this->processesByGroup[$group]--;
        $this->updateSoftLoad();
    }

    /**
     * Soft load is calculated using all process in groups that have soft max.
     * ie in group if the soft max is 3 that means 1 process uses 33% of the soft load.
     * If in another group the soft max is 4, 1 process uses 25% of the soft load.
     * When you reach 100%, adding a process in a group that has soft max becomes impossible.
     * @var integer
     */
    protected $softLoad = 0;
    protected function updateSoftLoad()
    {
        $this->softLoad = 0;
        foreach ($this->processesByGroup as $group => $n) {
            if (empty($this->maxProcessesByGroup[$group]['soft'])) continue;
            $this->softLoad += $n / $this->maxProcessesByGroup[$group]['soft'];
        }
    }
    
    /**
     * hard/soft max by group.
     * - null means no max
     * - an integer value means hard max of processes
     * - an array of 2 elements means hard max and soft max
     *
     * By default setting the hard max does not set the soft max.
     *
     * @var array
     * @see Daemon::$softLoad
     */
    protected $maxProcessesByGroup = [
        'default' => null,
    ];
    protected function initMaxProcessesByGroup()
    {
        foreach ($this->maxProcessesByGroup as &$max) {
            if (is_null($max)) {
                $max = [null, null];
            } else if (!is_array($max)) {
                $max = [intval($max), null];
            }
            if (2 != count($max)) {
                throw new \InvalidArgumentException('Invalid max');
            }
            $max = [
                'hard' => $max['hard'] ?? $max[0],
                'soft' => $max['soft'] ?? $max[1],
            ];
        }
    }
    
    /**
     * Decide if we can add a process.
     * @param string $group
     */
    protected function canCreateProcess($group, $silent = false)
    {
        // test hard max first
        if (!empty($this->maxProcessesByGroup[$group]['hard'])) {
            if (empty($this->processesByGroup[$group])) $this->processesByGroup[$group] = 0;
            if ($this->maxProcessesByGroup[$group]['hard'] <= $this->processesByGroup[$group]) {
                if (!$silent) $this->log(LogLevel::DEBUG, sprintf('Cannot create process in group %s (hard max = %d)', $group, $this->maxProcessesByGroup[$group]['hard']));
                return false;
            }
        }
        // the test soft max
        if (!empty($this->maxProcessesByGroup[$group]['soft'])) {
            $incr = 1 / $this->maxProcessesByGroup[$group]['soft'];
            if (($incr + $this->softLoad) > 1) {
                if (!$silent) $this->log(LogLevel::DEBUG, sprintf('Cannot create process in group %s (soft load = %d%%)', $group, $this->softLoad * 100));
                return false;
            }
        }
        return true;
    }
    
    /**
     * @var ProcessInterface[]
     */
    protected $children = [];
    protected function checkChildren($wait = false)
    {
        $this->log(LogLevel::DEBUG, sprintf('Check my %d children...', count($this->children)));
        $finished = [];
        $this->walkChildren(function (ProcessInterface $child) use ($wait, &$finished) {
            if (!$child->isRunning()) return;
            if ($child->checkRunning($wait)) {
                // still running but not waiting
//                 $this->log(LogLevel::DEBUG, sprintf('Child %d still running', $child->getPid()));
            } else {
                if ($child->getExitStatus()) {
                    $this->log(LogLevel::DEBUG, sprintf('Child %d exited (%d)', $child->getPid(), $child->getExitStatus()));
                } elseif ($child->getStopSig()) {
                    $this->log(LogLevel::DEBUG, sprintf('Child %d stopped (%d)', $child->getPid(), $child->getStopSig()));
                } elseif ($child->getTermSig()) {
                    $this->log(LogLevel::DEBUG, sprintf('Child %d terminated (%d)', $child->getPid(), $child->getTermSig()));
                }
                $finished[] = $child;
            }
        });
        
        array_walk($finished, function(ProcessInterface $child) {
            unset($this->children[$child->getPid()]);
            $this->decrStats($child->getGroup());
            $child->triggerEvent(Process::EVENT_CLEANUP);
        });
        
    }
    
    /**
     * Apply the callback on every children.
     * @param callable $callback function (ProcessInterface $child) { ... }
     */
    public function walkChildren(callable $callback)
    {
        array_walk($this->children, $callback);
    }
    
    protected $nextTock = null;
    
    /**
     * Tock is a waiting loop.
     */
    protected function tock()
    {
        if (is_null($this->nextTock)) {
            $this->nextTock = time();
        }
        $this->nextTock += $this->loopInterval;
        while (time() < $this->nextTock) $this->tick();
    }
    
    protected $tickInterval = 1;
    protected function tick()
    {
        // check children often :)
        $this->checkChildren();
        sleep($this->tickInterval);
    }
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    protected function log($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->log($level, ($this->parent ? '' : sprintf('(%d) ', getmypid())) . $message, $context);
        }
    }
    
}