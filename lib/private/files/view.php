<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

/**
 * Class to provide access to ownCloud filesystem via a "view", and methods for
 * working with files within that view (e.g. read, write, delete, etc.). Each
 * view is restricted to a set of directories via a virtual root. The default view
 * uses the currently logged in user's data directory as root (parts of
 * OC_Filesystem are merely a wrapper for OC_FilesystemView).
 *
 * Apps that need to access files outside of the user data folders (to modify files
 * belonging to a user other than the one currently logged in, for example) should
 * use this class directly rather than using OC_Filesystem, or making use of PHP's
 * built-in file manipulation functions. This will ensure all hooks and proxies
 * are triggered correctly.
 *
 * Filesystem functions are not called directly; they are passed to the correct
 * \OC\Files\Storage\Storage object
 */

namespace OC\Files;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\Files\Stream\Dir;
use OC\Hooks\BasicEmitter;

/**
 * Class View
 *
 * Hooks available in scope \OC\Files
 * - preWrite(\OC\Files\Node\Node $node)
 * - postWrite(\OC\Files\Node\Node $node)
 * - preCreate(\OC\Files\Node\Node $node)
 * - postCreate(\OC\Files\Node\Node $node)
 * - preDelete(\OC\Files\Node\Node $node)
 * - postDelete(\OC\Files\Node\Node $node)
 * - preTouch(\OC\Files\Node\Node $node)
 * - postTouch(\OC\Files\Node\Node $node)
 * - preCopy(\OC\Files\Node\Node $source, \OC\Files\Node\Node $target)
 * - postCopy(\OC\Files\Node\Node $source, \OC\Files\Node\Node $target)
 * - preRename(\OC\Files\Node\Node $source, \OC\Files\Node\Node $target)
 * - postRename(\OC\Files\Node\Node $source, \OC\Files\Node\Node $target)
 *
 * Only events of changes made within the root of the view are emitted
 *
 * @package OC\Files
 */
class View extends BasicEmitter {
	/**
	 * @var \OC\Files\Node\Folder $rootFolder
	 */
	private $rootFolder;

	public function __construct($root) {
		$this->rootFolder = Filesystem::getRootNode()->get($root);
		$this->connectHooks(Filesystem::getRootNode());
	}

	/**
	 * @param \OC\Files\Node\Root $root
	 */
	private function connectHooks($root) {
		$basicHooks = array(
			'preWrite',
			'postWrite',
			'preCreate',
			'postCreate',
			'preDelete',
			'postDelete',
			'preTouch',
			'postTouch'
		);
		foreach ($basicHooks as $hook) {
			$root->listen('\OC\Files', $hook, function ($node) use ($hook) {
				if ($this->rootFolder->isSubNode($node)) {
					$this->emit('\OC\Files', $hook, array($node));
				}
			});
		}
		$doubleHooks = array(
			'preCopy',
			'postCopy',
			'preRename',
			'postRename'
		);
		foreach ($doubleHooks as $hook) {
			$root->listen('\OC\Files', $hook, function ($source, $target) use ($hook) {
				if ($this->rootFolder->isSubNode($source) and $this->rootFolder->isSubNode($target)) {
					$this->emit('\OC\Files', $hook, array($source, $target));
				}
			});
		}
	}

	public function getAbsolutePath($path = '/') {
		return $this->rootFolder->getFullPath($path);
	}

	/**
	 * change the root to a fake root
	 *
	 * @param string $fakeRoot
	 * @return bool
	 */
	public function chroot($fakeRoot) {
		if (!$fakeRoot == '') {
			if ($fakeRoot[0] !== '/') {
				$fakeRoot = '/' . $fakeRoot;
			}
		}
		$this->rootFolder = Filesystem::getRootNode()->get($fakeRoot);
	}

	/**
	 * get the fake root
	 *
	 * @return string
	 */
	public function getRoot() {
		return $this->rootFolder->getPath();
	}

	/**
	 * get path relative to the root of the view
	 *
	 * @param string $path
	 * @return string
	 */
	public function getRelativePath($path) {
		return $this->rootFolder->getRelativePath($path);
	}

	/**
	 * get the mountpoint of the storage object for a path
	 * ( note: because a storage is not always mounted inside the fakeroot, the
	 * returned mountpoint is relative to the absolute root of the filesystem
	 * and doesn't take the chroot into account )
	 *
	 * @param string $path
	 * @return string
	 */
	public function getMountPoint($path) {
		return Filesystem::getMountPoint($this->getAbsolutePath($path));
	}

	/**
	 * resolve a path to a storage and internal path
	 *
	 * @param string $path
	 * @return array consisting of the storage and the internal path
	 */
	public function resolvePath($path) {
		return Filesystem::resolvePath($this->getAbsolutePath($path));
	}

	/**
	 * return the path to a local version of the file
	 * we need this because we can't know if a file is stored local or not from
	 * outside the filestorage and for some purposes a local file is needed
	 *
	 * @param string $path
	 * @deprecated use the oc:// streamwrapper or streams instead
	 * @return string
	 */
	public function getLocalFile($path) {
		$parent = substr($path, 0, strrpos($path, '/'));
		$path = $this->getAbsolutePath($path);
		list($storage, $internalPath) = Filesystem::resolvePath($path);
		if (Filesystem::isValidPath($parent) and $storage) {
			return $storage->getLocalFile($internalPath);
		} else {
			return null;
		}
	}

	/**
	 * @param string $path
	 * @deprecated use the oc:// streamwrapper or streams instead
	 * @return string
	 */
	public function getLocalFolder($path) {
		$parent = substr($path, 0, strrpos($path, '/'));
		$path = $this->getAbsolutePath($path);
		list($storage, $internalPath) = Filesystem::resolvePath($path);
		if (Filesystem::isValidPath($parent) and $storage) {
			return $storage->getLocalFolder($internalPath);
		} else {
			return null;
		}
	}

	/**
	 * the following functions operate with arguments and return values identical
	 * to those of their PHP built-in equivalents. Mostly they are merely wrappers
	 * for \OC\Files\Storage\Storage.
	 */
	public function mkdir($path) {
		try {
			$this->rootFolder->newFolder($path);
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function rmdir($path) {
		try {
			$this->rootFolder->get($path)->delete();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function opendir($path) {
		try {
			$folder = $this->rootFolder->get($path);
		} catch (\Exception $e) {
			return false;
		}
		if ($folder instanceof Folder) {
			$content = $folder->getDirectoryListing();
			$contentNames = array();
			foreach ($content as $node) {
				$contentNames[] = $node->getName();
			}
			Dir::register($this->getAbsolutePath($path), $contentNames);
			return opendir('fakedir://' . $this->getAbsolutePath($path));
		} else {
			return false;
		}
	}

	public function readdir($handle) {
		$fsLocal = new Storage\Local(array('datadir' => '/'));
		return $fsLocal->readdir($handle);
	}

	public function is_dir($path) {
		try {
			return $this->rootFolder->get($path) instanceof Folder;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function is_file($path) {
		try {
			return $this->rootFolder->get($path) instanceof File;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function stat($path) {
		try {
			return $this->rootFolder->get($path)->stat();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function filetype($path) {
		return $this->is_dir($path) ? 'dir' : 'file';
	}

	public function filesize($path) {
		$stat = $this->stat($path);
		return $stat['size'];
	}

	public function readfile($path) {
		@ob_end_clean();
		$handle = $this->fopen($path, 'rb');
		if ($handle) {
			$chunkSize = 8192; // 8 kB chunks
			while (!feof($handle)) {
				echo fread($handle, $chunkSize);
				flush();
			}
			$size = $this->filesize($path);
			return $size;
		}
		return false;
	}

	public function isCreatable($path) {
		try {
			$node = $this->rootFolder->get($path);
			return $node instanceof Folder and $node->isCreatable();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function isReadable($path) {
		try {
			return $this->rootFolder->get($path)->isReadable();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function isUpdatable($path) {
		try {
			return $this->rootFolder->get($path)->isUpdateable();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function isDeletable($path) {
		try {
			return $this->rootFolder->get($path)->isDeletable();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function isSharable($path) {
		try {
			return $this->rootFolder->get($path)->isShareable();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function file_exists($path) {
		return $this->rootFolder->nodeExists($path);
	}

	public function filemtime($path) {
		$stat = $this->stat($path);
		return $stat['mtime'];
	}

	public function touch($path, $mtime = null) {
		try {
			$this->rootFolder->get($path)->touch($mtime);
			return true;
		} catch (NotFoundException $e) {
			$newFile = $this->rootFolder->newFile($path);
			if ($mtime) {
				$newFile->touch($mtime);
			}
			return true;
		}
	}

	public function file_get_contents($path) {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof File) {
				return $node->getContent();
			} else {
				return false;
			}
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function file_put_contents($path, $data) {
		if ($this->rootFolder->nodeExists($path)) {
			$node = $this->rootFolder->get($path);
		} else {
			$node = $this->rootFolder->newFile($path);
		}
		$node->putContent($data);
	}

	public function unlink($path) {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof File) {
				$node->delete();
				return true;
			} else {
				return false;
			}
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function deleteAll($directory, $empty = false) {
		try {
			$this->rootFolder->get($directory)->delete();
			return true;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function rename($path1, $path2) {
		try {
			$this->rootFolder->get($path1)->move($this->getAbsolutePath($path2));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function copy($path1, $path2) {
		try {
			$this->rootFolder->get($path1)->copy($this->getAbsolutePath($path2));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function fopen($path, $mode) {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof File) {
				return $node->fopen($mode);
			} else {
				return false;
			}
		} catch (NotFoundException $e) {
			if ($mode !== 'r' and $mode !== 'rb') {
				$node = $this->rootFolder->newFile($path);
				return $node->fopen($mode);
			} else {
				return false;
			}
		}
	}

	/**
	 * @param string $path
	 * @deprecated
	 * @return bool|string
	 */
	public function toTmpFile($path) {
		if (Filesystem::isValidPath($path)) {
			$source = $this->fopen($path, 'r');
			if ($source) {
				$extension = pathinfo($path, PATHINFO_EXTENSION);
				$tmpFile = \OC_Helper::tmpFile($extension);
				file_put_contents($tmpFile, $source);
				return $tmpFile;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param string $tmpFile
	 * @param string $path
	 * @deprecated
	 * @return bool
	 */
	public function fromTmpFile($tmpFile, $path) {
		if (Filesystem::isValidPath($path)) {
			if (!$tmpFile) {
				debug_print_backtrace();
			}
			$source = fopen($tmpFile, 'r');
			if ($source) {
				$this->file_put_contents($path, $source);
				unlink($tmpFile);
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function getMimeType($path) {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof File) {
				return $node->getMimeType();
			} else {
				return 'httpd/unix-directory';
			}
		} catch (\Exception $e) {
			return false;
		}
	}

	public function hash($type, $path, $raw = false) {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof File) {
				return $node->hash($type, $raw);
			} else {
				return null;
			}
		} catch (\Exception $e) {
			return null;
		}
	}

	public function free_space($path = '/') {
		try {
			$node = $this->rootFolder->get($path);
			if ($node instanceof Folder) {
				return $node->getFreeSpace();
			} else {
				return 0;
			}
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		try {
			$node = $this->rootFolder->get($path);
			return $node->getStorage()->hasUpdated($node->getInternalPath(), $time);
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * get the filesystem info
	 *
	 * @param string $path
	 * @return array
	 *
	 * returns an associative array with the following keys:
	 * - size
	 * - mtime
	 * - mimetype
	 * - encrypted
	 * - versioned
	 */
	public function getFileInfo($path) {
		return $this->stat($path);
	}

	/**
	 * get the content of a directory
	 *
	 * @param string $directory path under datadirectory
	 * @param string $mimetype_filter limit returned content to this mimetype or mimepart
	 * @return array[]
	 */
	public function getDirectoryContent($directory, $mimetype_filter = '') {
		try {
			$node = $this->rootFolder->get($directory);
			if ($node instanceof Folder) {
				$result = array();
				$files = array();
				$content = $node->getDirectoryListing();
				foreach ($content as $node) {
					$file = $node->stat();
					$file['permissions'] = $node->getPermissions();
					$file['type'] = ($node instanceof Folder) ? 'dir' : 'file';
					$files[] = $file;
				}
				if ($mimetype_filter) {
					foreach ($files as $file) {
						if (strpos($mimetype_filter, '/')) {
							if ($file['mimetype'] === $mimetype_filter) {
								$result[] = $file;
							}
						} else {
							if ($file['mimepart'] === $mimetype_filter) {
								$result[] = $file;
							}
						}
					}
				} else {
					$result = $files;
				}
				return $result;
			} else {
				return array();
			}
		} catch (\Exception $e) {
			return array();
		}
	}

	/**
	 * change file metadata
	 *
	 * @param string $path
	 * @param array $data
	 * @return int
	 *
	 * returns the fileid of the updated file
	 */
	public function putFileInfo($path, $data) {
		try {
			$node = $this->rootFolder->get($path);
			$cache = $node->getStorage()->getCache();
			return $cache->put($node->getInternalPath(), $data);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * search for files with the name matching $query
	 *
	 * @param string $query
	 * @return string[]
	 */
	public function search($query) {
		$result = $this->rootFolder->search($query);
		$files = array();
		foreach ($result as $node) {
			$stat = $node->stat();
			$stat['path'] = $this->getRelativePath($node->getPath());
			$files[] = $stat;
		}
		return $files;
	}

	/**
	 * search for files by mimetype
	 *
	 * @param string $mimetype
	 * @return array
	 */
	public function searchByMime($mimetype) {
		$result = $this->rootFolder->searchByMime($mimetype);
		$files = array();
		foreach ($result as $node) {
			$stat = $node->stat();
			$stat['path'] = $this->getRelativePath($node->getPath());
			$files[] = $stat;
		}
		return $files;
	}

	/**
	 * Get the owner for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getOwner($path) {
		try {
			$node = $this->rootFolder->get($path);
			return $node->getStorage()->getOwner($node->getInternalPath());
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * get the ETag for a file or folder
	 *
	 * @param string $path
	 * @return string
	 */
	public function getETag($path) {
		try {
			return $this->rootFolder->get($path)->getEtag();
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Get the path of a file by id, relative to the view
	 *
	 * Note that the resulting path is not guarantied to be unique for the id, multiple paths can point to the same file
	 *
	 * @param int $id
	 * @return string
	 */
	public function getPath($id) {
		try {
			$nodes = $this->rootFolder->getById($id);
			if (count($nodes) > 0) {
				return $this->getRelativePath($nodes[0]->getPath());
			} else {
				return null;
			}
		} catch (\Exception $e) {
			return null;
		}
	}
}
