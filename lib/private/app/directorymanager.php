<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

use OCP\IConfig;

class DirectoryManager {
	/**
	 * @var \OCP\IConfig
	 */
	private $config;

	/**
	 * @var \OC\App\Directory[]
	 */
	private $dirs;

	/**
	 * @param \OCP\IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Get all configured app directories
	 *
	 * @return \OC\App\Directory[]
	 */
	public function getAppDirectories() {
		if (!$this->dirs) {
			$dirs = $this->config->getSystemValue('apps_paths', array());
			$this->dirs = array_map(function ($dir) {
				return new Directory($dir['path'], $dir['url'], $dir['writable']);
			}, $dirs);
		}
		return $this->dirs;
	}

	/**
	 * Get the app directory an app is installed in
	 *
	 * @param string $appId
	 * @return \OC\App\Directory or null if the app isn't installed in any directory
	 */
	public function getDirectoryForApp($appId) {
		$dirs = $this->getAppDirectories();
		foreach ($dirs as $dir) {
			if ($dir->hasApp($appId)) {
				return $dir;
			}
		}
		return null;
	}

	/**
	 * List all apps installed in all app directories
	 *
	 * @return string[]
	 */
	public function listApps() {
		$dirs = $this->getAppDirectories();
		$apps = array_reduce($dirs, function ($apps, Directory $dir) {
			return array_merge($apps, $dir->listApps());
		}, array());
		sort($apps);
		return array_values($apps);
	}
}
