<?php

use PHPUnit\Framework\TestCase;
use Syonix\LogViewer\LogFile;

final class LogFileTest extends TestCase
{
	/**
	 * @dataProvider logLineTextProvider
	 */
	public function testLogLineHasText(array $line, string $query, bool $meta, bool $expected)
	{
		$log = new LogFile('logfile', 'logfile', []);
		$log->addLine($line);

		$this->assertTrue(true); // TODO
	}

	public function logLineTextProvider()
	{
		return [
			[[], '', true, true],
		];
	}
}
