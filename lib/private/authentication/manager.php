<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Authentication;

use OCP\Authentication\IManager;

class Manager implements IManager {
	/**
	 * @var \OCP\Authentication\IProvider[]
	 */
	protected $providers;

	/**
	 * @param \OCP\Authentication\IProvider $provider
	 * @return mixed
	 */
	public function registerProvider($provider) {
		$this->providers[] = $provider;
	}

	/**
	 * Try to login using the available providers
	 *
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return bool
	 */
	public function tryAuth($server, $post, $cookie) {
		foreach ($this->providers as $provider) {
			if ($provider->tryAuth($server, $post, $cookie)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the link for triggering the logout
	 *
	 * @return string with one or more HTML attributes
	 */
	public function getLogoutLink() {
		foreach ($this->providers as $provider) {
			if ($result = $provider->getLogoutLink()) {
				return $result;
			}
		}
		return 'href="' . \OC_Helper::linkTo('', 'index.php') . '?logout=true&requesttoken=' . \OC_Util::callRegister() . '"';
	}
}
