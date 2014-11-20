<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

use OC\Diagnostics\NullEventLogger;
use OCP\App\IAppManager;
use OCP\App\IInfo;
use OCP\App\IInfoManager;
use OCP\IUser;
use OCP\App\IAppLoader;

class Loader implements IAppLoader {
	/**
	 * @var \OC\App\DirectoryManager
	 */
	private $directoryManager;

	/**
	 * @var \OCP\App\IAppManager
	 */
	private $appManager;

	/**
	 * @var \OCP\App\IInfoManager
	 */
	private $infoManager;

	/**
	 * @var \OCP\Diagnostics\IEventLogger
	 */
	private $eventLogger;

	/**
	 * @var string
	 */
	private $loadedApps = array();

	/**
	 * @param \OCP\App\IAppManager $appManager
	 * @param \OC\App\DirectoryManager $directoryManager
	 * @param \OCP\App\IInfoManager $infoManager
	 * @param \OCP\Diagnostics\IEventLogger $eventLogger
	 */
	function __construct(IAppManager $appManager, DirectoryManager $directoryManager, IInfoManager $infoManager, $eventLogger = null) {
		$this->appManager = $appManager;
		$this->directoryManager = $directoryManager;
		$this->infoManager = $infoManager;
		if (is_null($eventLogger)) {
			$this->eventLogger = new NullEventLogger();
		} else {
			$this->eventLogger = $eventLogger;
		}
	}

	/**
	 * Load a single app
	 *
	 * @param string $appId
	 * @throws \OC\NeedsUpdateException
	 */
	public function loadApp($appId) {
		if (array_search($appId, $this->loadedApps) !== false) {
			return;
		}
		$installedVersion = $this->infoManager->getInstalledVersion($appId);
		$version = $this->infoManager->getAppVersion($appId);
		if (version_compare($version, $installedVersion, '>')) {
			throw new \OC\NeedsUpdateException();
		}
		$this->loadedApps[] = $appId;
		$dir = $this->directoryManager->getDirectoryForApp($appId);
		if (!$dir) {
			return;
		}
		$appFile = $dir->getPath() . '/' . $appId . '/appinfo/app.php';
		if (file_exists($appFile)) {
			$this->eventLogger->start('load_app_' . $appId, 'Load app: ' . $appId);
			$this->requireFile($appFile);
			$this->eventLogger->end('load_app_' . $appId);
		}
	}

	/**
	 * Provide a clean scope for the include
	 *
	 * @param string $appFile
	 */
	private function requireFile($appFile){
		ob_start();
		require $appFile;
		ob_end_clean();
	}

	/**
	 * @param string[] $apps
	 */
	private function loadMultiple($apps) {
		array_walk($apps, array($this, 'loadApp'));
	}

	/**
	 * Load all installed apps
	 */
	public function loadInstalledApps() {
		$this->loadMultiple($this->appManager->listInstalledApps());
	}

	/**
	 * Load all apps enabled for a user
	 *
	 * @param \OCP\IUser $user
	 */
	public function loadAppsEnabledForUser(IUser $user) {
		$this->loadMultiple($this->appManager->listAppsEnabledForUser($user));
	}

	/**
	 * Load all apps of a specific type
	 *
	 * @param string $type
	 */
	public function loadAppsWithType($type) {
		$allApps = $this->appManager->listInstalledApps();
		$infoManager = $this->infoManager;
		$appIds = array_filter($allApps, function ($appId) use ($infoManager, $type) {
			return $infoManager->isType($appId, $type);
		});
		$this->loadMultiple($appIds);
	}
}
