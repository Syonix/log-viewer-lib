<?php

namespace Syonix\LogViewer;

use Dubture\Monolog\Parser\LineLogParser;
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
                if($args['filePattern']){
                    date_default_timezone_set('Europe/Madrid'); //change to your timezone
                    $directory = dirname($args['filePattern']).DIRECTORY_SEPARATOR;
                    $files = scandir($directory);
                    $files = array_diff($files, array('.', '..'));
                    foreach($files as $file) {

                        if(!preg_match(basename($args['path'], $file) && !is_dir($directory . $file))){

                            $time["$file"] = filemtime($directory . $file);
                        }
                    }
                    array_multisort($time);
                    end($time);
                    $first_key = key($time);
                    $args['filesystem'] = new Filesystem(new Local($directory));
                    $args['path'] =$first_key;
                }else{
                    $args['filesystem'] = new Filesystem(new Local(dirname($args['path'])));
                    $args['path'] = basename($args['path']);
                }
                break;
            default:
                throw new \InvalidArgumentException('Invalid log file type: "'.$args['type'].'"');
        }

        return $args;
    }

    public function get(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        $file = $args['filesystem']->read($args['path']);
        if (pathinfo($args['path'])['extension'] === 'gz') {
            $file = gzdecode($file);
        }
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

        return $logFile;
    }

    public static function isAccessible(LogFile $logFile)
    {
        $args = self::getFilesystem($logFile->getArgs());

        return $args['filesystem']->has($args['path']);
    }
}
