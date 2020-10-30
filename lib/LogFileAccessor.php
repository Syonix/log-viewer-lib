<?php

namespace Syonix\LogViewer;

use Dubture\Monolog\Parser\LineLogParser;
use InvalidArgumentException;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * Takes care of returning log file contents.
 */
class LogFileAccessor
{
    private $reverse;

    public function __construct($reverse = true)
    {
        $this->reverse = $reverse;
    }

    private static function getFilesystem($args)
    {
        switch ($args['type']) {
            case 'ftp':
                $default = [
                    'port'    => 21,
                    'passive' => true,
                    'ssl'     => false,
                    'timeout' => 30,
                ];

                $args['filesystem'] = new Filesystem(new Ftp([
                    'host'     => $args['host'],
                    'username' => $args['username'],
                    'password' => $args['password'],
                    'port'     => $args['port'] ?? $default['port'],
                    'passive'  => $args['passive'] ?? $default['passive'],
                    'ssl'      => $args['ssl'] ?? $default['ssl'],
                    'timeout'  => $args['timeout'] ?? $default['timeout'],
                ]));

                break;
            case 'sftp':
                $default = [
                    'port'    => 21,
                    'passive' => true,
                    'ssl'     => false,
                    'timeout' => 30,
                ];

                $config = [
                    'host'     => $args['host'],
                    'username' => $args['username'],
                    'password' => $args['password'],
                    'port'     => $args['port'] ?? $default['port'],
                    'passive'  => $args['passive'] ?? $default['passive'],
                    'ssl'      => $args['ssl'] ?? $default['ssl'],
                    'timeout'  => $args['timeout'] ?? $default['timeout'],
                ];

                if (isset($args['private_key']))
                    $config['privateKey'] = $args['private_key'];

                $args['filesystem'] = new Filesystem(new SftpAdapter($config));
                break;
            case 'local':
                $args['filesystem'] = new Filesystem(new Local(dirname($args['path'])));
                $args['path'] = basename($args['path']);
                break;
            default:
                throw new InvalidArgumentException('Invalid log file type: "'.$args['type'].'"');
        }

        return $args;
    }

    public function get(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        $file = $args['filesystem']->read($args['path']);

        if (pathinfo($args['path'])['extension'] === 'gz')
            $file = gzdecode($file);

        $lines = explode("\n", $file);
        $parser = new LineLogParser();
	    $hasCustomPattern = isset($args['pattern']);

        if ($hasCustomPattern)
            $parser->registerPattern('custom', $args['pattern']);

        foreach ($lines as $line) {
            $entry = ($hasCustomPattern ? $parser->parse($line, 0, 'custom') : $parser->parse($line, 0));

            if (count($entry) === 0)
                continue;

            if (!$logFile->hasLogger($entry['logger']))
                $logFile->addLogger($entry['logger']);

            $logFile->addLine($entry);
        }

        if ($this->reverse)
            $logFile->reverseLines();

        return $logFile;
    }

    public static function isAccessible(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        return $args['filesystem']->has($args['path']);
    }
}
