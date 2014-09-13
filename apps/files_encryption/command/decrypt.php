<?php
/**
 * Copyright (c) 2014, Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

namespace OCA\Encryption\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Decrypt extends Command {
	protected function configure() {
		$this
			->setName('files:decrypt')
			->setDescription('decrypts files')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'user'
			)
			->addArgument(
				'password',
				InputArgument::REQUIRED,
				'password'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$user = $input->getArgument('user');
		$password = $input->getArgument('password');

		$this->decryptFiles($output, $user, $password);
	}

	protected function decryptFiles($output, $user, $password) {
		$view = new \OC\Files\View('/');
		$util = new \OCA\Encryption\Util($view, $user);
		$result = $util->initEncryption(
			array(
				'uid' => $user,
				'password' => $password
			)
		);
		if (!$result) {
			$output->writeln('<error>Could not decrypt files, please check the password and try again</error>');
			return false;
		}

		try {
			$successful = $util->decryptAll();
		} catch (\Exception $ex) {
			$output->writeln('<error>Decryption finished unexpectly: ' . $ex->getMessage() . '</error>', \OCP\Util::ERROR);
			$successful = false;
		}

		$util->closeEncryptionSession();
		return $successful;
	}
}
