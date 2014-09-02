<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Authentication;

use OCP\Authentication\Exception;
use OCP\Authentication\IProvider;

/**
 * Auth provider for remember-me cookie
 */
class Cookie extends Base implements IProvider {
	/**
	 * @param array $server the $_SERVER environment
	 * @param array $post the $_POST data
	 * @param array $cookie the $_COOKIE data
	 * @return int either \OCP\Authentication\IProvider::NOT_APPLICABLE, \OCP\Authentication\IProvider::SUCCESS_CONTINUE
	 *         or \OCP\Authentication\IProvider::SUCCESS_REDIRECT
	 *
	 * @throws \OCP\Authentication\Exception
	 */
	public function tryAuth(&$server, $post, $cookie) {
		if (!isset($_COOKIE["oc_remember_login"])
			|| !isset($_COOKIE["oc_token"])
			|| !isset($_COOKIE["oc_username"])
			|| !$_COOKIE["oc_remember_login"]
			|| !\OC_Util::rememberLoginAllowed()
		) {
			return IProvider::NOT_APPLICABLE;
		}

		if ($this->userSession->getManager()->userExists($_COOKIE['oc_username'])) {
			$this->cleanupLoginTokens($_COOKIE['oc_username']);
			// verify whether the supplied "remember me" token was valid
			$granted = $this->userSession->loginWithCookie(
				$_COOKIE['oc_username'], $_COOKIE['oc_token']);
			if ($granted === true) {
				return IProvider::SUCCESS_REDIRECT;
			}
		}
		$this->userSession->unsetMagicInCookie();
		throw new Exception('invalidcookie');
	}
}
