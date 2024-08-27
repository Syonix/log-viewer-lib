<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use Syonix\LogViewer\Exceptions\NoLogsConfiguredException;

/**
 * Represents the entry point for the application.
 */
class LogManager
{
	protected $logCollections;
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

	public function hasLogs()
	{
		return !$this->logCollections->isEmpty();
	}

	public function getLogCollections()
	{
		return $this->logCollections;
	}

	/**
	 * @param $slug
	 *
	 * @return LogCollection|null
	 */
	public function getLogCollection($slug)
	{
		foreach ($this->logCollections as $logCollection)
			if ($logCollection->getSlug() == $slug)
				return $logCollection;
	}

	/**
	 * @return LogCollection|null
	 */
	public function getFirstLogCollection()
	{
		return ($this->logCollections->count() > 0) ? $this->logCollections->first() : null;
	}

	public function logCollectionExists($logCollection)
	{
		return $this->logCollections->contains($logCollection);
	}
}
