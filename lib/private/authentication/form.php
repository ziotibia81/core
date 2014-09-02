<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Authentication;

use OCP\Authentication\IProvider;

/**
 * Auth provider for form login
 */
class Form extends Base implements IProvider {
	/**
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return bool
	 */
	public function tryAuth(&$server, $post, $cookie) {
		if (!isset($post['user']) || !isset($post['password'])) {
			return false;
		}

		if ($this->userSession->login($post['user'], $post['password'])) {
			// setting up the time zone
			if (isset($post['timezone-offset'])) {
				$this->session->set('timezone', $post['timezone-offset']);
			}

			$user = $this->userSession->getUser();
			$this->cleanupLoginTokens($user->getUID());
			if (!empty($post['remember_login'])) {
				$token = \OC_Util::generateRandomBytes(32); //TODO DI
				$this->config->setUserValue($user->getUID(), 'login_token', $token, $this->time);
				$this->userSession->setMagicInCookie($user->getUID(), $token);
			} else {
				$this->userSession->unsetMagicInCookie();
			}
			return true;
		}
		return false;
	}
}
