# php-daemon
A Very Basic PHP Daemon

## usage

A main loop is waiting for something to do.
The daemon creates process for each task.
The process uses pcntl functions to create child process to run the given task.

## soft/hard max

For each group/family of tasks you can set soft or hard max.

Soft max is handled with a weight system.
