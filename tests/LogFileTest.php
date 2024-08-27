<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Syonix\LogViewer\LogFile;

final class LogFileTest extends TestCase
{
	#[DataProvider('logLineTextProvider')]
	public function testLogLineHasText(array $line, string $query, bool $meta, bool $expected)
	{
		$log = new LogFile('logfile', 'logfile', []);
		$log->addLine($line);

		$this->assertTrue(true); // TODO
	}

	public static function logLineTextProvider(): array
	{
		return [
			[[], '', true, true],
		];
	}
}
