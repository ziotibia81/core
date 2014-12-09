<?php

namespace OC\Setup;

use Doctrine\DBAL\Exception\DriverException;
use OCP\IDBConnection;

class MySQL extends AbstractDatabase {
	public $dbprettyname = 'MySQL/MariaDB';

	public function setupDatabase($username) {
		//check if the database user has admin right
		$connection = \OC::$server->getDatabaseConnection();

		//user already specified in config
		$oldUser = \OC_Config::getValue('dbuser', false);

		//we don't have a dbuser specified in config
		if($this->dbuser!=$oldUser) {
			//add prefix to the admin username to prevent collisions
			$adminUser=substr('oc_'.$username, 0, 16);

			$i = 1;
			while(true) {
				//this should be enough to check for admin rights in mysql
				$query = 'SELECT `user` FROM `mysql`.`user` WHERE `user` = ?';

				try {
					$result = $connection->executeQuery($query, array($adminUser));

					//current dbuser has admin rights
					if( $result->rowCount() === 0 ) {
						//use the admin login data for the new database user
						$this->dbuser = $adminUser;

						//create a random password so we don't need to store the admin password in the config file
						$this->dbpassword = \OC_Util::generateRandomBytes(30);

						$this->createDBUser($connection);

						break;
					} else {
						//repeat with different username
						$length = strlen((string)$i);
						$adminUser = substr('oc_'.$username, 0, 16 - $length).$i;
						$i++;
					}
				} catch (DriverException $e) {
					break;
				}
			};

			\OC_Config::setValue('dbuser', $this->dbuser);
			\OC_Config::setValue('dbpassword', $this->dbpassword);
		}

		//create the database
		$this->createDatabase($connection);

		//fill the database if needed
		$query='SELECT count(*) AS `cnt`
				FROM `information_schema`.`tables`
				WHERE `table_schema` = ? AND `table_name` = ?';
		$result = $connection->executeQuery($query, array($this->dbname, $this->tableprefix.'users'));
		if($result) {
			$row = $result->fetch();
		}
		if(!$result or $row['cnt'] == 0) {
			\OC_DB::createDbFromStructure($this->dbDefinitionFile);
		}
		$connection->close();
	}

	private function createDatabase(IDBConnection $connection) {
		$name = $this->dbname;
		$user = $this->dbuser;
		//we cant use OC_BD functions here because we need to connect as the administrative user.
		$query = 'CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8 COLLATE utf8_bin';
		$connection->executeUpdate($query);

		$query='GRANT ALL PRIVILEGES ON ? . * TO ?';
		try {
			$connection->executeUpdate($query, array($name, $user));
		} catch (\Exception $e) {
			//this query will fail if there aren't the right permissions, ignore the error
		}
	}

	private function createDBUser($connection) {
		$name = $this->dbuser;
		$password = $this->dbpassword;
		// we need to create 2 accounts, one for global use and one for local user. if we don't specify the local one,
		// the anonymous user would take precedence when there is one.
		$query = "CREATE USER ?@'localhost' IDENTIFIED BY ?";
		$connection->executeUpdate($query, array($name, $password));

		$query = "CREATE USER ?@'%' IDENTIFIED BY ?";
		$connection->executeUpdate($query, array($name, $password));
	}
}
