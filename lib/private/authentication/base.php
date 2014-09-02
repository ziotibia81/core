<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Authentication;

class Base {
	/**
	 * @var \OCP\ISession
	 */
	protected $session;

	/**
	 * @var \OC\User\Session
	 */
	protected $userSession;

	/**
	 * @var \OCP\IConfig
	 */
	protected $config;

	/**
	 * @var int
	 */
	protected $time;

	/**
	 * @param \OCP\ISession $session
	 * @param \OCP\IUserSession $userSession
	 * @param \OCP\IConfig $config
	 * @param int $time
	 */
	public function __construct($session, $userSession, $config, $time) {
		$this->session = $session;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->time = $time;
	}

	/**
	 * Remove outdated and therefore invalid tokens for a user
	 *
	 * @param string $user
	 */
	protected function cleanupLoginTokens($user) {
		$cutoff = $this->time - $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		$tokens = $this->config->getUserKeys($user, 'login_token');
		foreach ($tokens as $token) {
			$time = $this->config->getUserValue($user, 'login_token', $token);
			if ($time < $cutoff) {
				$this->config->deleteUserValue($user, 'login_token', $token);
			}
		}
	}

	/**
	 * Get the link for triggering the logout
	 *
	 * @return string with one or more HTML attributes.
	 */
	public function getLogoutLink() {
		return false;
	}
}
