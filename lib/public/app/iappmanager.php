<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\App;

interface IAppManager {
	/**
	 * Check if an app is enabled for user
	 *
	 * @param string $appId
	 * @param \OCP\IUser $user (optional) if not defined, the currently loggedin user will be used
	 * @return bool
	 */
	public function isEnabledForUser($appId, $user = null);

	/**
	 * Check if an app is installed in the instance
	 *
	 * @param string $appId
	 * @return bool
	 */
	public function isInstalled($appId);

	/**
	 * Enable an app for every user
	 *
	 * @param string $appId
	 */
	public function enableApp($appId);

	/**
	 * Enable an app only for specific groups
	 *
	 * @param string $appId
	 * @param \OCP\IGroup[] $groups
	 */
	public function enableAppForGroups($appId, $groups);

	/**
	 * Disable an app for every user
	 *
	 * @param string $appId
	 */
	public function disableApp($appId);

	/**
	 * List all apps enabled for any user
	 *
	 * @return string[]
	 */
	public function listInstalledApps();

	/**
	 * List all apps enabled for a specific user
	 *
	 * @param \OCP\IUser $user
	 * @return string[]
	 */
	public function listAppsEnabledForUser($user = null);

	/**
	 * Get the version of an app that's currently installed
	 *
	 * Note that this might be lower then the latest code version
	 *
	 * @param string $appId
	 * @return string
	 */
	public function getInstalledVersion($appId);

	/**
	 * Get the version of an app as defined by the code
	 *
	 * Note that this might be newer than the installed version
	 *
	 * @param string $appId
	 * @return string
	 */
	public function getAppVersion($appId);

	/**
	 * @param string $appId
	 * @return \OCP\App\IInfo
	 */
	public function getAppInfo($appId);
}
