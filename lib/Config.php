<?php

namespace Syonix\LogViewer;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use URLify;

/**
 * Abstracts the application configuration and includes a linter to validate the configuration.
 */
class Config
{
	protected array $config;
	protected array $configTree;

	/**
	 * @param array|string $config Either an array or the file contents of the config file (yaml).
	 */
	public function __construct(array|string $config)
	{
		$this->config = is_array($config) ? $config : $this->parse($config);
	}

	public static function parseFile(string $path): ?array
	{
		$input = file_get_contents($path);

		if ($input === false)
			throw new RuntimeException("Failed to read config file '$path'");

		return self::parse($input);
	}

	public static function parse(string $input): ?array
	{
		return Yaml::parse($input, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
	}

	public function validate(): array
	{
		return self::lint($this->config);
	}

	/**
	 * Lints a config file for syntactical and semantic correctness.
	 *
	 * @param mixed[]|string $config         The configuration string to parse and lint
	 * @param bool           $verifyLogFiles Also verify whether the log files are accessible
	 *
	 * @return mixed[]
	 */
	public static function lint(array|string $config, bool $verifyLogFiles = false): array
	{
		$valid = true;
		$checks = [];

		// Valid YAML
		$checks['valid_yaml'] = [
			'message' => 'Is a valid YAML file',
		];

		try {
			$config = is_string($config) ? self::parse($config) : $config;
			$checks['valid_yaml']['status'] = 'ok';
		} catch (Exception $e) {
			$valid = false;
			$checks['valid_yaml']['status'] = 'fail';
			$checks['valid_yaml']['error'] = $e->getMessage();
		}

		try {
			// Valid structure
			$checks['valid_structure'] = self::lintValidProperties($config);
			if ($checks['valid_structure']['status'] === 'fail')
				throw new Exception;

			// Valid config values
			$checks['valid_settings'] = self::lintValidSettingsValues($config);
			if ($checks['valid_settings']['status'] === 'fail')
				throw new Exception;

			// Validate log collections (each)
			$checks['log_collections'] = [
				'message' => 'Checking log collections',
			];

			try {
				foreach ($config['logs'] as $logCollectionName => $logCollection) {
					$checks['log_collections']['sub_checks'][$logCollectionName] = self::lintLogCollection($logCollectionName, $logCollection);
					if ($checks['log_collections']['sub_checks'][$logCollectionName]['status'] === 'fail')
						throw new Exception;
				}
				foreach ($config['logs'] as $logCollectionName => $logCollection) {
					$checks['log_collections']['checks'][$logCollectionName] = self::lintLogCollection($logCollectionName, $logCollection, $verifyLogFiles);
					if ($checks['log_collections']['checks'][$logCollectionName]['status'] === 'fail')
						throw new Exception;
				}
				$checks['log_collections']['status'] = 'ok';
			} catch (Exception $e) {
				$checks['log_collections']['status'] = 'fail';
				$checks['log_collections']['error'] = $e->getMessage();
				$valid = false;
			}
		} catch (Exception) {
			$valid = false;
		}

		return [
			'valid' => $valid,
			'checks' => $checks,
		];
	}

	protected static function lintValidProperties(array $config): array
	{
		$return = [
			'message' => 'Structure is valid',
		];

		try {
			$unknown = [];

			if (!array_key_exists('logs', $config))
				throw new Exception('Config property "logs" is missing.');

			foreach ($config as $property => $value) {
				if ($property === 'logs') {
					$emptyCollections = [];
					foreach ($value as $logCollectionKey => $logCollection) {
						if (empty($logCollection)) {
							$emptyCollections[] = $logCollectionKey;
							continue;
						}

						foreach ($logCollection as $logFileKey => $logFile) {
							if (array_key_exists('type', $logFile))
								continue;

							throw new Exception('Log file "' . $logCollectionKey . '.' . $logFileKey . '" has no type property.');
						}
					}

					if (!empty($emptyCollections)) {
						$return['status'] = 'warn';
						$return['error'] = 'The following log collections have no logs: ' . implode(', ', $emptyCollections);
					}
				} elseif (!array_key_exists($property, self::getValidSettings())) {
					$unknown[] = $property;
				}
			}

			if (!isset($return['status'])) {
				if ($unknown !== []) {
					$return['status'] = 'warn';
					$return['error'] = 'Unknown config properties: ' . implode(', ', $unknown);
				} else {
					$return['status'] = 'ok';
				}
			}
		} catch (Exception $e) {
			$return['status'] = 'fail';
			$return['error'] = $e->getMessage();
		}

		return $return;
	}

	protected static function getValidSettings(string $key = null): array
	{
		$settings = [
			'debug' => [
				'type' => 'bool',
				'default' => false,
			],
			'display_logger' => [
				'type' => 'bool',
				'default' => false,
			],
			'reverse_line_order' => [
				'type' => 'bool',
				'default' => true,
			],
			'date_format' => [
				'type' => 'string',
				'default' => 'dd.MM.yyyy HH:mm:ss',
			],
			'timezone' => [
				'type' => 'string',
				'default' => 'Europe/Zurich',
			],
			'limit' => [
				'type' => 'int',
				'default' => 100,
			],
			'cache_expire' => [
				'type' => 'int',
				'default' => 300,
			],
		];

		if ($key !== null && !isset($settings[$key]))
			throw new InvalidArgumentException("Settings key '$key' is not valid");

		return $key !== null ? $settings[$key] : $settings;
	}

	protected static function lintValidSettingsValues(array $config): array
	{
		$return = [
			'message' => 'Settings values are valid',
		];

		try {
			foreach ($config as $property => $value) {
				if ($property === 'logs')
					continue;

				switch (self::getValidSettings($property)['type']) {
					case 'bool':
						if (!is_bool($value))
							throw new Exception(sprintf('"%s" must be a boolean value.', $property));
						break;
					case 'int':
						if (!is_int($value))
							throw new Exception(sprintf('"%s" must be an integer value.', $property));
						break;
				}
			}

			$return['status'] = 'ok';
		} catch (Exception $e) {
			$return['status'] = 'fail';
			$return['error'] = $e->getMessage();
		}

		return $return;
	}

	protected static function lintLogCollection(string $name, array $logCollection, bool $verifyLogFiles = false): array
	{
		$return = [
			'message' => 'Checking "' . $name . '"',
		];

		try {
			$return['status'] = 'ok';
			if ($logCollection === []) {
				$return['status'] = 'warn';
				$return['error'] = '"' . $name . '" has no log files.';
			}

			foreach ($logCollection as $logFileName => $logFile) {
				$return['sub_checks'][$logFileName] = self::lintLogFile($logFileName, $logFile, $verifyLogFiles);
				if ($return['sub_checks'][$logFileName]['status'] === 'fail')
					throw new Exception;
			}
		} catch (Exception $e) {
			$return['status'] = 'fail';
			$return['error'] = $e->getMessage();
		}

		return $return;
	}

	protected static function lintLogFile(string $name, array $logFile, bool $verifyLogFiles = false): array
	{
		$return = [
			'message' => 'Checking "' . $name . '"',
		];
		try {
			if (!array_key_exists($logFile['type'], self::getValidLogTypes()))
				throw new Exception('"' . $logFile['type'] . '" is not a supported type.');

			if ($verifyLogFiles) {
				$return['checks'][$name] = self::lintCheckFileAccessible(new LogFile($name, '', $logFile));
				if ($return['checks'][$name]['status'] === 'fail')
					throw new Exception;
			}

			$return['status'] = 'ok';
		} catch (Exception $e) {
			$return['status'] = 'fail';
			$return['error'] = $e->getMessage();
		}

		return $return;
	}

	protected static function getValidLogTypes(): array
	{
		return [
			'local' => [
				'path' => ['type' => 'string',],
				'pattern' => ['type' => 'string',],
			],
			'ftp' => [
				'host' => ['type' => 'string',],
				'username' => ['type' => 'string',],
				'password' => ['type' => 'string',],
				'path' => ['type' => 'string',],
				'pattern' => ['type' => 'string',],
			],
			'sftp' => [
				'host' => ['type' => 'string',],
				'username' => ['type' => 'string',],
				'password' => ['type' => 'string',],
				'path' => ['type' => 'string',],
				'pattern' => ['type' => 'string',],
				'private_key' => ['type' => 'string',],
				'private_key_passphrase' => ['type' => 'string',],
				'port' => ['type' => 'string',],
				'timeout' => ['type' => 'int',],
				'max_tries' => ['type' => 'int',],
				'host_fingerprint' => ['type' => 'string',],
			],
		];
	}

	protected static function lintCheckFileAccessible(LogFile $logFile): array
	{
		$result = ['message' => 'Checking if "' . $logFile->getName() . '" is accessible'];

		try {
			if (!LogFileCache::isSourceFileAccessible($logFile))
				throw new Exception('File does not exist on target file system.');

			$result['status'] = 'ok';
		} catch (Exception $e) {
			$result['status'] = 'fail';
			$result['error'] = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Returns a config property if it exists and throws an exception if not.
	 *
	 * @param string|null $property Dot-separated property (e.g. "date_format" or "logs.collection.log_file")
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 *
	 */
	public function get(?string $property = null): mixed
	{
		if ($property === null || $property === '')
			return $this->config;

		$tree = explode('.', $property);
		$node = $this->config;
		foreach ($tree as $workingNode) {
			if (!array_key_exists($workingNode, $node)) {
				$actualNode = null;
				foreach ($node as $testNodeKey => $testNode)
					if (URLify::filter($testNodeKey) === $workingNode)
						$actualNode = $testNodeKey;

				if ($actualNode === null)
					throw new InvalidArgumentException('The property "' . $property
						. '" was not found. Failed while getting node "' . $workingNode . '"');

				$workingNode = $actualNode;
			}
			$node = $node[$workingNode];
		}

		return $node;
	}
}
