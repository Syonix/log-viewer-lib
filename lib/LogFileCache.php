<?php

namespace Syonix\LogViewer;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;

/**
 * Takes care of returning log file contents, either from the source or locally cached and supports various adapters.
 */
class LogFileCache
{
	private Filesystem $cache;
	private LogFileAccessor $accessor;
	private int $expire;

	public function __construct(FilesystemAdapter $adapter, $expire = 300, $reverse = true)
	{
		$this->cache = new Filesystem($adapter);
		$this->accessor = new LogFileAccessor($reverse);
		$this->expire = $expire;
	}

	public static function isSourceFileAccessible(LogFile $logFile): bool
	{
		return LogFileAccessor::isAccessible($logFile);
	}

	public function clearCache(): void
	{
		$cache = $this->cache->listContents('/');

		foreach ($cache as $file)
			if ($file->type() === StorageAttributes::TYPE_FILE && !str_starts_with(basename($file->path()), '.'))
				$this->cache->delete($file['path']);
	}

	public function get(LogFile $logFile): LogFile
	{
		if ($this->cache->fileExists($this->getFilename($logFile))) {
			$timestamp = $this->cache->lastModified($this->getFilename($logFile));

			if ($timestamp > (time() - $this->expire))
				return $this->readCache($logFile);

			$this->deleteCache($logFile);
		}

		return $this->loadSource($logFile);
	}

	private function readCache(LogFile $logFile): LogFile
	{
		return unserialize($this->cache->read($this->getFilename($logFile)));
	}

	private function deleteCache(LogFile $logFile): void
	{
		$this->cache->delete($this->getFilename($logFile));
	}

	private function loadSource(LogFile $logFile): LogFile
	{
		$logFile = $this->accessor->get($logFile);
		$this->writeCache($logFile);

		return $logFile;
	}

	private function writeCache(LogFile $logFile): void
	{
		$this->cache->write($this->getFilename($logFile), serialize($logFile));
	}

	private function getFilename(LogFile $logFile): string
	{
		return base64_encode($logFile->getIdentifier());
	}
}
