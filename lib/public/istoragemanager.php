<?php
/**
 * Copyright (c) 2014 Jörn Friedrich Dreyer jfd@butonic.de
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 *
 */

/**
 * Public interface of ownCloud for apps to use.
 * Storage manager interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes
namespace OCP;

interface IStorageManager {

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return \OCP\Files\Folder
	 */
	public function getRootFolder();

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder
	 */
	function getUserFolder($userId = null);

	/**
	 * Returns an app-specific view in ownClouds data directory
	 *
	 * @return \OCP\Files\Folder
	 */
	function getAppFolder();

}
