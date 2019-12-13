<?php

namespace Quazardous\Daemon;

/**
 * Optionnal task wrapper.
 *
 */
interface TaskInterface
{
    public function getGroup();
    public function __invoke();
    public function getEnvents();
}