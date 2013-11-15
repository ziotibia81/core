<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Node;

use OC\Files\NotPermittedException;

class File extends Node {
	/**
	 * @return string
	 * @throws \OC\Files\NotPermittedException
	 */
	public function getContent() {
		if ($this->checkPermissions(\OCP\PERMISSION_READ)) {
			/**
			 * @var \OC\Files\Storage\Storage $storage;
			 */
			return $this->storage->file_get_contents($this->internalPath);
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @param string $data
	 * @throws \OC\Files\NotPermittedException
	 */
	public function putContent($data) {
		if ($this->checkPermissions(\OCP\PERMISSION_UPDATE)) {
			$this->sendHooks(array('preWrite'));
			if (is_resource($data)) {
				$fh = $this->fopen('w');
				stream_copy_to_stream($data, $fh);
				fclose($fh);
			} else {
				$this->storage->file_put_contents($this->internalPath, $data);
			}
			$this->writeUpdateCache();
			$this->sendHooks(array('postWrite'));
		} else {
			throw new NotPermittedException();
		}
	}

	/**
	 * @return string
	 */
	public function getMimeType() {
		return $this->data['mimetype'];
	}

	/**
	 * @param string $mode
	 * @return resource
	 * @throws \OC\Files\NotPermittedException
	 */
	public function fopen($mode) {
		$preHooks = array();
		$postHooks = array();
		$requiredPermissions = \OCP\PERMISSION_READ;
		switch ($mode) {
			case 'r+':
			case 'rb+':
			case 'w+':
			case 'wb+':
			case 'x+':
			case 'xb+':
			case 'a+':
			case 'ab+':
			case 'w':
			case 'wb':
			case 'x':
			case 'xb':
			case 'a':
			case 'ab':
				$preHooks[] = 'preWrite';
				$postHooks[] = 'postWrite';
				$requiredPermissions |= \OCP\PERMISSION_UPDATE;
				break;
		}

		if ($this->checkPermissions($requiredPermissions)) {
			$this->sendHooks($preHooks);
			$result = $this->storage->fopen($this->internalPath, $mode);
			$this->sendHooks($postHooks);
			return $result;
		} else {
			throw new NotPermittedException();
		}
	}

	public function delete() {
		if ($this->checkPermissions(\OCP\PERMISSION_DELETE)) {
			$this->sendHooks(array('preDelete'));
			$this->storage->unlink($this->internalPath);
			$nonExisting = new NonExistingFile($this->root, $this->storage, $this->internalPath, $this->path, $this->data);
			$this->deleteFromCache();
			$this->root->emit('\OC\Files', 'postDelete', array($nonExisting));
			$this->exists = false;
		} else {
			throw new NotPermittedException();
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
			$nonExisting = new NonExistingFile($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
			$this->root->emit('\OC\Files', 'preCopy', array($this, $nonExisting));
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			if ($targetStorage === $this->storage) {
				$this->storage->copy($this->internalPath, $targetInternalPath);
			} else {
				$targetStream = $targetStorage->fopen($targetInternalPath, 'w');
				$sourceStream = $this->fopen('r');
				stream_copy_to_stream($sourceStream, $targetStream);
				fclose($sourceStream);
				fclose($targetStream);
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
			$nonExisting = new NonExistingFile($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
			$this->root->emit('\OC\Files', 'preRename', array($this, $nonExisting));
			$this->root->emit('\OC\Files', 'preWrite', array($nonExisting));
			if ($targetStorage === $this->storage) {
				$this->storage->rename($this->internalPath, $targetInternalPath);
				$targetNode = new File($this->root, $targetStorage, $targetInternalPath, $targetPath, $targetData);
				$this->moveInCache($targetNode);
			} else {
				$targetStream = $targetStorage->fopen($targetInternalPath, 'w');
				$sourceStream = $this->fopen('r');
				stream_copy_to_stream($sourceStream, $targetStream);
				fclose($sourceStream);
				fclose($targetStream);
				$this->delete();
				$this->storage = $targetStorage;
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

	/**
	 * @param string $type
	 * @param bool $raw
	 * @return string
	 */
	public function hash($type, $raw = false) {
		return $this->storage->hash($type, $this->internalPath, $raw);
	}
}
