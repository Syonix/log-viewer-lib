<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Syonix\LogViewer\LogFile;

final class LogFileTest extends TestCase
{
	public static function logLineTextProvider(): array
	{
		return [
			[[], '', true, true],
		];
	}

	#[DataProvider('logLineTextProvider')]
	public function testLogLineHasText(array $line, string $query, bool $meta, bool $expected): void
	{
		$log = new LogFile('logfile', 'logfile', []);
		$log->addLine($line);

		// @phpstan-ignore method.alreadyNarrowedType
		$this->assertTrue(true); // TODO write tests
	}
}
