<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

class Info extends \PHPUnit_Framework_TestCase {
	private function buildInfo($id, $version, $installedVersion, $xml) {
		$appPath = \OC::$server->getTempManager()->getTemporaryFolder();
		mkdir($appPath . '/appinfo');
		file_put_contents($appPath . '/appinfo/info.xml', $xml);
		return new \OC\App\Info($id, $appPath, $version, $installedVersion);
	}

	public function testParseInfo() {
		$xml = '<?xml version="1.0"?>
<info>
	<id>files</id>
	<name>Files</name>
	<description>File Management</description>
	<licence>AGPL</licence>
	<author>Robin Appelman, Vincent Petry</author>
	<requiremin>4.93</requiremin>
	<shipped>true</shipped>
	<standalone/>
	<default_enable/>
	<types>
		<filesystem/>
	</types>
	<remote>
		<files>appinfo/remote.php</files>
		<webdav>appinfo/remote.php</webdav>
	</remote>
	<documentation>
		<user>user-files</user>
	</documentation>
</info>';
		$info = $this->buildInfo('files', '1.2.3', '1.2.2', $xml);
		$this->assertEquals('Files', $info->getName());
		$this->assertEquals('File Management', $info->getDescription());
		$this->assertEquals('AGPL', $info->getLicence());
		$this->assertEquals('Robin Appelman, Vincent Petry', $info->getAuthor());
		$this->assertEquals(array('files' => 'appinfo/remote.php', 'webdav' => 'appinfo/remote.php'), $info->getRemote());
		$this->assertEquals(array('user' => 'http://doc.owncloud.org/server/7.0/go.php?to=user-files'), $info->getDocumentation());
		$this->assertTrue($info->isType('filesystem'));
		$this->assertEquals('1.2.3', $info->getVersion());
		$this->assertEquals('1.2.2', $info->getInstalledVersion());
	}

	public function versionProvider() {
		return array(
			array('1.2.3', '1.2.2', true),
			array('1.2', '1.2.2', false),
			array('2', '1.2.2', true),
			array('1.2.3', '1.2.3', false),
			array('1.2.3', '1.2.4', false),
			array('1.20', '2.0.0', false),
		);
	}

	/**
	 * @param $version
	 * @param $installedVersion
	 * @param $needsUpgrade
	 * @dataProvider versionProvider
	 */
	public function testNeedUpdate($version, $installedVersion, $needsUpgrade) {
		$xml = '<?xml version="1.0"?><info></info>';
		$info = $this->buildInfo('test', $version, $installedVersion, $xml);
		$this->assertEquals($needsUpgrade, $info->needsUpdate());
	}
}
