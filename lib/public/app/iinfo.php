<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\App;

interface IInfo {
	/**
	 * Get the id of the app
	 *
	 * @return string
	 */
	public function getId();

	/**
	 * Get the path where the app is installed
	 *
	 * @return string
	 */
	public function getAppPath();

	/**
	 * Check if the app is a specfic type
	 *
	 * @param string $type
	 * @return bool
	 */
	public function isType($type);

	/**
	 * Get the description of the app
	 *
	 * @return string
	 */
	public function getDescription();

	/**
	 * Get the documentation links for the app
	 *
	 * @return string[]
	 */
	public function getDocumentation();

	/**
	 * Get the licence of the app
	 *
	 * @return string
	 */
	public function getLicence();

	/**
	 * Get the name of the app
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Get the author of the app
	 *
	 * @return string
	 */
	public function getAuthor();

	/**
	 * Get the public routes registered by the app
	 *
	 * @return string[]
	 */
	public function getPublic();

	/**
	 * Get the remote routes registered by the app
	 *
	 * @return string[]
	 */
	public function getRemote();

	/**
	 * Get the version of the code
	 *
	 * Note that this might be higher than the installed version if the app needs to be updated
	 *
	 * @return string
	 */
	public function getVersion();

	/**
	 * Get the version of the app that is installed
	 *
	 * Note that this might be lower then the version of the code if the app needs to be updated
	 *
	 * @return string
	 */
	public function getInstalledVersion();

	/**
	 * Check if the app needs to be updated
	 *
	 * @return bool
	 */
	public function needsUpdate();

	/**
	 * Check whether the app is a shipped app
	 *
	 * @return bool
	 */
	public function isShipped();

	/**
	 * Check if the app is compatible with a specific version of ownCloud
	 *
	 * @param string $ocVersion
	 * @return bool
	 */
	public function isCompatible($ocVersion);
}
