<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Cache;

use OC\Files\Filesystem;
use OC\Files\Node\Root;
use OC\Files\NotFoundException;

/**
 * listen to filesystem hooks and change the cache accordingly
 */
class Updater {

	/**
	 * @var \OC\Files\Node\Root $root
	 */
	protected $root;

	/**
	 * @param \OC\Files\Node\Root $root
	 */
	public function __construct($root) {
		$this->root = $root;
	}

	/**
	 * @param \OC\Files\Node\Node $node
	 */
	public function update($node) {
		$scanner = $node->getStorage()->getScanner($node->getInternalPath());
		$scanner->scan($node->getInternalPath(), Scanner::SCAN_SHALLOW);
	}

	/**
	 * @param \OC\Files\Node\Node $node
	 * @param int $time (optional)
	 */
	public function updateParents($node, $time = null) {
		if (is_null($time)) {
			$time = $node->getStorage()->filemtime($node->getInternalPath());
		}
		$cache = $node->getStorage()->getCache($node->getInternalPath());
		$cache->correctFolderSize($node->getInternalPath());
		try {
			$this->correctParentStorageMtime($node->getStorage(), $node->getInternalPath());
			$this->correctFolder($node, $time);
		} catch (NotFoundException $e) {

		}
	}

	/**
	 * resolve a path to a storage and internal path
	 *
	 * @param string $path the absolute path
	 * @return array consisting of the storage and the internal path
	 */
	static public function resolvePath($path) {
		$view = \OC\Files\Filesystem::getView();
		return $view->resolvePath($path);
	}

	/**
	 * perform a write update
	 *
	 * @param string $path the relative path of the file
	 */
	static public function writeUpdate($path) {
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 * @var string $internalPath
		 */
		list($storage, $internalPath) = self::resolvePath($path);
		if ($storage) {
			$cache = $storage->getCache($internalPath);
			$scanner = $storage->getScanner($internalPath);
			$scanner->scan($internalPath, Scanner::SCAN_SHALLOW);
			$cache->correctFolderSize($internalPath);
			self::correctFolder($path, $storage->filemtime($internalPath));
			self::correctParentStorageMtime($storage, $internalPath);
		}
	}

	/**
	 * perform a delete update
	 *
	 * @param string $path the relative path of the file
	 */
	static public function deleteUpdate($path) {
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 * @var string $internalPath
		 */
		list($storage, $internalPath) = self::resolvePath($path);
		if ($storage) {
			$parent = dirname($internalPath);
			if ($parent === '.') {
				$parent = '';
			}
			$cache = $storage->getCache($internalPath);
			$cache->remove($internalPath);
			$cache->correctFolderSize($parent);
			self::correctFolder($path, time());
			self::correctParentStorageMtime($storage, $internalPath);
		}
	}

	/**
	 * preform a rename update
	 *
	 * @param string $from the relative path of the source file
	 * @param string $to the relative path of the target file
	 */
	static public function renameUpdate($from, $to) {
		/**
		 * @var \OC\Files\Storage\Storage $storageFrom
		 * @var \OC\Files\Storage\Storage $storageTo
		 * @var string $internalFrom
		 * @var string $internalTo
		 */
		list($storageFrom, $internalFrom) = self::resolvePath($from);
		list($storageTo, $internalTo) = self::resolvePath($to);
		if ($storageFrom && $storageTo) {
			if ($storageFrom === $storageTo) {
				$cache = $storageFrom->getCache($internalFrom);
				$cache->move($internalFrom, $internalTo);
				if (pathinfo($internalFrom, PATHINFO_EXTENSION) !== pathinfo($internalTo, PATHINFO_EXTENSION)) {
					// redetect mime type change
					$mimeType = $storageTo->getMimeType($internalTo);
					$fileId = $storageTo->getCache()->getId($internalTo);
					$storageTo->getCache()->update($fileId, array('mimetype' => $mimeType));
				}
				$cache->correctFolderSize($internalFrom);
				$cache->correctFolderSize($internalTo);
				self::correctFolder($from, time());
				self::correctFolder($to, time());
				self::correctParentStorageMtime($storageFrom, $internalFrom);
				self::correctParentStorageMtime($storageTo, $internalTo);
			} else {
				self::deleteUpdate($from);
				self::writeUpdate($to);
			}
		}
	}

	/**
	 * @brief get file owner node
	 * @param \OC\Files\Node\Node $node
	 * @return \OC\Files\Node\Node the owners node
	 */
	private function getOwnerNode($node) {
		$user = $node->getOwner();
		if ($user and $user->getUID() != $this->root->getUser()->getUID()) {
			Filesystem::initMountPoints($user->getUID());

			$ownerRoot = new Root($this->root->getMountManager(), $user, $this->root->getUserManager());
			$fileId = $node->getId();
			$nodes = $ownerRoot->getById($fileId);
			if (count($nodes)) {
				$node = $nodes[0];
			}
		}
		return $node;
	}

	/**
	 * Update the mtime and ETag of all parent folders
	 *
	 * @param \OC\Files\Node\Node $node
	 * @param string $time
	 */
	public function correctFolder($node, $time) {
		if ($parent = $node->getParent()) {
			$realNode = $this->getOwnerNode($parent);
			if (!$realNode) {
				$realNode = $node;
			}

			$storage = $realNode->getStorage();
			$cache = $storage->getCache();
			$id = $realNode->getId();

			while ($id !== -1) {
				$cache->update($id, array('mtime' => $time, 'etag' => $storage->getETag($realNode->getInternalPath())));
				if ($realNode = $realNode->getParent()) {
					$storage = $realNode->getStorage();
					$cache = $storage->getCache();
					$id = $realNode->getId();
				} else {
					$id = -1;
				}
			}
		}
	}

	/**
	 * update the storage_mtime of the parent
	 *
	 * @param \OC\Files\Storage\Storage $storage
	 * @param string $internalPath
	 */
	public function correctParentStorageMtime($storage, $internalPath) {
		$cache = $storage->getCache();
		$parentId = $cache->getParentId($internalPath);
		$parent = dirname($internalPath);
		if ($parentId != -1) {
			$cache->update($parentId, array('storage_mtime' => $storage->filemtime($parent)));
		}
	}

	/**
	 * @param array $params
	 */
	static public function writeHook($params) {
//		self::writeUpdate($params['path']);
	}

	/**
	 * @param array $params
	 */
	static public function touchHook($params) {
//		$path = $params['path'];
//		list($storage, $internalPath) = self::resolvePath($path);
//		$cache = $storage->getCache();
//		$id = $cache->getId($internalPath);
//		if ($id !== -1) {
//			$cache->update($id, array('etag' => $storage->getETag($internalPath)));
//		}
//		self::writeUpdate($path);
	}

	/**
	 * @param array $params
	 */
	static public function renameHook($params) {
//		self::renameUpdate($params['oldpath'], $params['newpath']);
	}

	/**
	 * @param array $params
	 */
	static public function deleteHook($params) {
//		self::deleteUpdate($params['path']);
	}
}
