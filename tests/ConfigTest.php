<?php

class ConfigTest extends PHPUnit_Framework_TestCase
{
    public function testLint() {
        $config_valid = file_get_contents(__DIR__.'/res/config_valid.yml');
        $response = \Syonix\LogViewer\Config::lint($config_valid);

        self::printLint($response);

        $this->assertArrayHasKey('valid', $response);
        $this->assertArrayHasKey('checks', $response);
        $this->assertTrue($response['valid']);
        $this->assertGreaterThan(0, count($response['checks']));
        foreach($response['checks'] as $check) {
            $this->assertArrayHasKey('message', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertEquals('ok', $check['status']);
        }
    }

    public static function printLint($lint) {
        $g = "\x1b[32m"; // Green
        $y = "\x1b[33m"; // Yellow
        $r = "\x1b[31m"; // Red
        $d = "\x1b[39m"; // Default

        $baseWidth = 50;
        echo "\n".str_repeat('-', $baseWidth+8);
        echo "\n Linting config file";
        echo "\n".str_repeat('-', $baseWidth+8);
        foreach($lint['checks'] as $check) {
            self::printLintLine($check, $baseWidth);
        }
        echo "\n".str_repeat('-', $baseWidth+8);
        echo "\n ".($lint['valid'] ? $g.'YOUR CONFIG FILE IS VALID!'.$d : $r.'YOUR CONFIG FILE IS NOT VALID!'.$d);
        echo "\n".str_repeat('-', $baseWidth+8);

        return true;
    }

    public static function printLintLine($check, $baseWidth = 50, $level = 0)
    {
        $g = "\x1b[32m"; // Green
        $y = "\x1b[33m"; // Yellow
        $r = "\x1b[31m"; // Red
        $d = "\x1b[39m"; // Default

        $colors = array(
            'ok'   => $g,
            'warn' => $y,
            'fail' => $r,
        );

        $indentation = str_repeat('  ', $level);
        $status = $check['status'] == 'ok' ? ' ok ' : $check['status'];
        echo "\n".str_pad($indentation."  ➤ ".$check['message'], $baseWidth)."[ ".$colors[$check['status']].$status.$d." ]";
        if($check['error'] != '') {
            echo "\n    ".$indentation.$y."! ".$d.$check['error'];
        }
        if(!empty($check['sub_checks'])) {
            foreach($check['sub_checks'] as $subCheck) {
                self::printLintLine($subCheck, $baseWidth, $level + 1);
            }
        }
    }
}
