<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

use OCP\App\IInfoManager;
use OCP\IAppConfig;

class InfoManager implements IInfoManager {
	/**
	 * @var \OC\App\DirectoryManager
	 */
	private $directoryManager;

	/**
	 * @var \OCP\IAppConfig
	 */
	private $appConfig;

	/**
	 * @var string[] $appId => $version
	 */
	private $appsVersionsCache;

	/**
	 * @var string[] $appId => $type[]
	 */
	private $appsTypesCache;

	public function __construct(DirectoryManager $directoryManager, IAppConfig $appConfig) {
		$this->directoryManager = $directoryManager;
		$this->appConfig = $appConfig;
	}

	/**
	 * @return string[] $appId => $type[]
	 */
	private function getTypeValues() {
		if (!$this->appsTypesCache) {
			$values = $this->appConfig->getValues(false, 'types');
			$this->appsTypesCache = array_map(function ($types) {
				return explode(',', $types);
			}, $values);
			ksort($this->appsTypesCache);
		}
		return $this->appsTypesCache;
	}

	/**
	 * @return string[] $appId => $version
	 */
	private function getVersionValues() {
		if (!$this->appsVersionsCache) {
			$this->appsVersionsCache = $this->appConfig->getValues(false, 'installed_version');
			ksort($this->appsVersionsCache);
		}
		return $this->appsVersionsCache;
	}

	/**
	 * Get the version of an app that's currently installed
	 *
	 * Note that this might be lower then the latest code version
	 *
	 * @param string $appId
	 * @return string
	 */
	public function getInstalledVersion($appId) {
		$versions = $this->getVersionValues();
		$version = isset($versions[$appId]) ? $versions[$appId] : '0.0.0';
		if (!$version) {
			$version = '0.0.0';
		}
		return $version;
	}

	/**
	 * Get the version of an app as defined by the code
	 *
	 * Note that this might be newer than the installed version
	 *
	 * @param string $appId
	 * @return string
	 */
	public function getAppVersion($appId) {
		$directory = $this->directoryManager->getDirectoryForApp($appId);
		if (!$directory) {
			return '0.0.0';
		}
		$versionFile = $directory->getPath() . '/' . $appId . '/appinfo/version';
		if (file_exists($versionFile)) {
			return trim(file_get_contents($versionFile));
		} else {
			$infoFile = $directory->getPath() . '/' . $appId . '/appinfo/info.xml';
			// extract version without having to parse the whole xml file
			$info = file_get_contents($infoFile);
			if (!strpos($info, '<version>')) {
				return '0.0.0';
			}
			list(, $info) = explode('<version>', $info);
			list($version) = explode('</version>', $info);
			return trim($version);
		}
	}

	/**
	 * Check if an app is of a specific type
	 *
	 * @param string $appId
	 * @param string $type
	 * @return bool
	 */
	public function isType($appId, $type) {
		$allTypes = $this->getTypeValues();
		if (isset($allTypes[$appId])) {
			return array_search($type, $allTypes[$appId]) !== false;
		} else {
			// if it's not cached in the db, read from the info.xml
			$info = $this->getAppInfo($appId);
			return $info->isType($type);
		}
	}

	/**
	 * Cache the types of an app
	 *
	 * @param string $appId
	 * @param string[] $types
	 */
	public function cacheTypes($appId, $types) {
		$appTypes = implode(',', $types);
		$this->appConfig->setValue($appId, 'types', $appTypes);
	}

	/**
	 * @param string $appId
	 * @return \OCP\App\IInfo
	 */
	public function getAppInfo($appId) {
		$appDir = $this->directoryManager->getDirectoryForApp($appId);
		if (!$appDir) {
			return null;
		}
		return new Info($appId, $appDir->getPath() . '/' . $appId, $this->getAppVersion($appId), $this->getInstalledVersion($appId));
	}
}
