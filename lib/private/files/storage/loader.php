<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Storage;

class Loader {
	/**
	 * @var callable[] $storageWrappers
	 */
	private $storageWrappers = array();

	/**
	 * allow modifier storage behaviour by adding wrappers around storages
	 *
	 * $callback should be a function of type (string $mountPoint, Storage $storage) => Storage
	 *
	 * @param string $wrapperName
	 * @param callable $callback
	 */
	public function addStorageWrapper($wrapperName, $callback) {
		$this->storageWrappers[$wrapperName] = $callback;
	}

	/**
	 * @param string $mountPoint
	 * @param string $class
	 * @param array $arguments
	 * @return \OC\Files\Storage\Storage
	 */
	public function load($mountPoint, $class, $arguments) {
		return $this->wrap($mountPoint, new $class($arguments));
	}

	/**
	 * @param string $mountPoint
	 * @param \OC\Files\Storage\Storage $storage
	 * @return \OC\Files\Storage\Storage
	 */
	public function wrap($mountPoint, $storage) {
		foreach ($this->storageWrappers as $wrapper) {
			$result = $wrapper($mountPoint, $storage);
			if ($result instanceof Storage) {
				$storage = $result;
			}
		}
		return $storage;
	}
}
