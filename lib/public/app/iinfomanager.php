<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\App;

interface IInfoManager {
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
	 * Check if an app is of a specific type
	 *
	 * @param string $appId
	 * @param string $type
	 * @return bool
	 */
	public function isType($appId, $type);

	/**
	 * Cache the types of an app
	 *
	 * @param string $appId
	 * @param string[] $types
	 */
	public function cacheTypes($appId, $types);

	/**
	 * @param string $appId
	 * @return \OCP\App\IInfo
	 */
	public function getAppInfo($appId);
}
