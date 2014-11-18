<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\App;

use OCP\IUser;

interface IAppLoader {
	/**
	 * Load a single app
	 *
	 * @param string $appId
	 */
	public function loadApp($appId);

	/**
	 * Load all installed apps
	 */
	public function loadInstalledApps();

	/**
	 * Load all apps enabled for a user
	 *
	 * @param \OCP\IUser $user
	 */
	public function loadAppsEnabledForUser(IUser $user);

	/**
	 * Load all apps of a specific type
	 *
	 * @param string $type
	 */
	public function loadAppsWithType($type);
}
