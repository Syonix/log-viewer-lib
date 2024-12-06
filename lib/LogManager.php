<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Syonix\LogViewer\Exceptions\NoLogsConfiguredException;

class LogManager
{
	protected ArrayCollection $collections;
	protected string $cacheDir;
	protected int $expire;
	protected bool $reverse;
	protected LogFileCache $cache;

	public function __construct(array $logs, string $cacheDir, int $expire = 300, bool $reverse = true)
	{
		setlocale(LC_ALL, 'en_US.UTF8');

		$this->collections = new ArrayCollection;
		$this->cacheDir = $cacheDir;
		$this->expire = $expire;
		$this->reverse = $reverse;

		$adapter = new LocalFilesystemAdapter($this->cacheDir);
		$this->cache = new LogFileCache($adapter, $this->expire, $this->reverse);

		if (count($logs) === 0)
			throw new NoLogsConfiguredException;

		foreach ($logs as $collection => $collectionLogs) {
			if (count($collectionLogs) === 0)
				continue;

			$collection = new LogCollection($collection);

			foreach ($collectionLogs as $logName => $args)
				$collection->addLog(new LogFile($logName, $collection->getSlug(), $args));

			$this->collections->add($collection);
		}
	}

	public function hasLogs(): bool
	{
		return !$this->collections->isEmpty();
	}

	public function getCollections(): ArrayCollection
	{
		return $this->collections;
	}

	public function getLogCollection(string $slug): ?LogCollection
	{
		foreach ($this->collections as $collection)
			if ($collection->getSlug() === $slug)
				return $collection;

		return null;
	}

	public function clearCache(string $collection, string $log): void
	{
		$collection = $this->getLogCollection($collection);
		$log = $collection->getLog($log);

		if ($log === null)
			return;

		$this->cache->clearCache($log);
	}

	public function getFirstLogCollection(): ?LogCollection
	{
		return ($this->collections->count() > 0) ? $this->collections->first() : null;
	}

	public function logCollectionExists(LogCollection $logCollection): bool
	{
		return $this->collections->contains($logCollection);
	}

	public function loadLog(LogFile $logFile): LogFile
	{
		return $this->cache->get($logFile);
	}
}
