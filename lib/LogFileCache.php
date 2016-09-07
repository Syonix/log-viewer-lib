<?php

namespace Syonix\LogViewer;

use Dubture\Monolog\Parser\LineLogParser;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;

/**
 * Takes care of returning log file contents, either from the source or locally cached and supports various adapters
 * 
 * @package Syonix\LogViewer
 */
class LogFileCache
{
    private $cache;
    private $accessor;
    private $expire;
    private $reverse;

    public function __construct(AdapterInterface $adapter, $expire = 300, $reverse = true)
    {
        $this->cache = new Filesystem($adapter);
        $this->accessor = new LogFileAccessor($reverse);
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

    private function loadSource(LogFile $logFile)
    {
        $logFile = $this->accessor->get($logFile);
        $this->writeCache($logFile);
        return $logFile;
    }

    public static function isSourceFileAccessible(LogFile $logFile)
    {
        return LogFileAccessor::isAccessible($logFile);
    }
}
