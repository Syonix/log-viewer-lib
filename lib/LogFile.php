<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use Monolog\Level;
use URLify;
use InvalidArgumentException;

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

	/**
	 * LogFile constructor.
	 *
	 * @param string $name
	 * @param string $clientSlug
	 * @param array  $args
	 */
	public function __construct(string $name, string $clientSlug, array $args)
	{
		setlocale(LC_ALL, 'en_US.UTF8');

		$this->name = $name;
		$this->slug = URLify::filter($name);
		$this->collectionSlug = URLify::filter($clientSlug);
		$this->args = $args;
		$this->lines = new ArrayCollection;
		$this->loggers = new ArrayCollection;
	}

	public static function getLevelName($level): ?string
	{
		return Level::fromValue($level)->getName();
	}

	/**
	 * Adds a line to the log file.
	 */
	public function addLine(array $line): void
	{
		$this->lines->add($line);
	}

	/**
	 * Returns a log line from the file or null if the index does not exist.
	 */
	public function getLine($line): ?array
	{
		return $this->lines[(int)$line] ?? null;
	}

	/**
	 * Returns the config arguments of the log file.
	 */
	public function getArgs(): array
	{
		return $this->args;
	}

	/**
	 * Reverses the line order. (e.g. newest first instead of oldest first).
	 */
	public function reverseLines(): void
	{
		$this->lines = new ArrayCollection(array_reverse($this->lines->toArray(), false));
	}

	public function toArray(): array
	{
		return [
			'name' => $this->name,
			'slug' => $this->slug,
			'loggers' => $this->loggers,
		];
	}

	/**
	 * Returns the number of lines in the log file.
	 */
	public function countLines(?array $filter = null): int
	{
		if ($filter !== null)
			return count($this->getLines(null, 0, $filter));

		return $this->lines->count();
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
		$lines = clone $this->lines;

		if ($filter !== null) {
			$logger = $filter['logger'] ?? null;
			$minLevel = $filter['level'] ?? 0;
			$text = (isset($filter['text']) && $filter['text'] != '') ? $filter['text'] : null;
			$searchMeta = isset($filter['search_meta']) ? ($filter['search_meta']) : true;

			foreach ($lines as $line) {
				$ok = true;
				$ok = $ok || static::logLineHasLogger($logger, $line);
				$ok = $ok || static::logLineHasMinLevel($minLevel, $line);
				$ok = $ok || static::logLineHasText($text, $line, $searchMeta);

				if (!$ok)
					$lines->removeElement($line);
			}
		}

		if (null !== $limit)
			return array_values($lines->slice($offset, $limit));

		return array_values($lines->toArray());
	}

	/**
	 * Internal filtering method for determining whether a log line belongs to a specific logger.
	 */
	private static function logLineHasLogger(?string $logger, array $line): bool
	{
		if ($logger === null)
			return true;

		return array_key_exists('logger', $line) && $line['logger'] == $logger;
	}

	/**
	 * Internal filtering method for determining whether a log line has a specific minimal log level.
	 */
	private static function logLineHasMinLevel(int $minLevel, array $line): bool
	{
		if ($minLevel === 0)
			return true;

		$ok = array_key_exists('level', $line);
		$ok = $ok && static::getLevelNumber($line['level']) >= $minLevel;

		return $ok;
	}

	/**
	 * Returns the associated number for a log level string.
	 */
	public static function getLevelNumber(string $level)
	{
		$levels = self::getLevels();

		if (!isset($levels[$level]))
			throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' . implode(', ', $levels));

		return $levels[$level];
	}

	public static function getLevels(): array
	{
		return Level::VALUES;
	}

	/**
	 * Internal filtering method for determining whether a log line contains a specific string.
	 *
	 * @param string $keyword
	 * @param array  $line
	 * @param bool   $searchMeta
	 *
	 * @return bool
	 */
	private static function logLineHasText(string $keyword, array $line, bool $searchMeta = true): bool
	{
		$ok = $keyword === null;
		$ok = $ok || array_key_exists('message', $line) && str_contains(strtolower($line['message']), strtolower($keyword));
		$ok = $ok || array_key_exists('date', $line) && str_contains(strtolower($line['date']), strtolower($keyword));

		if ($ok || !$searchMeta)
			return $ok;

		$context = $line['context'] ?? [];
		$extra = $line['extra'] ?? [];
		$meta = array_merge($context, $extra);

		$ok = array_key_exists(strtolower($keyword), $meta);
		foreach ($meta as $content)
			$ok = $ok || str_contains(strtolower($content), strtolower($keyword));

		return $ok;
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

	public function hasLogger($logger): bool
	{
		return $this->loggers->contains($logger);
	}

	public function addLogger($logger): void
	{
		$this->loggers->add($logger);
	}

	public function getCollectionSlug(): string
	{
		return $this->collectionSlug;
	}

	public function getIdentifier(): string
	{
		return "$this->collectionSlug/$this->slug";
	}
}
