<?php

namespace Syonix\LogViewer;

use DateTime;

class LogApiHelper
{
	private LogManager $manager;

	public function __construct(LogManager $manager)
	{
		$this->manager = $manager;
	}

	public function getLogs(string $urlPrefix = ''): array
	{
		$result = [];
		foreach ($this->manager->getCollections() as $collection) {
			$row = [
				'name' => $collection->getName(),
				'slug' => $collection->getSlug(),
			];

			foreach ($collection->getLogs() as $log)
				$row['logs'][] = [
					'name' => $log->getName(),
					'slug' => $log->getSlug(),
				];

			$result[] = $row;
		}

		return $result;
	}

	public function getLog(string $collection, string $log, int $limit, int $offset = 0, array $filter = [], string $urlPrefix = ''): array
	{
		$collection = $this->manager->getLogCollection($collection);
		$log = $this->manager->loadLog($collection->getLog($log));
		$totalLines = $log->countLines($filter);

		$prevOffset = max($offset - $limit, 0);
		$nextOffset = $offset + $limit;

		$id = $log->getIdentifier();
		$prev = $offset > 0 ? "$urlPrefix/$id?limit=$limit&offset=$prevOffset" : null;
		$next = $nextOffset < $totalLines ? "$urlPrefix/$id?limit=$limit&offset=$nextOffset" : null;

		foreach ($filter as $k => $f) {
			if ($f === null)
				continue;

			if ($prev !== null)
				$prev .= "&$k=$f";

			if ($next !== null)
				$next .= "&$k=$f";
		}

		// TODO: Use proper URL Builder

		$lines = $log->getLines($limit, $offset, $filter);

		foreach ($lines as &$line)
			$line['date'] = $line['date'] instanceof DateTime ? $line['date']->format('c') : null; // TODO: Improve

		return [
			'name' => $log->getName(),
			'slug' => $log->getSlug(),
			'collection' => [
				'name' => $collection->getName(),
				'slug' => $collection->getSlug(),
			],
			'lines' => $lines,
			'total_lines' => $totalLines,
			'offset' => $offset,
			'limit' => $limit,
			'loggers' => $log->getLoggers()->toArray(),
			'prev' => $prev,
			'next' => $next,
		];
	}
}
