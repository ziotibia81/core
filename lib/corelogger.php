<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC;

use Clockwork\DataSource\DataSource;
use Clockwork\Request\Log as CWLog;
use Psr\Log\LogLevel;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Class CoreLogger
 *
 * Log internal internal events for debugging purposes
 *
 * @package OC
 */
class CoreLogger extends DataSource implements SQLLogger {
	/**
	 * \Clockwork\Request\Timeline $timeline
	 */
	protected $queries;

	/**
	 * @var int $lastQueryId
	 */
	protected $lastQueryId;

	/**
	 * @var \Clockwork\Request\Timeline $timeline
	 */
	protected $timeline;

	/**
	 * @var bool $skip
	 */
	protected $skip;

	/**
	 * @var \Clockwork\Request\Log $log
	 */
	protected $log;

	public function __construct() {
		$this->timeline = new Timeline();
		$this->queries = new Timeline();
		$this->log = new CWLog();
		$this->attachHooks();
	}

	/**
	 * Adds request method, uri, controller, headers, response status, timeline data and log entries to the request
	 */
	public function resolve(Request $request) {
		$request->timelineData = $this->timeline->finalize($request->time);
		$request->databaseQueries = array_merge($request->databaseQueries, $this->getQueries());
		$request->log = $this->log->toArray();

		return $request;
	}

	/**
	 * @param string $name
	 * @param string $description
	 */
	public function startEvent($name, $description = '') {
		$this->timeline->startEvent($name, $description);
	}

	/**
	 * @param string $name
	 */
	public function endEvent($name) {
		$this->timeline->endEvent($name);
	}

	/**
	 * Logs a SQL statement somewhere.
	 *
	 * @param string $query The SQL to be executed.
	 * @param array $params The SQL parameters.
	 * @param array $types The SQL parameter types.
	 * @return void
	 */
	public function startQuery($query, array $params = null, array $types = null) {
		$this->lastQueryId++;
		$this->queries->startEvent($this->lastQueryId, $this->createRunnableQuery($query, $params));
	}

	public function stopQuery() {
		$this->queries->endEvent($this->lastQueryId);
	}

	/**
	 * @return \Clockwork\Request\Timeline
	 */
	public function getTimeLine() {
		return $this->timeline;
	}

	/**
	 * @return array[]
	 *
	 * [
	 *       [
	 *           'query' => string
	 *           'duration => int
	 *       ]
	 * ]
	 */
	public function getQueries() {
		$queries = array();
		$queryTimeline = $this->queries->toArray();
		foreach ($queryTimeline as $query) {
			$queries[] = array(
				'query' => $query['description'],
				'duration' => $query['duration']
			);
		}
		return $queries;
	}

	/**
	 * Takes a query and array of bindings as arguments, returns runnable query with upper-cased keywords
	 *
	 * @param string $query
	 * @param array $params
	 * @return string
	 */
	protected function createRunnableQuery($query, $params) {
		if (!$params) {
			return $query;
		}
		foreach ($params as $param) {
			if (is_string($param)) { //poor mans quoting
				$param = '"' . $param . '"';
			}
			$query = preg_replace('/\?/', $param, $query, 1);
		}

		//highlight keywords
		$keywords = array('select', 'insert', 'update', 'delete', 'where', 'from', 'limit', 'is', 'null', 'having', 'group by', 'order by', 'asc', 'desc');
		$regexp = '/\b' . implode('\b|\b', $keywords) . '\b/i';

		$query = preg_replace_callback($regexp, function ($match) {
			return strtoupper($match[0]);
		}, $query);

		return $query;
	}

	public function skip() {
		$this->skip = true;
	}

	/**
	 * @return bool
	 */
	public function getSkip() {
		return $this->skip;
	}

	public function attachHooks() {
		\OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'writeHook');
		\OC_Hook::connect('OC_Filesystem', 'post_rename', $this, 'renameHook');
		\OC_Hook::connect('OC_Filesystem', 'post_copy', $this, 'copyHook');
	}

	public function writeHook($params) {
		$this->log->log('Write ' . $params['path'], LogLevel::INFO);
	}

	public function renameHook($params) {
		$this->log->log('Rename ' . $params['oldpath'] . ' to ' . $params['newpath'], LogLevel::INFO);
	}

	public function copyHook($params) {
		$this->log->log('Rename ' . $params['oldpath'] . ' to ' . $params['newpath'], LogLevel::INFO);
	}

	public function log($message, $level = LogLevel::INFO) {
		$stack = xdebug_get_function_stack();
		array_pop($stack);
		$stackMessage = '';
		$i = 0;
		foreach ($stack as $stackLine) {
			$i++;
			$stackMessage .= "\n" . $i . '. ';
			$file = substr($stackLine['file'], strlen(\OC::$SERVERROOT));
			if (isset($stackLine['class'])) {
				$function = $stackLine['class'] . '::' . $stackLine['function'];
			} elseif (isset($stackMessage['function'])) {
				$function = $stackLine['function'];
			} else {
				$function = '';
			}
			$stackMessage .= $function . ' in ' . $file;
			if (isset($stackLine['line'])) {
				$stackMessage .= ' line ' . $stackLine['line'];
			}
		}
		$this->log->log($level, $message . $stackMessage);
	}
}
