<?php

use PHPUnit\Framework\TestCase;
use Syonix\LogViewer\Config;

final class ConfigTest extends TestCase
{
	public function testLint(): void
	{
		$config_valid = file_get_contents(__DIR__ . '/res/config_valid.yml');
		$response = Config::lint($config_valid);

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

	public function testGetRoot(): void
	{
		$config = new Config(file_get_contents(__DIR__ . '/res/config_valid.yml'));
		$configTest = $config->get();
		$this->assertArrayHasKey('debug', $configTest);
	}

	public function testGetTimezone(): void
	{
		$config = new Config(file_get_contents(__DIR__ . '/res/config_valid.yml'));
		$this->assertEquals('Europe/Zurich', $config->get('timezone'));
	}

	public function testGetNestedValue(): void
	{
		$config = new Config(file_get_contents(__DIR__ . '/res/config_valid.yml'));
		$this->assertEquals('local', $config->get('logs.Demo.Demo-Log-File.type'));
	}

	public function testGetNestedValueSlug(): void
	{
		$config = new Config(file_get_contents(__DIR__ . '/res/config_valid.yml'));
		$this->assertEquals('local', $config->get('logs.demo.custom-pattern.type'));
	}

	public function testGetNestedValueException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The property "logs.Node.Not.Existing" was not found. Failed while getting node "Node"');
		$config = new Config(file_get_contents(__DIR__ . '/res/config_valid.yml'));
		$config->get('logs.Node.Not.Existing');
	}
}
