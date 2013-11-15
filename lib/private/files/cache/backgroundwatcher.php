<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Cache;

use OC\BackgroundJob\TimedJob;
use \OC\Files\Mount;
use \OC\Files\Filesystem;

class BackgroundWatcher extends TimedJob {
	private $folderMimetype = null;

	public function __construct() {
		$this->setInterval(3600);
	}

	private function getFolderMimetype() {
		if (!is_null($this->folderMimetype)) {
			return $this->folderMimetype;
		}
		$sql = 'SELECT `id` FROM `*PREFIX*mimetypes` WHERE `mimetype` = ?';
		$result = \OC_DB::executeAudited($sql, array('httpd/unix-directory'));
		$row = $result->fetchRow();
		$this->folderMimetype = $row['id'];
		return $this->folderMimetype;
	}

	private function checkUpdate($id) {
		$cacheItem = Cache::getById($id);
		if (is_null($cacheItem)) {
			return;
		}
		list($storageId, $internalPath) = $cacheItem;
		$mounts = Filesystem::getMountByStorageId($storageId);

		if (count($mounts) === 0) {
			//if the storage we need isn't mounted on default, try to find a user that has access to the storage
			$permissionsCache = new Permissions($storageId);
			$users = $permissionsCache->getUsers($id);
			if (count($users) === 0) {
				return;
			}
			Filesystem::initMountPoints($users[0]);
			$mounts = Filesystem::getMountByStorageId($storageId);
			if (count($mounts) === 0) {
				return;
			}
		}
		$storage = $mounts[0]->getStorage();
		$watcher = new Watcher($storage);
		$watcher->checkUpdate($internalPath);
	}

	/**
	 * get the next fileid in the cache
	 *
	 * @param int $previous
	 * @param bool $folder
	 * @return int
	 */
	private function getNextFileId($previous, $folder) {
		if ($folder) {
			$stmt = \OC_DB::prepare('SELECT `fileid` FROM `*PREFIX*filecache` WHERE `fileid` > ? AND `mimetype` = ? ORDER BY `fileid` ASC', 1);
		} else {
			$stmt = \OC_DB::prepare('SELECT `fileid` FROM `*PREFIX*filecache` WHERE `fileid` > ? AND `mimetype` != ? ORDER BY `fileid` ASC', 1);
		}
		$result = \OC_DB::executeAudited($stmt, array($previous, self::getFolderMimetype()));
		if ($row = $result->fetchRow()) {
			return $row['fileid'];
		} else {
			return 0;
		}
	}

	protected function checkNext() {
		// check both 1 file and 1 folder, this way new files are detected quicker because there are less folders than files usually
		$previousFile = \OC_Appconfig::getValue('files', 'backgroundwatcher_previous_file', 0);
		$previousFolder = \OC_Appconfig::getValue('files', 'backgroundwatcher_previous_folder', 0);
		$nextFile = $this->getNextFileId($previousFile, false);
		$nextFolder = $this->getNextFileId($previousFolder, true);
		\OC_Appconfig::setValue('files', 'backgroundwatcher_previous_file', $nextFile);
		\OC_Appconfig::setValue('files', 'backgroundwatcher_previous_folder', $nextFolder);
		if ($nextFile > 0) {
			$this->checkUpdate($nextFile);
		}
		if ($nextFolder > 0) {
			$this->checkUpdate($nextFolder);
		}
	}

	protected function checkAll() {

	}

	protected function run($storageId) {

	}
}
