<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use URLify;

/**
 * Represents a collection of log files belonging together.
 */
class LogCollection
{
	protected ?string $name = null;
	protected ?string $slug = null;
	protected ArrayCollection $logs;

	public function __construct(?string $name = null)
	{
		$this->logs = new ArrayCollection;

		if ($name !== null)
			$this->setName($name);
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(?string $name): void
	{
		$this->name = $name;
		$this->slug = URLify::filter($name);
	}

	public function addLog(LogFile $log): self
	{
		if (!$this->logs->contains($log))
			$this->logs->add($log);

		return $this;
	}

	public function removeLog(LogFile $log): self
	{
		if ($this->logs->contains($log))
			$this->logs->remove($this->logs->indexOf($log));

		return $this;
	}

	public function getLogs(): ArrayCollection
	{
		return $this->logs;
	}

	public function getLog(string $slug): ?LogFile
	{
		foreach ($this->logs as $log)
			if ($log->getSlug() === $slug)
				return $log;

		return null;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function getFirstLog(): ?LogFile
	{
		return ($this->logs->count() > 0) ? $this->logs->first() : null;
	}

	public function logExists(string $log): bool
	{
		$ok = false;
		foreach ($this->logs as $existing_log)
			$ok = $ok || $existing_log->getSlug() === $log;

		return $ok;
	}

	public function toArray(): array
	{
		$logs = [];
		foreach ($this->logs as $log)
			$logs[] = $log->toArray();

		return [
			'name' => $this->name,
			'slug' => $this->slug,
			'logs' => $logs,
		];
	}
}
