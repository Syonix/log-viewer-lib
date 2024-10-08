<?php

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testLint()
    {
        $config_valid = file_get_contents(__DIR__.'/res/config_valid.yml');
        $response = \Syonix\LogViewer\Config::lint($config_valid);

        $this->assertArrayHasKey('valid', $response);
        $this->assertArrayHasKey('checks', $response);
        $this->assertTrue($response['valid']);
        $this->assertGreaterThan(0, count($response['checks']));
        foreach ($response['checks'] as $check) {
            $this->assertArrayHasKey('message', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertEquals('ok', $check['status']);
        }
    }

    public function testGetRoot()
    {
        $config = new \Syonix\LogViewer\Config(file_get_contents(__DIR__.'/res/config_valid.yml'));
        $configTest = $config->get();
        $this->assertArrayHasKey('debug', $configTest);
    }

    public function testGetTimezone()
    {
        $config = new \Syonix\LogViewer\Config(file_get_contents(__DIR__.'/res/config_valid.yml'));
        $this->assertEquals('Europe/Zurich', $config->get('timezone'));
    }

    public function testGetNestedValue()
    {
        $config = new \Syonix\LogViewer\Config(file_get_contents(__DIR__.'/res/config_valid.yml'));
        $this->assertEquals('local', $config->get('logs.Demo.Demo-Log-File.type'));
    }

    public function testGetNestedValueSlug()
    {
        $config = new \Syonix\LogViewer\Config(file_get_contents(__DIR__.'/res/config_valid.yml'));
        $this->assertEquals('local', $config->get('logs.demo.custom-pattern.type'));
    }

    public function testGetNestedValueException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The property "logs.Node.Not.Existing" was not found. Failed while getting node "Node"');
        $config = new \Syonix\LogViewer\Config(file_get_contents(__DIR__.'/res/config_valid.yml'));
        $config->get('logs.Node.Not.Existing');
    }
}
