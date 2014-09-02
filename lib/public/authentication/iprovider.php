<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCP\Authentication;

interface IProvider {
	/**
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return bool
	 */
	public function tryAuth(&$server, $post, $cookie);

	/**
	 * Get the link for triggering the logout
	 *
	 * @return string | false with one or more HTML attributes or false if the default should be used
	 */
	public function getLogoutLink();
}
