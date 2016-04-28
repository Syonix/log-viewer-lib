<?php

namespace Syonix\LogViewer;

use Dubture\Monolog\Parser\LineLogParser;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

class LogFileCache
{
    private $cache;
    private $expire;
    private $reverse;

    public function __construct(AdapterInterface $adapter, $expire = 300, $reverse = true)
    {
        $this->cache = new Filesystem($adapter);
        $this->expire = $expire;
        $this->reverse = $reverse;
    }

    public function get(LogFile $logFile)
    {
        if ($this->cache->has($this->getFilename($logFile))) {
            $timestamp = $this->cache->getTimestamp($this->getFilename($logFile));
            if ($timestamp > (time() - $this->expire)) {
                return $this->readCache($logFile);
            } else {
                $this->deleteCache($logFile);
            }
        }

        return $this->loadSource($logFile);
    }

    private function getFilename(LogFile $logFile)
    {
        return base64_encode($logFile->getIdentifier());
    }

    private function writeCache(LogFile $logFile)
    {
        $this->cache->write($this->getFilename($logFile), serialize($logFile));
    }

    private function readCache(LogFile $logFile)
    {
        return unserialize($this->cache->get($this->getFilename($logFile))->read());
    }

    private function deleteCache(LogFile $logFile)
    {
        $this->cache->delete($this->getFilename($logFile));
    }

    public function emptyCache()
    {
        $cache = $this->cache->get('/')->getContents();
        foreach ($cache as $file) {
            if ($file['type'] == 'file' && substr($file['basename'], 0, 1) !== '.') {
                $this->cache->delete($file['path']);
            }
        }
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
                    'port'     => isset($args['port']) ? $args['port'] : $default['port'],
                    'passive'  => isset($args['passive']) ? $args['passive'] : $default['passive'],
                    'ssl'      => isset($args['ssl']) ? $args['ssl'] : $default['ssl'],
                    'timeout'  => isset($args['timeout']) ? $args['timeout'] : $default['timeout'],
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
                    'port'     => isset($args['port']) ? $args['port'] : $default['port'],
                    'passive'  => isset($args['passive']) ? $args['passive'] : $default['passive'],
                    'ssl'      => isset($args['ssl']) ? $args['ssl'] : $default['ssl'],
                    'timeout'  => isset($args['timeout']) ? $args['timeout'] : $default['timeout'],
                ];
                if (isset($args['private_key'])) {
                    $config['privateKey'] = $args['private_key'];
                }
                $args['filesystem'] = new Filesystem(new SftpAdapter($config));
                break;
            case 'local':
                $args['filesystem'] = new Filesystem(new Local(dirname($args['path'])));
                $args['path'] = basename($args['path']);
                break;
            default:
                throw new \InvalidArgumentException('Invalid log file type: "'.$args['type'].'"');
        }

        return $args;
    }

    private function loadSource(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        $file = $args['filesystem']->read($args['path']);
        $lines = explode("\n", $file);
        $parser = new LineLogParser();
        if (isset($args['pattern'])) {
            $hasCustomPattern = true;
            $parser->registerPattern('custom', $args['pattern']);
        } else {
            $hasCustomPattern = false;
        }

        foreach ($lines as $line) {
            $entry = ($hasCustomPattern ? $parser->parse($line, 0, 'custom') : $parser->parse($line, 0));
            if (count($entry) > 0) {
                if (!$logFile->hasLogger($entry['logger'])) {
                    $logFile->addLogger($entry['logger']);
                }
                $logFile->addLine($entry);
            }
        }

        if ($this->reverse) {
            $logFile->reverseLines();
        }
        $this->writeCache($logFile);

        return $logFile;
    }

    public static function isSourceFileAccessible(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        return $args['filesystem']->has($args['path']);
    }
}
