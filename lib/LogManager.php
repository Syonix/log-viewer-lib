<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use Syonix\LogViewer\Exceptions\NoLogsConfiguredException;

/**
 * Represents the entry point for the application.
 */
class LogManager
{
	protected ArrayCollection $logCollections;
	protected $cacheDir;

	public function __construct($logs)
	{
		setlocale(LC_ALL, 'en_US.UTF8');

		$this->logCollections = new ArrayCollection;

		if (count($logs) === 0)
			throw new NoLogsConfiguredException;

		foreach ($logs as $logCollectionName => $logCollectionLogs) {
			if (count($logCollectionLogs) === 0)
				continue;

			$logCollection = new LogCollection($logCollectionName);

			foreach ($logCollectionLogs as $logName => $args)
				$logCollection->addLog(new LogFile($logName, $logCollection->getSlug(), $args));

			$this->logCollections->add($logCollection);
		}
	}

	public function hasLogs(): bool
	{
		return !$this->logCollections->isEmpty();
	}

	public function getLogCollections(): ArrayCollection
	{
		return $this->logCollections;
	}

	public function getLogCollection(string $slug): ?LogCollection
	{
		foreach ($this->logCollections as $logCollection)
			if ($logCollection->getSlug() === $slug)
				return $logCollection;

		return null;
	}

	public function getFirstLogCollection(): ?LogCollection
	{
		return ($this->logCollections->count() > 0) ? $this->logCollections->first() : null;
	}

	public function logCollectionExists($logCollection): bool
	{
		return $this->logCollections->contains($logCollection);
	}
}
