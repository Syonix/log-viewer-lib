<?php

namespace Syonix\LogViewer;

use Dubture\Monolog\Parser\LineLogParser;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

/**
 * Takes care of returning log file contents.
 */
class LogFileAccessor
{
	private bool $reverse;

	public function __construct($reverse = true)
	{
		$this->reverse = $reverse;
	}

	public static function isAccessible(LogFile $logFile): bool
	{
		$args = self::getFilesystem($logFile->getArgs());

		return $args['filesystem']->has($args['path']);
	}

	public function get(LogFile $logFile): LogFile
	{
		$args = self::getFilesystem($logFile->getArgs());

		$file = $args['filesystem']->read($args['path']);

		if (pathinfo($args['path'])['extension'] === 'gz')
			$file = gzdecode($file);

		$lines = explode("\n", $file);
		$parser = new LineLogParser;
		$hasCustomPattern = isset($args['pattern']);

		if ($hasCustomPattern)
			$parser->registerPattern('custom', $args['pattern']);

		foreach ($lines as $line) {
			$entry = ($hasCustomPattern ? $parser->parse($line, 0, 'custom') : $parser->parse($line, 0));

			if (count($entry) === 0)
				continue;

			if (!$logFile->hasLogger($entry['logger']))
				$logFile->addLogger($entry['logger']);

			$logFile->addLine($entry);
		}

		if ($this->reverse)
			$logFile->reverseLines();

		return $logFile;
	}

	private static function getFilesystem($args)
	{
		switch ($args['type']) {
			case 'ftp':
				$default = [
					'port' => 21,
					'passive' => true,
					'ssl' => false,
					'timeout' => 30,
				];

				$args['filesystem'] = new Filesystem(new FtpAdapter(FtpConnectionOptions::fromArray([
					'host' => $args['host'],
					'username' => $args['username'],
					'password' => $args['password'],
					'port' => $args['port'] ?? $default['port'],
					'passive' => $args['passive'] ?? $default['passive'],
					'ssl' => $args['ssl'] ?? $default['ssl'],
					'timeout' => $args['timeout'] ?? $default['timeout'],
				])));
				break;
			case 'sftp':
				$default = [
					'port' => 21,
					'timeout' => 30,
				];

				$args['filesystem'] = new Filesystem(new SftpAdapter(
					new SftpConnectionProvider(
						$args['host'],
						$args['username'],
						$args['password'],
						$args['private_key'] ?? null,
						$args['private_key_passphrase'] ?? null,
						$args['port'] ?? $default['port'],
						false, // Note: agent currently not supported. Open PR if required.
						$args['timeout'] ?? $default['timeout'],
						$args['max_tries'] ?? 4,
						$args['host_fingerprint'] ?? null,
					),
					$args['path']));
				break;
			case 'local':
				$args['filesystem'] = new Filesystem(new LocalFilesystemAdapter(dirname($args['path'])));
				$args['path'] = basename($args['path']);
				break;
			default:
				throw new InvalidArgumentException('Invalid log file type: "' . $args['type'] . '"');
		}

		return $args;
	}
}
