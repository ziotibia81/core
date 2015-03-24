<?php
/**
 * @author Clark Tomlinson  <clark@owncloud.com>
 * @since 3/17/15, 10:31 AM
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


namespace OCA\Encryption;


use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Encryption\Crypto\Crypt;
use OCA\Files_Versions\Storage;
use OCP\App;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;
use OCP\Share;

class Util {
	/**
	 * @var View
	 */
	private $files;
	/**
	 * @var Filesystem
	 */
	private $filesystem;
	/**
	 * @var Crypt
	 */
	private $crypt;
	/**
	 * @var KeyManager
	 */
	private $keyManager;
	/**
	 * @var ILogger
	 */
	private $logger;
	/**
	 * @var bool|IUser
	 */
	private $user;
	/**
	 * @var IConfig
	 */
	private $config;

	/**
	 * Util constructor.
	 *
	 * @param View $files
	 * @param Filesystem $filesystem
	 * @param Crypt $crypt
	 * @param KeyManager $keyManager
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 */
	public function __construct(
		View $files,
		Filesystem $filesystem,
		Crypt $crypt,
		KeyManager $keyManager,
		ILogger $logger,
		IUserSession $userSession,
		IConfig $config
	) {
		$this->files = $files;
		$this->filesystem = $filesystem;
		$this->crypt = $crypt;
		$this->keyManager = $keyManager;
		$this->logger = $logger;
		$this->user = $userSession && $userSession->isLoggedIn() ? $userSession->getUser() : false;
		$this->config = $config;
	}

	/**
	 * @param $dirPath
	 * @return bool
	 * @throws \Exception
	 */
	public function encryptAll($dirPath) {

		$found = $this->findEncryptedFiles($dirPath);
		$result = true;

		if (App::isEnabled('files_versions')) {
			\OC_App::disable('files_versions');
		}

		$encryptedFiles = [];

		// Encrypt Unencrypted files
		foreach ($found['plain'] as $plainFile) {
			// get file info
			$fileInfo = $this->filesystem->getFileInfo($plainFile['path']);

			// Relative to data/<user>/file
			$relPath = $plainFile['path'];

			// Relative to /data
			$rawPath = '/' . $this->user->getUID() . '/files/' . $plainFile['path'];

			// Keep timestamp
			$timestamp = $fileInfo['mtime'];

			// Open plain file handle for binary reading
			$plainHandle = $this->files->fopen($rawPath, 'rb');

			// Open enc file handle for binary writing, with some filenames as original plain file
			$encHandle = fopen('crypt://' . $rawPath . '.part', 'wb');

			if (is_resource($encHandle) && is_resource($plainHandle)) {
				// Move plain file to a temp location
				$size = stream_copy_to_stream($plainHandle, $encHandle);

				fclose($encHandle);
				fclose($plainHandle);

				$fakeRoot = $this->files->getRoot();
				$this->files->chroot('/' . $this->user->getUID() . '/files');

				$this->files->rename($relPath . '.part', $relPath);

				// Set Timestamp
				$this->files->touch($relPath, $timestamp);

				$encSize = $this->files->filesize($relPath);

				$this->files->chroot($fakeRoot);

				// Add the file to the cache
				$this->filesystem->putFileInfo($relPath,
					[
						'encrypted' => true,
						'size' => $encSize,
						'unencrypted_size' => $size,
						'etag' => $fileInfo['etag']
					]);

				$encryptedFiles[] = $relPath;
			} else {
				$this->logger->critical('Encryption initial encryption could not encrypt ' . $rawPath);
				$result = false;
			}
		}

		if (!App::isEnabled('files_versions')) {
			\OC_App::enable('files_versions');
		}

		return $result && $this->encryptVersions($encryptedFiles);
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function decryptAll() {
		$found = $this->findEncryptedFiles($dirPath);
		$result = true;

		if (App::isEnabled('files_versions')) {
			\OC_App::disable('files_versions');
		}

		$decryptedFiles = [];

		// Encrypt Unencrypted files
		foreach ($found['encrypted'] as $plainFile) {
			// get file info
			$fileInfo = $this->filesystem->getFileInfo($plainFile['path']);

			// Relative to data/<user>/file
			$relPath = $plainFile['path'];

			// Relative to /data
			$rawPath = '/' . $this->user->getUID() . '/files/' . $plainFile['path'];

			// Keep timestamp
			$timestamp = $fileInfo['mtime'];

			// Open plain file handle for binary reading
			$plainHandle = $this->files->fopen($rawPath, 'rb');

			// Open enc file handle for binary writing, with some filenames as original plain file
			$encHandle = fopen('crypt://' . $rawPath . '.part', 'wb');


			if ($encHandle === false) {
				$this->logger->critical('Encryption library couldn\'t open ' . $rawPath . 'decryption failed');
				$result = false;
				continue;
			}

			// Open plain file handle for binary writing, with same filename as original plain file
			$plainHandle = $this->files->fopen($rawPath . '.part', 'wb');
			if ($plainHandle === false) {
				$this->logger->critical('Encryption library couldn\'t open ' . $rawPath . 'decryption failed');
				$result = false;
				continue;
			}

			fclose($encHandle);
			fclose($plainHandle);


			$fakeRoot = $this->files->getRoot();
			$this->files->chroot('/' . $this->user->getUID() . '/files');

			$this->files->rename($relPath . '.part', $relPath);

			// Set Timestamp
			$this->files->touch($relPath, $timestamp);

			$this->files->chroot($fakeRoot);

			// Add the file to the cache
			$this->filesystem->putFileInfo($relPath,
				[
					'encrypted' => false,
					'size' => $size,
					'unencrypted_size' => 0,
					'etag' => $fileInfo['etag']
				]);

			$decryptedFiles[] = $relPath;

			if (!App::isEnabled('files_versions')) {
				\OC_App::enable('files_versions');
			}

			// If there are broken encrypted files than th complete decryption was a fail
			if (!empty($found['broken'])) {
				$result = false;
			}

			if ($result) {
				$this->keyManager->backupAllKeys('decryptAll', false, false);
				$this->keyManager->deleteAllKeys();
			}

		}
		return $result;
	}

	/**
	 * @param $dirPath
	 * @param bool $found
	 * @return array|bool
	 */
	private function findEncryptedFiles($dirPath, &$found = false) {

		if ($found === false) {
			$found = [
				'plain' => [],
				'encrypted' => [],
				'broken' => [],
			];
		}

		if ($this->files->is_dir($dirPath) && $handle = $this->files->opendir($dirPath)) {
			if (is_resource($handle)) {
				while (($file = readdir($handle) !== false)) {
					if ($file !== '.' && $file !== '..') {

						// Skip stray part files
						if ($this->isPartialFilePath($file)) {
							continue;
						}

						$filePath = $dirPath . '/' . $this->files->getRelativePath('/' . $file);
						$relPath = $this->stripUserFilesPath($filePath);

						// If the path is a directory, search its contents
						if ($this->files->is_dir($filePath)) {
							// Recurse back
							$this->findEncryptedFiles($filePath);

							/*
							 * If the path is a file,
							 * determine where they got re-enabled :/
							*/
						} elseif ($this->files->is_file($filePath)) {
							$isEncryptedPath = $this->isEncryptedPath($filePath);

							/**
							 * If the file is encrypted
							 *
							 * @note: if the userId is
							 * empty or not set, file will
							 * be detected as plain
							 * @note: this is inefficient;
							 * scanning every file like this
							 * will eat server resources :(
							 * fixMe: xxx find better way
							 */
							if ($isEncryptedPath) {
								$fileKey = $this->keyManager->getFileKey($relPath);
								$shareKey = $this->keyManager->getShareKey($relPath);
								// If file is encrypted but now file key is available, throw exception
								if (!$fileKey || !$shareKey) {
									$this->logger->error('Encryption library, no keys avilable to decrypt the file: ' . $file);
									$found['broken'][] = [
										'name' => $file,
										'path' => $filePath,
									];
								} else {
									$found['encrypted'][] = [
										'name' => $file,
										'path' => $filePath
									];
								}
							} else {
								$found['plain'][] = [
									'name' => $file,
									'path' => $filePath
								];
							}
						}
					}

				}
			}
		}

		return $found;
	}

	/**
	 * @param $path
	 * @return bool
	 */
	private
	function isPartialFilePath($path) {
		$extension = pathinfo($path, PATHINFO_EXTENSION);

		if ($extension === 'part') {
			return true;
		}
		return false;
	}

	/**
	 * @param $filePath
	 * @return bool|string
	 */
	private
	function stripUserFilesPath($filePath) {
		$split = $this->splitPath($filePath);

		// It is not a file relative to data/user/files
		if (count($split) < 4 || $split[2] !== 'files') {
			return false;
		}

		$sliced = array_slice($split, 3);

		return implode('/', $sliced);

	}

	/**
	 * @param $filePath
	 * @return array
	 */
	private
	function splitPath($filePath) {
		$normalized = $this->filesystem->normalizePath($filePath);

		return explode('/', $normalized);
	}

	/**
	 * @param $filePath
	 * @return bool
	 */
	private
	function isEncryptedPath($filePath) {
		$data = '';

		// We only need 24 bytes from the last chunck
		if ($this->files->file_exists($filePath)) {
			$handle = $this->files->fopen($filePath, 'r');
			if (is_resource($handle)) {
				// Suppress fseek warning, we handle the case that fseek
				// doesn't work in the else branch
				if (@fseek($handle, -24, SEEK_END) === 0) {
					$data = fgets($handle);
				} else {
					// if fseek failed on the storage we create a local copy
					// from the file and read this one
					fclose($handle);
					$localFile = $this->files->getLocalFile($filePath);
					$handle = fopen($localFile, 'r');

					if (is_resource($handle) && fseek($handle,
							-24,
							SEEK_END) === 0
					) {
						$data = fgets($handle);
					}
				}
				fclose($handle);
				return $this->crypt->isCatfileContent($data);
			}
		}
	}

	/**
	 * @param $encryptedFiles
	 * @return bool
	 */
	private
	function encryptVersions($encryptedFiles) {
		$successful = true;

		if (App::isEnabled('files_versions')) {
			foreach ($encryptedFiles as $filename) {
				$versions = Storage::getVersions($this->user->getUID(),
					$filename);
				foreach ($versions as $version) {
					$path = '/' . $this->user->getUID() . '/files_versions/' . $versions['path'] . '.v' . $version['version'];

					$encHandle = fopen('crypt://' . $path . '.part', 'wb');

					if ($encHandle === false) {
						$this->logger->critical('Encryption Library couldn\'t open ' . $path . '.part, decryption failed');
						$successful = false;
						continue;
					}

					$plainHandle = $this->files->fopen($path, 'rb');
					if ($plainHandle === false) {
						$this->logger->critical('Encryption Library couldn\'t open ' . $path . '.part, decryption failed');
						$successful = false;
						continue;
					}

					stream_copy_to_stream($plainHandle, $encHandle);

					fclose($encHandle);
					fclose($plainHandle);

					$this->files->rename($path . '.part', $path);
				}
			}
		}
		return $successful;
	}

	/**
	 * @return bool
	 */
	public
	function recoveryEnabledForUser() {
		$recoveryMode = $this->config->getUserValue($this->user->getUID(),
			'encryption',
			'recoveryEnabled',
			0);

		return ($recoveryMode === '1');
	}

	/**
	 * @param $enabled
	 * @return bool
	 */
	public
	function setRecoveryForUser($enabled) {
		$value = $enabled ? '1' : '0';

		try {
			$this->config->setUserValue($this->user->getUID(),
				'encryption',
				'recoveryEnabled',
				$value);
			return true;
		} catch (PreConditionNotMetException $e) {
			return false;
		}
	}

	/**
	 * @param $recoveryPassword
	 */
	public
	function recoverUsersFiles($recoveryPassword) {
		// todo: get system private key here
//		$this->keyManager->get
		$privateKey = $this->crypt->decryptPrivateKey($encryptedKey,
			$recoveryPassword);

		$this->recoverAllFiles('/', $privateKey);
	}

	/**
	 * @param $encryptedFiles
	 * @return bool
	 */
	private
	function decryptVersions($encryptedFiles) {
		$successful = true;

		if (App::isEnabled('files_versions')) {
			foreach ($encryptedFiles as $filename) {
				$versions = Storage::getVersions($this->user->getUID(),
					$filename);
				foreach ($versions as $version) {
					$path = '/' . $this->user->getUID() . '/files_versions/' . $versions['path'] . '.v' . $version['version'];

					$encHandle = fopen('crypt://' . $path . '.part', 'wb');

					if ($encHandle === false) {
						$this->logger->critical('Encryption Library couldn\'t open ' . $path . '.part, decryption failed');
						$successful = false;
						continue;
					}

					$plainHandle = $this->files->fopen($path, 'rb');
					if ($plainHandle === false) {
						$this->logger->critical('Encryption Library couldn\'t open ' . $path . '.part, decryption failed');
						$successful = false;
						continue;
					}

					stream_copy_to_stream($plainHandle, $encHandle);

					fclose($encHandle);
					fclose($plainHandle);

					$this->files->rename($path . '.part', $path);
				}
			}
		}
		return $successful;
	}

	/**
	 * @param string $uid
	 * @return bool
	 */
	public
	function userHasFiles($uid) {
		return $this->files->file_exists($uid . '/files');
	}

	/**
	 * @param $path
	 * @param $privateKey
	 */
	private
	function recoverAllFiles($path, $privateKey) {
		// Todo relocate to storage
		$dirContent = $this->files->getDirectoryContent();

		foreach ($dirContent as $item) {
			// Get relative path from encryption/keyfiles
			$filePath = substr($item['path'], strlen('encryption/keys'));
			if ($this->files->is_dir($this->user->getUID() . '/files' . '/' . $filePath)) {
				$this->recoverAllFiles($filePath . '/', $privateKey);
			} else {
				$this->recoverFile($filePath, $privateKey);
			}
		}

	}

	/**
	 * @param $filePath
	 * @param $privateKey
	 */
	private
	function recoverFile($filePath, $privateKey) {
		$sharingEnabled = Share::isEnabled();

		// Find out who, if anyone, is sharing the file
		if ($sharingEnabled) {
			$result = Share::getUsersSharingFile($filePath,
				$this->user->getUID(),
				true);
			$userIds = $result['users'];
			$userIds[] = $this->publicShareKeyId;
		} else {
			$userIds = [
				$this->user->getUID(),
				$this->recoveryKeyId
			];
		}
		$filteredUids = $this->filterShareReadyUsers($userIds);

		// Decrypt file key
		$encKeyFile = $this->keyManager->getFileKey($filePath);
		$shareKey = $this->keyManager->getShareKey($filePath);
		$plainKeyFile = $this->crypt->multiKeyDecrypt($encKeyFile,
			$shareKey,
			$privateKey);

		// Encrypt the file key again to all users, this time with the new publick keyt for the recovered user
		$userPublicKeys = $this->keyManager->getPublicKeys($filteredUids['ready']);
		$multiEncryptionKey = $this->crypt->multiKeyEncrypt($plainKeyFile,
			$userPublicKeys);

		$this->keyManager->setFileKeys($multiEncryptionKey['data']);
		$this->keyManager->setShareKeys($multiEncryptionKey['keys']);
	}

	/**
	 * @param $userIds
	 * @return array
	 */
	private
	function filterShareReadyUsers($userIds) {
		// This array will collect the filtered IDs
		$readyIds = $unreadyIds = [];

		// Loop though users and create array of UIDs that need new keyfiles
		foreach ($userIds as $user) {
			// Check that the user is encryption capable, or is the
			// public system user (for public shares)
			if ($this->isUserReady($user)) {
				// construct array of ready UIDs for keymanager
				$readyIds[] = $user;
			} else {
				// Construct array of unready UIDs for keymanager
				$unreadyIds[] = $user;

				// Log warning; we cant do necessary setup here
				// because we don't have the user passphrase
				$this->logger->warning('Encryption Library ' . $this->user->getUID() . ' is not setup for encryption');
			}
		}
		return [
			'ready' => $readyIds,
			'unready' => $unreadyIds
		];
	}

	private
	function isUserReady($user) {
		if ($user === $this->publicShareKeyId || $user === $this->recoveryKeyId) {
			return true;
		}
		return $this->keyManager->ready();
	}

}
