<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\DB;

/**
 * This handles the way we use to write queries, into something that can be
 * handled by the database abstraction layer.
 */
class Adapter {

	/**
	 * @var \OC\DB\Connection $conn
	 */
	protected $conn;

	public function __construct($conn) {
		$this->conn = $conn;
	}

	/**
	 * @param string $table name
	 * @return int id of last insert statement
	 */
	public function lastInsertId($table) {
		return $this->conn->realLastInsertId($table);
	}

	/**
	 * @param string $statement that needs to be changed so the db can handle it
	 * @return string changed statement
	 */
	public function fixupStatement($statement) {
		return $statement;
	}

	/**
	 * Insert a row if the matching row does not exists.
	 *
	 * @param string $table The table name (will replace *PREFIX* with the actual prefix)
	 * @param array $input data that should be inserted into the table  (column name => value)
	 * @param array|null $compare List of values that should be checked for "if not exists"
	 *				If this is null or an empty array, all keys of $input will be compared
	 *				Please note: text fields (clob) must not be used in the compare array
	 * @return int number of inserted rows
	 * @throws \Doctrine\DBAL\DBALException
	 */
	public function insertIfNotExist($table, $input, array $compare = null) {
		if (empty($compare)) {
			$compare = array_keys($input);
		}
		$query = 'INSERT INTO `' .$table . '` (`'
			. implode('`,`', array_keys($input)) . '`) SELECT '
			. str_repeat('?,', count($input)-1).'? ' // Is there a prettier alternative?
			. 'FROM `' . $table . '` WHERE ';
		$subquery = '';
		$subqueryParams = [];

		$inserts = array_values($input);
		foreach($compare as $key) {
			$subquery .= '`' . $key . '`';
			if (is_null($input[$key])) {
				$subquery .= ' IS NULL AND ';
			} else {
				$inserts[] = $input[$key];
				$subqueryParams[] = $input[$key];
				$subquery .= ' = ? AND ';
			}
		}
		$subquery = substr($subquery, 0, strlen($subquery) - 5);
		$query .= $subquery;
		$query .= ' HAVING COUNT(*) = 0';

		try {
			$return = $this->conn->executeUpdate($query, $inserts);
		} catch (\Exception $e) {
			\OC::$server->getLogger()->error($e);
			$trace = array_map(function ($entry) {
				unset($entry['object']);
				unset($entry['args']);
				return $entry;
			}, debug_backtrace());
			\OC::$server->getLogger()->error('backtrace#' . serialize($trace));
			throw $e;
		}

		return $return;
	}

	function debug_string_backtrace() {
		ob_start();
		debug_print_backtrace();
		$trace = ob_get_contents();
		ob_end_clean();

		// Remove first item from backtrace as it's this function which
		// is redundant.
		$trace = preg_replace ('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '', $trace, 1);

		// Renumber backtrace items.
		$trace = preg_replace ('/^#(\d+)/me', '\'#\' . ($1 - 1)', $trace);

		return $trace;
	}
}
