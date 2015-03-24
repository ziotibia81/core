<?php
/**
 * @author Clark Tomlinson  <clark@owncloud.com>
 * @since 2/19/15, 10:02 AM
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Encryption\Hooks;


use OCP\Util as OCUtil;
use OCA\Encryption\Hooks\Contracts\IHook;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Migrator;
use OCA\Encryption\Users\Setup;
use OCP\App;
use OCP\ILogger;
use OCP\IUserSession;
use OCA\Encryption\Util;
use Test\User;

class UserHooks implements IHook {
	/**
	 * @var KeyManager
	 */
	private $keyManager;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var Setup
	 */
	private $userSetup;
	/**
	 * @var Migrator
	 */
	private $migrator;
	/**
	 * @var IUserSession
	 */
	private $user;
	/**
	 * @var Util
	 */
	private $util;

	/**
	 * UserHooks constructor.
	 *
	 * @param KeyManager $keyManager
	 * @param ILogger $logger
	 * @param Setup $userSetup
	 * @param Migrator $migrator
	 * @param IUserSession $user
	 * @param OCUtil $ocUtil
	 * @param Util $util
	 */
	public function __construct(
		KeyManager $keyManager, ILogger $logger, Setup $userSetup, Migrator $migrator, IUserSession $user, OCUtil $ocUtil, Util $util) {

		$this->keyManager = $keyManager;
		$this->logger = $logger;
		$this->userSetup = $userSetup;
		$this->migrator = $migrator;
		$this->user = $user;
		$this->util = $ocUtil;
		$this->util = $util;
	}

	/**
	 * Connects Hooks
	 *
	 * @return null
	 */
	public function addHooks() {
		OCUtil::connectHook('OC_User', 'post_login', $this, 'login');
		OCUtil::connectHook('OC_User', 'logout', $this, 'logout');
		OCUtil::connectHook('OC_User',
			'post_setPassword',
			$this,
			'setPassphrase');
		OCUtil::connectHook('OC_User',
			'pre_setPassword',
			$this,
			'preSetPassphrase');
		OCUtil::connectHook('OC_User',
			'post_createUser',
			$this,
			'postCreateUser');
		OCUtil::connectHook('OC_User',
			'post_deleteUser',
			$this,
			'postDeleteUser');
	}


	/**
	 * Startup encryption backend upon user login
	 *
	 * @note This method should never be called for users using client side encryption
	 * @param array $params
	 * @return bool
	 */
	public function login($params) {

		if (!App::isEnabled('encryption')) {
			return true;
		}

		// ensure filesystem is loaded
		// Todo: update?
		if (!\OC\Files\Filesystem::$loaded) {
			\OC_Util::setupFS($params['uid']);
		}

		// setup user, if user not ready force relogin
		if (!$this->userSetup->setupUser($params['uid'], $params['password'])) {
			return false;
		}

		$cache = $this->keyManager->init($params['password']);

		// Check if first-run file migration has already been performed
		$ready = false;
		$this->migrator->setUser($params['uid']);
		$migrationStatus = $this->migrator->getStatus($params['uid']);
		if ($migrationStatus === Migrator::$migrationOpen && $cache !== false) {
			$ready = $this->migrator->beginMigration();
		} elseif ($migrationStatus === Migrator::$migrationInProgress) {
			// refuse login as long as the initial encryption is running
			sleep(5);
			$this->user->logout();
			return false;
		}

		$result = true;

		// If migration not yet done
		if ($ready) {

			// Encrypt existing user files
			try {
				$result = $this->util->encryptAll('/' . $params['uid'] . '/' . 'files');
			} catch (\Exception $ex) {
				$this->logger->critical('Encryption library initial encryption failed! Error: ' . $ex->getMessage());
				$result = false;
			}

			if ($result) {
				$this->logger->info('Encryption Library Encryption of existing files belonging to "' . $params['uid'] . '" completed');
				// Register successful migration in DB
				$this->migrator->finishMigration();
			} else {
				$this->logger->critical('Encryption library initial encryption failed');
				$this->migrator->resetMigrationStatus();
				$this->user->logout();
			}
		}

		return $result;
	}

	/**
	 * remove keys from session during logout
	 */
	public function logout() {
		KeyManager::$cacheFactory->clear();
	}

	/**
	 * setup encryption backend upon user created
	 *
	 * @note This method should never be called for users using client side encryption
	 * @param array $params
	 */
	public function postCreateUser($params) {

		if (App::isEnabled('encryption')) {
			$this->userSetup->setupUser($params['uid'], $params['password']);
		}
	}

	/**
	 * cleanup encryption backend upon user deleted
	 *
	 * @param array $params : uid, password
	 * @note This method should never be called for users using client side encryption
	 */
	public function postDeleteUser($params) {

		if (App::isEnabled('encryption')) {
			$this->keyManager->deletePublicKey($params['uid']);
		}
	}

	/**
	 * If the password can't be changed within ownCloud, than update the key password in advance.
	 *
	 * @param array $params : uid, password
	 * @return bool
	 */
	public function preSetPassphrase($params) {
		if (App::isEnabled('encryption')) {

			if (!$this->user->getUser()->canChangePassword()) {
				if (App::isEnabled('encryption') === false) {
					return true;
				}
				$this->keyManager->setPassphrase($params,
					$this->user,
					$this->util);
			}
		}
	}


	/**
	 * after password reset we create a new key pair for the user
	 *
	 * @param array $params
	 */
	public function postPasswordReset($params) {
		$password = $params['password'];

		$this->keyManager->replaceUserKeys($params['uid']);
		$this->userSetup->setupServerSide($params['uid'], $password);
	}
}
