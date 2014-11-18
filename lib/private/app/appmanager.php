<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class AppManager implements IAppManager {
	/**
	 * @var \OCP\IUserSession
	 */
	private $userSession;

	/**
	 * @var \OCP\IAppConfig
	 */
	private $appConfig;

	/**
	 * @var \OCP\IGroupManager
	 */
	private $groupManager;

	/**
	 * @var \OC\App\DirectoryManager
	 */
	private $directoryManager;

	/**
	 * @var string[] $appId => $enabled
	 */
	private $appsEnabledCache;

	/**
	 * @var string[] $appId => $version
	 */
	private $appsVersionsCache;

	/**
	 * @param \OCP\IUserSession $userSession
	 * @param \OCP\IAppConfig $appConfig
	 * @param \OCP\IGroupManager $groupManager
	 * @param \OC\App\DirectoryManager $directoryManager
	 */
	public function __construct(IUserSession $userSession, IAppConfig $appConfig, IGroupManager $groupManager, DirectoryManager $directoryManager) {
		$this->userSession = $userSession;
		$this->appConfig = $appConfig;
		$this->groupManager = $groupManager;
		$this->directoryManager = $directoryManager;
	}

	/**
	 * @return string[] $appId => $enabled
	 */
	private function getEnabledValues() {
		if (!$this->appsEnabledCache) {
			$values = $this->appConfig->getValues(false, 'enabled');
			$this->appsEnabledCache = array_filter($values, function ($value) {
				return $value !== 'no';
			});
			ksort($this->appsEnabledCache);
		}
		return $this->appsEnabledCache;
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
	 * Check if an app is enabled for user
	 *
	 * @param string $appId
	 * @param \OCP\IUser $user (optional) if not defined, the currently logged in user will be used
	 * @return bool
	 */
	public function isEnabledForUser($appId, $user = null) {
		if (is_null($user)) {
			$user = $this->userSession->getUser();
		}
		$installedApps = $this->getEnabledValues();
		if (isset($installedApps[$appId])) {
			$enabled = $installedApps[$appId];
			if ($enabled === 'yes') {
				return true;
			} elseif (is_null($user)) {
				return false;
			} else {
				$groupIds = json_decode($enabled);
				$userGroups = $this->groupManager->getUserGroupIds($user);
				foreach ($userGroups as $groupId) {
					if (array_search($groupId, $groupIds) !== false) {
						return true;
					}
				}
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Check if an app is installed in the instance
	 *
	 * @param string $appId
	 * @return bool
	 */
	public function isInstalled($appId) {
		$installedApps = $this->getEnabledValues();
		return isset($installedApps[$appId]);
	}

	/**
	 * Enable an app for every user
	 *
	 * @param string $appId
	 */
	public function enableApp($appId) {
		$this->appConfig->setValue($appId, 'enabled', 'yes');
	}

	/**
	 * Enable an app only for specific groups
	 *
	 * @param string $appId
	 * @param \OCP\IGroup[] $groups
	 */
	public function enableAppForGroups($appId, $groups) {
		$groupIds = array_map(function ($group) {
			/** @var \OCP\IGroup $group */
			return $group->getGID();
		}, $groups);
		$this->appConfig->setValue($appId, 'enabled', json_encode($groupIds));
	}

	/**
	 * Disable an app for every user
	 *
	 * @param string $appId
	 */
	public function disableApp($appId) {
		$this->appConfig->setValue($appId, 'enabled', 'no');
	}

	/**
	 * List all apps enabled for any user
	 *
	 * @return string[]
	 */
	public function listInstalledApps() {
		return array_keys($this->getEnabledValues());
	}

	/**
	 * List all apps enabled for a specific user
	 *
	 * @param \OCP\IUser $user
	 * @return string[]
	 */
	public function listAppsEnabledForUser($user = null) {
		$apps = $this->listInstalledApps();
		$manager = $this;
		return array_filter($apps, function ($app) use ($manager, $user) {
			$manager->isEnabledForUser($app, $user);
		});
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
		$version = $this->getVersionValues();
		return isset($version[$appId]) ? $version[$appId] : '0.0.0';
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
		$file = $directory->getPath() . '/' . $appId . '/appinfo/version';
		return file_exists($file) ? file_get_contents($file) : '0.0.0';
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
