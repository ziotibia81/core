<?php
/**
 * Copyright (c) 2014 Jörn Friedrich Dreyer jfd@butonic.de
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 *
 */
namespace OC;

use OC\Files\Node\Root;
use OC\Files\View;
use OCP\IStorageManager;
use OCP\IUserManager;
use OCP\IUserSession;

class StorageManager implements IStorageManager {

	/**
	 * @var IUserManager $userManager
	 */
	protected $userManager;

	/**
	 * @var IUserSession $userSession
	 */
	protected $userSession;

	/**
	 * @param IUserManager $userManager
	 * @param IUserSession $userSession
	 */
	public function __construct(IUserManager $userManager, IUserSession $userSession) {
		$this->userManager = $userManager;
		$this->userSession = $userSession;
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return \OCP\Files\Folder
	 */
	public function getRootFolder() {
		$userId = \OC_User::getUser();
		$user = $this->userManager->get($userId);
		$manager = \OC\Files\Filesystem::getMountManager();
		$view = new View();
		return new Root($manager, $view, $user);
	}

	/**
	 * @var array $userFolderCache
	 */
	private $userFolderCache = array();

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder
	 */
	function getUserFolder($userId = null) {
		if($userId === null) {
			$user = $this->userSession->getUser();
			if (!$user) {
				return null;
			}
			$userId = $user->getUID();
		} else {
			$user = $this->userManager->get($userId);
		}

		if (empty($this->userFolderCache[$userId])) {
			$dir = '/' . $userId;
			$root = $this->getRootFolder();
			$folder = null;

			if (!$root->nodeExists($dir)) {
				$folder = $root->newFolder($dir);
			} else {
				$folder = $root->get($dir);
			}

			$dir = '/files';
			if (!$folder->nodeExists($dir)) {
				$folder = $folder->newFolder($dir);

				if (\OCP\App::isEnabled('files_encryption')) {
					// disable encryption proxy to prevent recursive calls
					$proxyStatus = \OC_FileProxy::$enabled;
					\OC_FileProxy::$enabled = false;
				}

				\OC_Util::copySkeleton($user, $folder);

				if (\OCP\App::isEnabled('files_encryption')) {
					// re-enable proxy - our work is done
					\OC_FileProxy::$enabled = $proxyStatus;
				}
			} else {
				$folder = $folder->get($dir);
			}
			$this->userFolderCache[$userId] = $folder;
		}

		return $this->userFolderCache[$userId];
	}

	/**
	 * Returns an app-specific view in ownClouds data directory
	 *
	 * @return \OCP\Files\Folder
	 */
	function getAppFolder() {
		$dir = '/' . \OC_App::getCurrentApp();
		$root = $this->getRootFolder();
		$folder = null;
		if (!$root->nodeExists($dir)) {
			$folder = $root->newFolder($dir);
		} else {
			$folder = $root->get($dir);
		}
		return $folder;
	}
}
