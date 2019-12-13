<?php

namespace Quazardous\Daemon\Tests;

use PHPUnit\Framework\TestCase;
use Quazardous\Daemon\Process;

class DaemonTest extends TestCase
{
    /**
     * Output is messy.
     */
    public function testDaemon()
    {
        $daemon = new TestDaemon([
            /*[create, return]*/
            [true, 5], 
            [true, 6], 
            [true, 9],
            [false, -1],
        ], [
            // create callback
            function ($process, $expected) {
                if ($process) {
                    $this->assertTrue($expected);
                } else {
                    $this->assertFalse($expected);
                }
            },
            // return callback
            function (Process $child, $expected) {
                $this->assertEquals($expected, $child->getExitStatus());
            },
        ],
        ['test' => 3]);
        
        $daemon->run();
    }
    
    public function testSoftMax()
    {
        $daemon = new TestDaemon([
            /*[create, return, group]*/
            [true, 5, 't1'], // load = 50% -> OK
            [true, 6, 't2'], // load = 75% -> OK
            [false, 9, 't1'], // load = 125% -> KO
            [true, 9, 't2'], // load = 100% -> OK
            [false, 9, 't2'], // load = 125% -> KO
            [true, 1, 'test'], // by defaut no soft load -> OK
            [false, 1, 'test'], // hard max is 1 -> KO
        ], [
            // create callback
            function ($process, $expected) {
                if ($process) {
                    $this->assertTrue($expected);
                } else {
                    $this->assertFalse($expected);
                }
            },
            // return callback
            function (Process $child, $expected) {
                $this->assertEquals($expected, $child->getExitStatus());
            },
            ],
            [
                /* [hard, soft] */
                't1' => [null, 2],
                't2' => [null, 4],
                'test' => 1,
            ]);
        
        $daemon->run();
    }


}