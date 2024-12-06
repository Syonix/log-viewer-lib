<?php

namespace Syonix\LogViewer\Exceptions;

use Exception;

class NoLogsConfiguredException extends Exception
{
	public function __construct(int $code = 0, Exception $previous = null)
	{
		parent::__construct('No valid log files have been configured.', $code, $previous);
	}
}
