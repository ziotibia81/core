<?php
/**
 * @author Clark Tomlinson  <fallen013@gmail.com>
 * @since 3/9/15, 2:44 PM
 * @link http:/www.clarkt.com
 * @copyright Clark Tomlinson © 2015
 *
 */

namespace OCA\Encryption;


use OCA\Encryption\Crypto\Crypt;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;

class Migrator {

	/**
	 * @var bool
	 */
	private $status = false;
	/**
	 * @var string
	 */
	private $user;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string
	 */
	public static $migrationOpen = '0';
	/**
	 * @var string
	 */
	public static $migrationInProgress = '-1';
	/**
	 * @var string
	 */
	public static $migrationComplete = '1';
	/**
	 * @var IUserManager
	 */
	private $userManager;
	/**
	 * @var ILogger
	 */
	private $log;
	/**
	 * @var Crypt
	 */
	private $crypt;

	/**
	 * Migrator constructor.
	 *
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param ILogger $log
	 * @param Crypt $crypt
	 * @internal param IUserSession $userSession
	 */
	public function __construct(IConfig $config, IUserManager $userManager, ILogger $log, Crypt $crypt) {
		$this->config = $config;
		$this->userManager = $userManager;
		$this->log = $log;
		$this->crypt = $crypt;
	}

	/**
	 * @param $userId
	 * @return bool|string
	 */
	public function getStatus($userId) {
		if ($this->userManager->userExists($userId)) {
			$this->status = $this->config->getUserValue($userId,
				'encryption',
				'migrationStatus',
				false);

			if (!$this->status) {
				$this->config->setUserValue($userId,
					'encryption',
					'migrationStatus',
					self::$migrationOpen);
				$this->status = self::$migrationOpen;
			}
		}

		return $this->status;
	}

	/**
	 * @return bool
	 */
	public function beginMigration() {
		$status = $this->setMigrationStatus(self::$migrationInProgress,
			self::$migrationOpen);

		if ($status) {
			$this->log->info('Encryption Library Start migration to encrypt for ' . $this->user);
			return $status;
		}
		$this->log->warning('Encryption Library Could not activate migration for ' . $this->user . '. Probably another process already started the inital encryption');
		return $status;
	}

	/**
	 * @param $status
	 * @param bool $preCondition
	 * @return bool
	 */
	private function setMigrationStatus($status, $preCondition = false) {
		// Convert to string if preCondition is set
		$preCondition = ($preCondition === false) ? null : (string)$preCondition;

		try {
			$this->config->setUserValue($this->user,
				'encryption',
				'migrationStatus',
				(string)$status,
				$preCondition);
			return true;
		} catch (PreConditionNotMetException $e) {
			return false;
		}
	}

	/**
	 * @return bool
	 */
	public function finishMigration() {
		$result = $this->setMigrationStatus(self::$migrationComplete);

		if ($result) {
			$this->log->info('Encryption library finish migration succcessfully for ' . $this->user);
			return $result;
		}

		$this->log->warning('Encryption library could not deactivate migration mode for ' . $this->user);
		return $result;

	}

	/**
	 * @return bool
	 */
	public function resetMigrationStatus() {
		return $this->setMigrationStatus(self::$migrationOpen);
	}

	/**
	 * @param $uid
	 */
	public function setUser($uid) {
		$this->user = $uid;
	}
}
