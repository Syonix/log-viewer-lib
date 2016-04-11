<?php

class ConfigTest extends PHPUnit_Framework_TestCase
{
    public function testLint() {
        $config_valid = file_get_contents(__DIR__.'/res/config_valid.yml');
        $response = \Syonix\LogViewer\Config::lint($config_valid);
        
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
}
