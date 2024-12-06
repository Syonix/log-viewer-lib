<?php

namespace Syonix\LogViewer;

use DateInterval;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Monolog\Level;
use Psr\Log\InvalidArgumentException;
use UnhandledMatchError;
use URLify;

/**
 * Represents a physical log file.
 */
class LogFile
{
	/** @var string Name of the log file */
	protected string $name;

	/** @var string Auto generated url safe version of the name */
	protected string $slug;

	/** @var string Slug of the log collection containing this log file. */
	protected string $collectionSlug;

	/** @var array Config arguments */
	protected array $args;

	/** @var ArrayCollection The individual lines of the log file. */
	protected ArrayCollection $lines;

	/** @var ArrayCollection All loggers used in this file. */
	protected ArrayCollection $loggers;

	public function __construct(string $name, string $collectionSlug, array $args)
	{
		setlocale(LC_ALL, 'en_US.UTF8');

		$this->name = $name;
		$this->slug = URLify::filter($name);
		$this->collectionSlug = URLify::filter($collectionSlug);
		$this->args = $args;
		$this->lines = new ArrayCollection;
		$this->loggers = new ArrayCollection;
	}

	public function addLine(array $line): void
	{
		$this->lines->add($line);
	}

	/**
	 * Returns a log line from the file or null if the index does not exist.
	 */
	public function getLine(int $line): ?array
	{
		return $this->lines[$line] ?? null;
	}

	/**
	 * Returns the config arguments of the log file.
	 */
	public function getArgs(): array
	{
		return $this->args;
	}

	/**
	 * Returns log lines, either all or paginated and or filtered.
	 *
	 * @param int|null   $limit  Defines how many lines are returned.
	 * @param int        $offset Defines the offset for returning lines. Offset 0 starts at the first line.
	 * @param array|null $filter Filter the log lines before returning and applying pagination. Can contain keys logger,
	 *                           level, text and searchMeta (should context and extra fields also be searched)
	 *
	 * @return array
	 */
	public function getLines(?int $limit = null, int $offset = 0, ?array $filter = null): array
	{
		$result = clone $this->lines;

		if ($filter !== null)
			$result = $this->filter($result, $filter);

		if ($limit !== null)
			return array_values($result->slice($offset, $limit));

		return array_values($result->toArray());
	}

	private function filter(ArrayCollection $lines, array $filter): ArrayCollection
	{
		$time = $filter['time'] ?? null;
		$logger = $filter['logger'] ?? null;
		$minLevel = $filter['level'] ?? 0;
		$text = (isset($filter['text']) && $filter['text'] !== '') ? $filter['text'] : null;
		$searchMeta = $filter['search_meta'] ?? true;
		$result = $lines;

		foreach ($result as $line) {
			$ok = self::logLineHasTime($time, $line);
			$ok = $ok && self::logLineHasLogger($logger, $line);
			$ok = $ok && self::logLineHasMinLevel($minLevel, $line);
			$ok = $ok && self::logLineHasText($text, $line, $searchMeta);

			if (!$ok)
				$result->removeElement($line);
		}

		return $result;
	}

	/**
	 * Internal filtering method for determining whether a log line matches a specific time.
	 */
	private static function logLineHasTime(?string $time, array $line): bool
	{
		if ($time === null)
			return true;

		$check = $line['date'] ?? null;

		if ($check === null)
			return false;

		$now = new DateTime;
		$diff = match ($time) {
			'h' => new DateInterval('PT1H'),
			'd' => new DateInterval('P1D'),
			'w' => new DateInterval('P1W'),
			'm' => new DateInterval('P1M'),
			'y' => new DateInterval('P1Y'),
			default => throw new InvalidArgumentException(sprintf('Invalid time value "%s"', $time)),
		};

		// TODO: Intervals go back whole interval (e.g. 24h for day) -> Today is not today but last 24 hours

		return $check >= $now->sub($diff);
	}

	/**
	 * Internal filtering method for determining whether a log line belongs to a specific logger.
	 */
	private static function logLineHasLogger(?string $logger, array $line): bool
	{
		if ($logger === null)
			return true;

		return array_key_exists('logger', $line) && $line['logger'] === $logger;
	}

	/**
	 * Internal filtering method for determining whether a log line has a specific minimal log level.
	 */
	private static function logLineHasMinLevel(int $minLevel, array $line): bool
	{
		if ($minLevel === 0 || !isset($line['level']))
			return true;

		return static::getLevelNumber($line['level']) >= $minLevel;
	}

	/**
	 * Internal filtering method for determining whether a log line contains a specific string.
	 */
	private static function logLineHasText(?string $keyword, array $line, bool $searchMeta = true): bool
	{
		if ($keyword === null)
			return true;

		if (isset($line['message']) && str_contains(strtolower($line['message']), strtolower($keyword)))
			return true;

		/*if (isset($line['date']) && strpos(strtolower($line['date']), strtolower($keyword)) !== false)
			return true;*/
		// TODO: Format date same as angular

		if (!$searchMeta || !isset($line['meta']))
			return false;

		$context = $line['meta'];
		$keyword = strtolower($keyword);

		foreach ($context as $meta) {
			if (isset($meta[$keyword]))
				return true;

			if (is_string($meta['content']) && str_contains(strtolower($meta['content']), $keyword))
				return true;
		}

		return false;
	}

	/**
	 * Reverses the line order. (e.g. newest first instead of oldest first).
	 */
	public function reverseLines(): void
	{
		$lines = $this->lines->toArray();
		$lines = array_reverse($lines, false);
		$this->lines = new ArrayCollection($lines);
	}

	/**
	 * Returns the number of lines in the log file.
	 */
	public function countLines(?array $filter = null): int
	{
		if ($filter === null)
			return $this->lines->count();

		$lines = $this->getLines(null, 0, $filter);

		return count($lines);
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getSlug(): string
	{
		return $this->slug;
	}

	public function getLoggers(): ArrayCollection
	{
		return $this->loggers;
	}

	public function hasLogger(string $logger): bool
	{
		return $this->loggers->contains($logger);
	}

	public function addLogger(string $logger): void
	{
		$this->loggers->add($logger);
	}

	public function ensureLogger(string $logger): void
	{
		if (!$this->hasLogger($logger))
			$this->loggers->add($logger);
	}

	public static function getLevelName(int $level): ?string
	{
		if (!in_array($level, [100, 200, 250, 300, 400, 500, 550, 600]))
			return null;

		return Level::fromValue($level)->getName();
	}

	/**
	 * Returns the associated number for a log level string.
	 */
	public static function getLevelNumber(string $level): ?int
	{
		try {
			return Level::fromName($level)->value;
		} catch (UnhandledMatchError) {
			return null;
		}
	}

	public static function getLevels(): array
	{
		return Level::VALUES;
	}

	public function getCollectionSlug(): string
	{
		return $this->collectionSlug;
	}

	public function getIdentifier(): string
	{
		return "$this->collectionSlug/$this->slug";
	}

	public function toArray(): array
	{
		return [
			'name' => $this->name,
			'slug' => $this->slug,
			'loggers' => $this->loggers,
		];
	}
}
