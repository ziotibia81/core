<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\Authentication;

interface IManager {
	/**
	 * @param \OCP\Authentication\IProvider $provider
	 * @return mixed
	 */
	public function registerProvider($provider);

	/**
	 * Try to login using the available providers
	 *
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return bool
	 */
	public function tryAuth($server, $post, $cookie);

	/**
	 * Get the link for triggering the logout
	 *
	 * @return string with one or more HTML attributes
	 */
	public function getLogoutLink();
}
