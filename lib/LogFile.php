<?php

namespace Syonix\LogViewer;

use Doctrine\Common\Collections\ArrayCollection;
use Monolog\Logger;
use Psr\Log\InvalidArgumentException;
use URLify;

/**
 * Represents a physical log file.
 */
class LogFile
{
	/** @var string Name of the log file */
	protected $name;

	/** @var string Auto generated url safe version of the name */
	protected $slug;

	/** @var string Slug of the log collection containing this log file. */
	protected $collectionSlug;

	/** @var array Config arguments */
	protected $args;

	/** @var ArrayCollection The individual lines of the log file. */
	protected $lines;

	/** @var ArrayCollection All loggers used in this file. */
	protected $loggers;

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

	/**
	 * Adds a line to the log file.
	 *
	 * @param array $line
	 *
	 * @return bool
	 */
	public function addLine(array $line)
	{
		return $this->lines->add($line);
	}

	/**
	 * Returns a log line from the file or null if the index does not exist.
	 *
	 * @param $line
	 *
	 * @return array|null
	 */
	public function getLine($line)
	{
		return $this->lines[intval($line)];
	}

	/**
	 * Returns the config arguments of the log file.
	 *
	 * @return array
	 */
	public function getArgs()
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
	public function getLines($limit = null, $offset = 0, $filter = null)
	{
		$lines = clone $this->lines;

		if ($filter !== null) {
			$logger = isset($filter['logger']) ? $filter['logger'] : null;
			$minLevel = isset($filter['level']) ? $filter['level'] : 0;
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
	 *
	 * @param string $logger
	 * @param array  $line
	 *
	 * @return bool
	 */
	private static function logLineHasLogger(string $logger, array $line)
	{
		if ($logger === null)
			return true;

		return array_key_exists('logger', $line) && $line['logger'] == $logger;
	}

	/**
	 * Internal filtering method for determining whether a log line has a specific minimal log level.
	 *
	 * @param int   $minLevel
	 * @param array $line
	 *
	 * @return bool
	 */
	private static function logLineHasMinLevel(int $minLevel, array $line)
	{
		if ($minLevel === 0)
			return true;

		$ok = array_key_exists('level', $line);
		$ok = $ok && static::getLevelNumber($line['level']) >= $minLevel;

		return $ok;
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
	private static function logLineHasText(string $keyword, array $line, $searchMeta = true)
	{
		$ok = $keyword === null;
		$ok = $ok || array_key_exists('message', $line) && strpos(strtolower($line['message']), strtolower($keyword)) !== false;
		$ok = $ok || array_key_exists('date', $line) && strpos(strtolower($line['date']), strtolower($keyword)) !== false;

		if ($ok || !$searchMeta)
			return $ok;

		$context = $line['context'] ?? [];
		$extra = $line['extra'] ?? [];
		$meta = array_merge($context, $extra);

		$ok = array_key_exists(strtolower($keyword), $meta);
		foreach ($meta as $content)
			$ok = $ok || strpos(strtolower($content), strtolower($keyword)) !== false;

		return $ok;
	}

	/**
	 * Reverses the line order. (e.g. newest first instead of oldest first).
	 */
	public function reverseLines()
	{
		$this->lines = new ArrayCollection(array_reverse($this->lines->toArray(), false));
	}

	/**
	 * Returns the number of lines in the log file.
	 *
	 * @param array|null $filter
	 *
	 * @return int
	 */
	public function countLines($filter = null)
	{
		if ($filter !== null)
			return count($this->getLines(null, 0, $filter));

		return $this->lines->count();
	}

	public function getName()
	{
		return $this->name;
	}

	public function getSlug()
	{
		return $this->slug;
	}

	public function getLoggers()
	{
		return $this->loggers;
	}

	public function hasLogger($logger)
	{
		return $this->loggers->contains($logger);
	}

	public function addLogger($logger)
	{
		return $this->loggers->add($logger);
	}

	public static function getLevelName($level)
	{
		return Logger::getLevelName($level);
	}

	/**
	 * Returns the associated number for a log level string.
	 *
	 * @param string $level
	 *
	 * @return int
	 */
	public static function getLevelNumber($level)
	{
		$levels = Logger::getLevels();

		if (!isset($levels[$level]))
			throw new InvalidArgumentException('Level "' . $level . '" is not defined, use one of: ' . implode(', ', $levels));

		return $levels[$level];
	}

	public static function getLevels()
	{
		return Logger::getLevels();
	}

	public function getCollectionSlug()
	{
		return $this->collectionSlug;
	}

	public function getIdentifier()
	{
		return "$this->collectionSlug/$this->slug";
	}

	public function toArray()
	{
		return [
			'name' => $this->name,
			'slug' => $this->slug,
			'loggers' => $this->loggers,
		];
	}
}
