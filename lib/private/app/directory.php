<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

/**
 * A local directory with one or more apps
 */
class Directory {
	/**
	 * @var string
	 */
	private $path;

	/**
	 * @var string
	 */
	private $url;

	/**
	 * @var bool
	 */
	private $writable;

	/**
	 * @param string $path
	 * @param string $url
	 * @param string $writable
	 */
	function __construct($path, $url, $writable) {
		$this->path = $path;
		$this->url = $url;
		$this->writable = $writable;
	}

	/**
	 * Get the local path of this directory
	 *
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * Get the url relative to the owncloud installation
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Whether this app directory is writable
	 *
	 * @return boolean
	 */
	public function isWritable() {
		return $this->writable;
	}

	/**
	 * List all apps installed in this directory
	 *
	 * @return string[]
	 */
	public function listApps() {
		$files = scandir($this->getPath());
		$apps = array_filter($files, array($this, 'hasApp'));
		sort($apps);
		return array_values($apps);
	}

	/**
	 * Check if an app is installed in this directory
	 *
	 * @param string $appId
	 * @return bool
	 */
	public function hasApp($appId) {
		return file_exists($this->getPath() . '/' . $appId . '/appinfo/app.php');
	}
}
