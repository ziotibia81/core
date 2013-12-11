<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Node;

use OC\Files\Cache\Cache;
use OC\Files\Cache\Scanner;
use OC\Files\NotFoundException;
use OC\Files\NotPermittedException;

class Folder extends Node {
	/**
	 * @param string $path path relative to the folder
	 * @return string
	 * @throws \OC\Files\NotPermittedException
	 */
	public function getFullPath($path) {
		$path = $this->normalizePath($path);
		if (!$this->isValidPath($path)) {
			throw new NotPermittedException();
		}
		return $this->normalizePath($this->path . $path);
	}

	/**
	 * @param string $path
	 * @throws \OC\Files\NotFoundException
	 * @return string
	 */
	public function getRelativePath($path) {
		if ($this->path === '' or $this->path === '/') {
			return $this->normalizePath($path);
		}
		if (strpos($path, $this->path) !== 0) {
			throw new NotFoundException();
		} else {
			$path = substr($path, strlen($this->path));
			if (strlen($path) === 0) {
				return '/';
			} else {
				return $this->normalizePath($path);
			}
		}
	}

	/**
	 * check if a node is a (grand-)child of the folder
	 *
	 * @param \OC\Files\Node\Node $node
	 * @return bool
	 */
	public function isSubNode($node) {
		return strpos($node->getPath(), $this->path . '/') === 0;
	}

	/**
	 * get the content of this directory
	 *
	 * @throws \OC\Files\NotFoundException
	 * @return Node[]
	 */
	public function getDirectoryListing() {
		$result = array();

		if ($this->storage) {
			$cache = $this->storage->getCache($this->internalPath);
			$permissionsCache = $this->storage->getPermissionsCache($this->internalPath);

			$this->checkUpdate();

			$files = $cache->getFolderContents($this->internalPath);
			$permissions = $permissionsCache->getDirectoryPermissions($this->getId(), $this->root->getUser()->getUID());
		} else {
			$files = array();
		}

		//add a folder for any mountpoint in this directory and add the sizes of other mountpoints to the folders
		$mounts = $this->root->getMountsIn($this->path);
		$dirLength = strlen($this->path);
		foreach ($mounts as $mount) {
			$subStorage = $mount->getStorage();
			if ($subStorage) {
				$subCache = $subStorage->getCache('');

				if ($subCache->getStatus('') === Cache::NOT_FOUND) {
					$subScanner = $subStorage->getScanner('');
					$subScanner->scanFile('');
				}

				$rootEntry = $subCache->get('');
				if ($rootEntry) {
					$relativePath = trim(substr($mount->getMountPoint(), $dirLength), '/');
					if ($pos = strpos($relativePath, '/')) {
						//mountpoint inside subfolder add size to the correct folder
						$entryName = substr($relativePath, 0, $pos);
						foreach ($files as &$entry) {
							if ($entry['name'] === $entryName) {
								if ($rootEntry['size'] >= 0) {
									$entry['size'] += $rootEntry['size'];
								} else {
									$entry['size'] = -1;
								}
							}
						}
					} else { //mountpoint in this folder, add an entry for it
						$rootEntry['name'] = $relativePath;
						$rootEntry['path'] = $this->path . '/' . $relativePath;
						$rootEntry['storageObject'] = $subStorage;

						//remove any existing entry with the same name
						foreach ($files as $i => $file) {
							if ($file['name'] === $rootEntry['name']) {
								$files[$i] = null;
								break;
							}
						}
						$files[] = $rootEntry;
					}
				}
			}
		}

		foreach ($files as $file) {
			if ($file) {
				if (isset($permissions[$file['fileid']])) {
					$file['permissions'] = $permissions[$file['fileid']];
				}
				$storage = isset($file['storageObject']) ? $file['storageObject'] : $this->storage;
				$node = $this->createNode($storage, $file['path'], $this->path . '/' . $file['name'], $file);
				$result[] = $node;
			}
		}

		return $result;
	}

	/**
	 * Get the node at $path
	 *
	 * @param string $path
	 * @return \OC\Files\Node\Node
	 * @throws \OC\Files\NotFoundException
	 */
	public function get($path) {
		return $this->root->get($this->getFullPath($path));
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function nodeExists($path) {
		try {
			$this->get($path);
			return true;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return Folder
	 * @throws NotPermittedException
	 */
	public function newFolder($path) {
		if ($this->checkPermissions(\OCP\PERMISSION_CREATE)) {
			$fullPath = $this->getFullPath($path);
			/**
			 * @var \OC\Files\Storage\Storage $storage
			 */
			list($storage, $internalPath) = $this->resolvePath($fullPath);
			$nonExisting = new NonExistingFolder($this->root, $storage, $internalPath, $fullPath, array());
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			$this->root->emit('\OC\Files', 'preCreate', array($nonExisting));
			$storage->mkdir($internalPath);
			$node = $this->root->get($fullPath);
			$node->writeUpdateCache();
			$this->root->emit('\OC\Files', 'postWrite', array($node));
			$this->root->emit('\OC\Files', 'postCreate', array($node));
			return $node;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string $path
	 * @return File
	 * @throws NotPermittedException
	 */
	public function newFile($path) {
		if ($this->checkPermissions(\OCP\PERMISSION_CREATE)) {
			$fullPath = $this->getFullPath($path);
			/**
			 * @var \OC\Files\Storage\Storage $storage
			 */
			list($storage, $internalPath) = $this->resolvePath($fullPath);
			$nonExisting = new NonExistingFile($this->root, $storage, $internalPath, $fullPath, array());
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			$this->root->emit('\OC\Files', 'preCreate', array($nonExisting));
			$storage->touch($internalPath);
			$node = $this->root->get($fullPath);
			$node->writeUpdateCache();
			$this->root->emit('\OC\Files', 'postWrite', array($node));
			$this->root->emit('\OC\Files', 'postCreate', array($node));
			return $node;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return Node[]
	 */
	public function search($query) {
		return $this->searchCommon('%' . $query . '%', 'search');
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return Node[]
	 */
	public function searchByMime($mimetype) {
		return $this->searchCommon($mimetype, 'searchByMime');
	}

	/**
	 * @param string $query
	 * @param string $method
	 * @return Node[]
	 */
	private function searchCommon($query, $method) {
		$files = array();
		$rootLength = strlen($this->path);
		$internalRootLength = strlen($this->internalPath);

		$cache = $this->storage->getCache('');

		$results = $cache->$method($query);
		foreach ($results as $result) {
			if ($internalRootLength === 0 or substr($result['path'], 0, $internalRootLength + 1) === $this->internalPath . '/') {
				$result['internalPath'] = $result['path'];
				$result['path'] = substr($result['path'], $internalRootLength);
				$result['storage'] = $this->storage;
				$files[] = $result;
			}
		}

		$mounts = $this->root->getMountsIn($this->path);
		foreach ($mounts as $mount) {
			$storage = $mount->getStorage();
			if ($storage) {
				$cache = $storage->getCache('');

				$relativeMountPoint = substr($mount->getMountPoint(), $rootLength);
				$results = $cache->$method($query);
				foreach ($results as $result) {
					$result['internalPath'] = $result['path'];
					$result['path'] = $relativeMountPoint . $result['path'];
					$result['storage'] = $storage;
					$files[] = $result;
				}
			}
		}

		$result = array();
		foreach ($files as $file) {
			$result[] = $this->createNode($file['storage'], $file['internalPath'], $this->normalizePath($this->path . '/' . $file['path']), $file);
		}

		return $result;
	}

	/**
	 * @param $id
	 * @return Node[]
	 */
	public function getById($id) {
		$nodes = $this->root->getById($id);
		$result = array();
		foreach ($nodes as $node) {
			$pathPart = substr($node->getPath(), 0, strlen($this->getPath()) + 1);
			if ($this->path === '/' or $pathPart === $this->getPath() . '/') {
				$result[] = $node;
			}
		}
		return $result;
	}

	public function getFreeSpace() {
		return $this->storage->free_space($this->internalPath);
	}

	/**
	 * @return bool
	 */
	public function isCreatable() {
		return $this->checkPermissions(\OCP\PERMISSION_CREATE);
	}

	public function delete() {
		if ($this->checkPermissions(\OCP\PERMISSION_DELETE)) {
			$this->sendHooks(array('preDelete'));
			$this->storage->rmdir($this->internalPath);
			$nonExisting = new NonExistingFolder($this->root, $this->storage, $this->internalPath, $this->path, $this->data);
			$this->deleteFromCache();
			$this->root->emit('\OC\Files', 'postDelete', array($nonExisting));
			$this->exists = false;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * copy a folder to a different storage
	 *
	 * @param string $targetPath
	 * @param \OC\Files\Storage\Storage $targetStorage
	 * @param string $targetInternalPath
	 */
	private function copyCrossStorage($targetPath, $targetStorage, $targetInternalPath) {
		$targetStorage->mkdir($targetInternalPath);
		$content = $this->getDirectoryListing();
		foreach ($content as $child) {
			if ($child instanceof Folder) {
				$child->copyCrossStorage($targetPath . '/' . $child->getName(), $targetStorage, $targetInternalPath . '/' . $child->getName());
			} else {
				$child->copy($targetPath . '/' . $child->getName());
			}
		}
	}

	/**
	 * @param string $targetPath
	 * @throws \OC\Files\NotPermittedException
	 * @return \OC\Files\Node\Node
	 */
	public function copy($targetPath) {
		$targetPath = $this->normalizePath($targetPath);
		$parent = $this->root->get(dirname($targetPath));
		if ($parent instanceof Folder and $this->isValidPath($targetPath) and $parent->isCreatable()) {
			/**
			 * @var \OC\Files\Storage\Storage $targetStorage
			 */
			list($targetStorage, $targetInternalPath) = $this->resolvePath($targetPath);
			$targetData = $this->data;
			$targetData['name'] = basename($targetInternalPath);
			$targetData['path'] = $targetInternalPath;
			$targetData['parent'] = $parent->getId();
			unset($targetData['fileid']);
			$nonExisting = new NonExistingFolder($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
			$this->root->emit('\OC\Files', 'preCopy', array($this, $nonExisting));
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			if ($targetStorage === $this->storage) {
				$this->storage->copy($this->internalPath, $targetInternalPath);
			} else {
				$this->copyCrossStorage($targetPath, $targetStorage, $targetInternalPath);
			}
			$targetNode = $this->root->get($targetPath);
			$this->root->emit('\OC\Files', 'postCopy', array($this, $targetNode));
			$this->root->emit('\OC\Files', 'postWrite', array($targetNode));
			return $targetNode;
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string $targetPath
	 * @throws \OC\Files\NotPermittedException
	 * @return \OC\Files\Node\Node
	 */
	public function move($targetPath) {
		$targetPath = $this->normalizePath($targetPath);
		$parent = $this->root->get(dirname($targetPath));
		if ($parent instanceof Folder and $this->isValidPath($targetPath) and $parent->isCreatable()) {
			/**
			 * @var \OC\Files\Storage\Storage $targetStorage
			 */
			list($targetStorage, $targetInternalPath) = $this->resolvePath($targetPath);
			$targetData = $this->data;
			$targetData['name'] = basename($targetInternalPath);
			$targetData['path'] = $targetInternalPath;
			$targetData['parent'] = $parent->getId();
			$nonExisting = new NonExistingFolder($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
			$this->root->emit('\OC\Files', 'preRename', array($this, $nonExisting));
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			if ($targetStorage === $this->storage) {
				$targetNode = new Folder($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
				$this->storage->rename($this->internalPath, $targetInternalPath);
				$this->moveInCache($targetNode);
			} else {
				$this->copyCrossStorage($targetPath, $targetStorage, $targetInternalPath);
				$this->delete();
				$targetNode = $this->root->get($targetPath);
				$this->data['fileid'] = $targetNode->getId();
			}
			$this->root->emit('\OC\Files', 'postRename', array($this, $targetNode));
			$this->root->emit('\OC\Files', 'postWrite', array($targetNode));
			$this->internalPath = $targetInternalPath;
			$this->path = $targetPath;
			return $targetNode;
		} else {
			throw new NotPermittedException();
		}
	}
}
