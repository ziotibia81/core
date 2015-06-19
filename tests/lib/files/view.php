<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file. */

namespace Test\Files;

use OC\Files\Cache\Watcher;
use OC\Files\Storage\Common;
use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Temporary;
use OC\Files\Filesystem;
use OCP\Lock\ILockingProvider;

class TemporaryNoTouch extends \OC\Files\Storage\Temporary {
	public function touch($path, $mtime = null) {
		return false;
	}
}

class TemporaryNoCross extends \OC\Files\Storage\Temporary {
	public function copyFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		return Common::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	public function moveFromStorage(\OCP\Files\Storage $sourceStorage, $sourceInternalPath, $targetInternalPath) {
		return Common::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}
}

class TemporaryNoLocal extends \OC\Files\Storage\Temporary {
	public function instanceOfStorage($className) {
		if ($className === '\OC\Files\Storage\Local') {
			return false;
		} else {
			return parent::instanceOfStorage($className);
		}
	}
}

class View extends \Test\TestCase {
	/**
	 * @var \OC\Files\Storage\Storage[] $storages
	 */
	private $storages = array();
	private $user;

	/** @var \OC\Files\Storage\Storage */
	private $tempStorage;

	protected function setUp() {
		parent::setUp();

		\OC_User::clearBackends();
		\OC_User::useBackend(new \OC_User_Dummy());

		//login
		\OC_User::createUser('test', 'test');

		$this->loginAsUser('test');
		$this->user = \OC_User::getUser();
		// clear mounts but somehow keep the root storage
		// that was initialized above...
		\OC\Files\Filesystem::clearMounts();

		$this->tempStorage = null;
	}

	protected function tearDown() {
		\OC_User::setUserId($this->user);
		foreach ($this->storages as $storage) {
			$cache = $storage->getCache();
			$ids = $cache->getAll();
			$cache->clear();
		}

		if ($this->tempStorage && !\OC_Util::runningOnWindows()) {
			system('rm -rf ' . escapeshellarg($this->tempStorage->getDataDir()));
		}

		$this->logout();
		parent::tearDown();
	}

	/**
	 * @medium
	 */
	public function testCacheAPI() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$storage3 = $this->getTestStorage();
		$root = $this->getUniqueID('/');
		\OC\Files\Filesystem::mount($storage1, array(), $root . '/');
		\OC\Files\Filesystem::mount($storage2, array(), $root . '/substorage');
		\OC\Files\Filesystem::mount($storage3, array(), $root . '/folder/anotherstorage');
		$textSize = strlen("dummy file data\n");
		$imageSize = filesize(\OC::$SERVERROOT . '/core/img/logo.png');
		$storageSize = $textSize * 2 + $imageSize;

		$storageInfo = $storage3->getCache()->get('');
		$this->assertEquals($storageSize, $storageInfo['size']);

		$rootView = new \OC\Files\View($root);

		$cachedData = $rootView->getFileInfo('/foo.txt');
		$this->assertEquals($textSize, $cachedData['size']);
		$this->assertEquals('text/plain', $cachedData['mimetype']);
		$this->assertNotEquals(-1, $cachedData['permissions']);

		$cachedData = $rootView->getFileInfo('/');
		$this->assertEquals($storageSize * 3, $cachedData['size']);
		$this->assertEquals('httpd/unix-directory', $cachedData['mimetype']);

		// get cached data excluding mount points
		$cachedData = $rootView->getFileInfo('/', false);
		$this->assertEquals($storageSize, $cachedData['size']);
		$this->assertEquals('httpd/unix-directory', $cachedData['mimetype']);

		$cachedData = $rootView->getFileInfo('/folder');
		$this->assertEquals($storageSize + $textSize, $cachedData['size']);
		$this->assertEquals('httpd/unix-directory', $cachedData['mimetype']);

		$folderData = $rootView->getDirectoryContent('/');
		/**
		 * expected entries:
		 * folder
		 * foo.png
		 * foo.txt
		 * substorage
		 */
		$this->assertEquals(4, count($folderData));
		$this->assertEquals('folder', $folderData[0]['name']);
		$this->assertEquals('foo.png', $folderData[1]['name']);
		$this->assertEquals('foo.txt', $folderData[2]['name']);
		$this->assertEquals('substorage', $folderData[3]['name']);

		$this->assertEquals($storageSize + $textSize, $folderData[0]['size']);
		$this->assertEquals($imageSize, $folderData[1]['size']);
		$this->assertEquals($textSize, $folderData[2]['size']);
		$this->assertEquals($storageSize, $folderData[3]['size']);

		$folderData = $rootView->getDirectoryContent('/substorage');
		/**
		 * expected entries:
		 * folder
		 * foo.png
		 * foo.txt
		 */
		$this->assertEquals(3, count($folderData));
		$this->assertEquals('folder', $folderData[0]['name']);
		$this->assertEquals('foo.png', $folderData[1]['name']);
		$this->assertEquals('foo.txt', $folderData[2]['name']);

		$folderView = new \OC\Files\View($root . '/folder');
		$this->assertEquals($rootView->getFileInfo('/folder'), $folderView->getFileInfo('/'));

		$cachedData = $rootView->getFileInfo('/foo.txt');
		$this->assertFalse($cachedData['encrypted']);
		$id = $rootView->putFileInfo('/foo.txt', array('encrypted' => true));
		$cachedData = $rootView->getFileInfo('/foo.txt');
		$this->assertTrue($cachedData['encrypted']);
		$this->assertEquals($cachedData['fileid'], $id);

		$this->assertFalse($rootView->getFileInfo('/non/existing'));
		$this->assertEquals(array(), $rootView->getDirectoryContent('/non/existing'));
	}

	/**
	 * @medium
	 */
	function testGetPath() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$storage3 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');
		\OC\Files\Filesystem::mount($storage3, array(), '/folder/anotherstorage');

		$rootView = new \OC\Files\View('');

		$cachedData = $rootView->getFileInfo('/foo.txt');
		$id1 = $cachedData['fileid'];
		$this->assertEquals('/foo.txt', $rootView->getPath($id1));

		$cachedData = $rootView->getFileInfo('/substorage/foo.txt');
		$id2 = $cachedData['fileid'];
		$this->assertEquals('/substorage/foo.txt', $rootView->getPath($id2));

		$folderView = new \OC\Files\View('/substorage');
		$this->assertEquals('/foo.txt', $folderView->getPath($id2));
		$this->assertNull($folderView->getPath($id1));
	}

	/**
	 * @medium
	 */
	function testMountPointOverwrite() {
		$storage1 = $this->getTestStorage(false);
		$storage2 = $this->getTestStorage();
		$storage1->mkdir('substorage');
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');

		$rootView = new \OC\Files\View('');
		$folderContent = $rootView->getDirectoryContent('/');
		$this->assertEquals(4, count($folderContent));
	}

	function testCacheIncompleteFolder() {
		$storage1 = $this->getTestStorage(false);
		\OC\Files\Filesystem::clearMounts();
		\OC\Files\Filesystem::mount($storage1, array(), '/incomplete');
		$rootView = new \OC\Files\View('/incomplete');

		$entries = $rootView->getDirectoryContent('/');
		$this->assertEquals(3, count($entries));

		// /folder will already be in the cache but not scanned
		$entries = $rootView->getDirectoryContent('/folder');
		$this->assertEquals(1, count($entries));
	}

	public function testAutoScan() {
		$storage1 = $this->getTestStorage(false);
		$storage2 = $this->getTestStorage(false);
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');
		$textSize = strlen("dummy file data\n");

		$rootView = new \OC\Files\View('');

		$cachedData = $rootView->getFileInfo('/');
		$this->assertEquals('httpd/unix-directory', $cachedData['mimetype']);
		$this->assertEquals(-1, $cachedData['size']);

		$folderData = $rootView->getDirectoryContent('/substorage/folder');
		$this->assertEquals('text/plain', $folderData[0]['mimetype']);
		$this->assertEquals($textSize, $folderData[0]['size']);
	}

	/**
	 * @medium
	 */
	function testSearch() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$storage3 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');
		\OC\Files\Filesystem::mount($storage3, array(), '/folder/anotherstorage');

		$rootView = new \OC\Files\View('');

		$results = $rootView->search('foo');
		$this->assertEquals(6, count($results));
		$paths = array();
		foreach ($results as $result) {
			$this->assertEquals($result['path'], \OC\Files\Filesystem::normalizePath($result['path']));
			$paths[] = $result['path'];
		}
		$this->assertContains('/foo.txt', $paths);
		$this->assertContains('/foo.png', $paths);
		$this->assertContains('/substorage/foo.txt', $paths);
		$this->assertContains('/substorage/foo.png', $paths);
		$this->assertContains('/folder/anotherstorage/foo.txt', $paths);
		$this->assertContains('/folder/anotherstorage/foo.png', $paths);

		$folderView = new \OC\Files\View('/folder');
		$results = $folderView->search('bar');
		$this->assertEquals(2, count($results));
		$paths = array();
		foreach ($results as $result) {
			$paths[] = $result['path'];
		}
		$this->assertContains('/anotherstorage/folder/bar.txt', $paths);
		$this->assertContains('/bar.txt', $paths);

		$results = $folderView->search('foo');
		$this->assertEquals(2, count($results));
		$paths = array();
		foreach ($results as $result) {
			$paths[] = $result['path'];
		}
		$this->assertContains('/anotherstorage/foo.txt', $paths);
		$this->assertContains('/anotherstorage/foo.png', $paths);

		$this->assertEquals(6, count($rootView->searchByMime('text')));
		$this->assertEquals(3, count($folderView->searchByMime('text')));
	}

	/**
	 * @medium
	 */
	function testWatcher() {
		$storage1 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		$storage1->getWatcher()->setPolicy(Watcher::CHECK_ALWAYS);

		$rootView = new \OC\Files\View('');

		$cachedData = $rootView->getFileInfo('foo.txt');
		$this->assertEquals(16, $cachedData['size']);

		$rootView->putFileInfo('foo.txt', array('storage_mtime' => 10));
		$storage1->file_put_contents('foo.txt', 'foo');
		clearstatcache();

		$cachedData = $rootView->getFileInfo('foo.txt');
		$this->assertEquals(3, $cachedData['size']);
	}

	/**
	 * @medium
	 */
	function testCopyBetweenStorageNoCross() {
		$storage1 = $this->getTestStorage(true, '\Test\Files\TemporaryNoCross');
		$storage2 = $this->getTestStorage(true, '\Test\Files\TemporaryNoCross');
		$this->copyBetweenStorages($storage1, $storage2);
	}

	/**
	 * @medium
	 */
	function testCopyBetweenStorageCross() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$this->copyBetweenStorages($storage1, $storage2);
	}

	/**
	 * @medium
	 */
	function testCopyBetweenStorageCrossNonLocal() {
		$storage1 = $this->getTestStorage(true, '\Test\Files\TemporaryNoLocal');
		$storage2 = $this->getTestStorage(true, '\Test\Files\TemporaryNoLocal');
		$this->copyBetweenStorages($storage1, $storage2);
	}

	function copyBetweenStorages($storage1, $storage2) {
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');

		$rootView = new \OC\Files\View('');
		$rootView->mkdir('substorage/emptyfolder');
		$rootView->copy('substorage', 'anotherfolder');
		$this->assertTrue($rootView->is_dir('/anotherfolder'));
		$this->assertTrue($rootView->is_dir('/substorage'));
		$this->assertTrue($rootView->is_dir('/anotherfolder/emptyfolder'));
		$this->assertTrue($rootView->is_dir('/substorage/emptyfolder'));
		$this->assertTrue($rootView->file_exists('/anotherfolder/foo.txt'));
		$this->assertTrue($rootView->file_exists('/anotherfolder/foo.png'));
		$this->assertTrue($rootView->file_exists('/anotherfolder/folder/bar.txt'));
		$this->assertTrue($rootView->file_exists('/substorage/foo.txt'));
		$this->assertTrue($rootView->file_exists('/substorage/foo.png'));
		$this->assertTrue($rootView->file_exists('/substorage/folder/bar.txt'));
	}

	/**
	 * @medium
	 */
	function testMoveBetweenStorageNoCross() {
		$storage1 = $this->getTestStorage(true, '\Test\Files\TemporaryNoCross');
		$storage2 = $this->getTestStorage(true, '\Test\Files\TemporaryNoCross');
		$this->moveBetweenStorages($storage1, $storage2);
	}

	/**
	 * @medium
	 */
	function testMoveBetweenStorageCross() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$this->moveBetweenStorages($storage1, $storage2);
	}

	/**
	 * @medium
	 */
	function testMoveBetweenStorageCrossNonLocal() {
		$storage1 = $this->getTestStorage(true, '\Test\Files\TemporaryNoLocal');
		$storage2 = $this->getTestStorage(true, '\Test\Files\TemporaryNoLocal');
		$this->moveBetweenStorages($storage1, $storage2);
	}

	function moveBetweenStorages($storage1, $storage2) {
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');

		$rootView = new \OC\Files\View('');
		$rootView->rename('foo.txt', 'substorage/folder/foo.txt');
		$this->assertFalse($rootView->file_exists('foo.txt'));
		$this->assertTrue($rootView->file_exists('substorage/folder/foo.txt'));
		$rootView->rename('substorage/folder', 'anotherfolder');
		$this->assertFalse($rootView->is_dir('substorage/folder'));
		$this->assertTrue($rootView->file_exists('anotherfolder/foo.txt'));
		$this->assertTrue($rootView->file_exists('anotherfolder/bar.txt'));
	}

	/**
	 * @medium
	 */
	function testUnlink() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');

		$rootView = new \OC\Files\View('');
		$rootView->file_put_contents('/foo.txt', 'asd');
		$rootView->file_put_contents('/substorage/bar.txt', 'asd');

		$this->assertTrue($rootView->file_exists('foo.txt'));
		$this->assertTrue($rootView->file_exists('substorage/bar.txt'));

		$this->assertTrue($rootView->unlink('foo.txt'));
		$this->assertTrue($rootView->unlink('substorage/bar.txt'));

		$this->assertFalse($rootView->file_exists('foo.txt'));
		$this->assertFalse($rootView->file_exists('substorage/bar.txt'));
	}

	/**
	 * @medium
	 */
	function testUnlinkRootMustFail() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), '/substorage');

		$rootView = new \OC\Files\View('');
		$rootView->file_put_contents('/foo.txt', 'asd');
		$rootView->file_put_contents('/substorage/bar.txt', 'asd');

		$this->assertFalse($rootView->unlink(''));
		$this->assertFalse($rootView->unlink('/'));
		$this->assertFalse($rootView->unlink('substorage'));
		$this->assertFalse($rootView->unlink('/substorage'));
	}

	/**
	 * @medium
	 */
	function testTouch() {
		$storage = $this->getTestStorage(true, '\Test\Files\TemporaryNoTouch');

		\OC\Files\Filesystem::mount($storage, array(), '/');

		$rootView = new \OC\Files\View('');
		$oldCachedData = $rootView->getFileInfo('foo.txt');

		$rootView->touch('foo.txt', 500);

		$cachedData = $rootView->getFileInfo('foo.txt');
		$this->assertEquals(500, $cachedData['mtime']);
		$this->assertEquals($oldCachedData['storage_mtime'], $cachedData['storage_mtime']);

		$rootView->putFileInfo('foo.txt', array('storage_mtime' => 1000)); //make sure the watcher detects the change
		$rootView->file_put_contents('foo.txt', 'asd');
		$cachedData = $rootView->getFileInfo('foo.txt');
		$this->assertGreaterThanOrEqual($oldCachedData['mtime'], $cachedData['mtime']);
		$this->assertEquals($cachedData['storage_mtime'], $cachedData['mtime']);
	}

	/**
	 * @medium
	 */
	function testViewHooks() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$defaultRoot = \OC\Files\Filesystem::getRoot();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), $defaultRoot . '/substorage');
		\OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'dummyHook');

		$rootView = new \OC\Files\View('');
		$subView = new \OC\Files\View($defaultRoot . '/substorage');
		$this->hookPath = null;

		$rootView->file_put_contents('/foo.txt', 'asd');
		$this->assertNull($this->hookPath);

		$subView->file_put_contents('/foo.txt', 'asd');
		$this->assertEquals('/substorage/foo.txt', $this->hookPath);
	}

	private $hookPath;

	public function dummyHook($params) {
		$this->hookPath = $params['path'];
	}

	public function testSearchNotOutsideView() {
		$storage1 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		$storage1->rename('folder', 'foo');
		$scanner = $storage1->getScanner();
		$scanner->scan('');

		$view = new \OC\Files\View('/foo');

		$result = $view->search('.txt');
		$this->assertCount(1, $result);
	}

	/**
	 * @param bool $scan
	 * @param string $class
	 * @return \OC\Files\Storage\Storage
	 */
	private function getTestStorage($scan = true, $class = '\OC\Files\Storage\Temporary') {
		/**
		 * @var \OC\Files\Storage\Storage $storage
		 */
		$storage = new $class(array());
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo.png');
		$storage->mkdir('folder');
		$storage->file_put_contents('foo.txt', $textData);
		$storage->file_put_contents('foo.png', $imgData);
		$storage->file_put_contents('folder/bar.txt', $textData);

		if ($scan) {
			$scanner = $storage->getScanner();
			$scanner->scan('');
		}
		$this->storages[] = $storage;
		return $storage;
	}

	/**
	 * @medium
	 */
	function testViewHooksIfRootStartsTheSame() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$defaultRoot = \OC\Files\Filesystem::getRoot();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), $defaultRoot . '_substorage');
		\OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'dummyHook');

		$subView = new \OC\Files\View($defaultRoot . '_substorage');
		$this->hookPath = null;

		$subView->file_put_contents('/foo.txt', 'asd');
		$this->assertNull($this->hookPath);
	}

	private $hookWritePath;
	private $hookCreatePath;
	private $hookUpdatePath;

	public function dummyHookWrite($params) {
		$this->hookWritePath = $params['path'];
	}

	public function dummyHookUpdate($params) {
		$this->hookUpdatePath = $params['path'];
	}

	public function dummyHookCreate($params) {
		$this->hookCreatePath = $params['path'];
	}

	public function testEditNoCreateHook() {
		$storage1 = $this->getTestStorage();
		$storage2 = $this->getTestStorage();
		$defaultRoot = \OC\Files\Filesystem::getRoot();
		\OC\Files\Filesystem::mount($storage1, array(), '/');
		\OC\Files\Filesystem::mount($storage2, array(), $defaultRoot);
		\OC_Hook::connect('OC_Filesystem', 'post_create', $this, 'dummyHookCreate');
		\OC_Hook::connect('OC_Filesystem', 'post_update', $this, 'dummyHookUpdate');
		\OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'dummyHookWrite');

		$view = new \OC\Files\View($defaultRoot);
		$this->hookWritePath = $this->hookUpdatePath = $this->hookCreatePath = null;

		$view->file_put_contents('/asd.txt', 'foo');
		$this->assertEquals('/asd.txt', $this->hookCreatePath);
		$this->assertNull($this->hookUpdatePath);
		$this->assertEquals('/asd.txt', $this->hookWritePath);

		$this->hookWritePath = $this->hookUpdatePath = $this->hookCreatePath = null;

		$view->file_put_contents('/asd.txt', 'foo');
		$this->assertNull($this->hookCreatePath);
		$this->assertEquals('/asd.txt', $this->hookUpdatePath);
		$this->assertEquals('/asd.txt', $this->hookWritePath);

		\OC_Hook::clear('OC_Filesystem', 'post_create');
		\OC_Hook::clear('OC_Filesystem', 'post_update');
		\OC_Hook::clear('OC_Filesystem', 'post_write');
	}

	/**
	 * @dataProvider resolvePathTestProvider
	 */
	public function testResolvePath($expected, $pathToTest) {
		$storage1 = $this->getTestStorage();
		\OC\Files\Filesystem::mount($storage1, array(), '/');

		$view = new \OC\Files\View('');

		$result = $view->resolvePath($pathToTest);
		$this->assertEquals($expected, $result[1]);

		$exists = $view->file_exists($pathToTest);
		$this->assertTrue($exists);

		$exists = $view->file_exists($result[1]);
		$this->assertTrue($exists);
	}

	function resolvePathTestProvider() {
		return array(
			array('foo.txt', 'foo.txt'),
			array('foo.txt', '/foo.txt'),
			array('folder', 'folder'),
			array('folder', '/folder'),
			array('folder', 'folder/'),
			array('folder', '/folder/'),
			array('folder/bar.txt', 'folder/bar.txt'),
			array('folder/bar.txt', '/folder/bar.txt'),
			array('', ''),
			array('', '/'),
		);
	}

	public function testUTF8Names() {
		$names = array('虚', '和知しゃ和で', 'regular ascii', 'sɨˈrɪlɪk', 'ѨѬ', 'أنا أحب القراءة كثيرا');

		$storage = new \OC\Files\Storage\Temporary(array());
		\OC\Files\Filesystem::mount($storage, array(), '/');

		$rootView = new \OC\Files\View('');
		foreach ($names as $name) {
			$rootView->file_put_contents('/' . $name, 'dummy content');
		}

		$list = $rootView->getDirectoryContent('/');

		$this->assertCount(count($names), $list);
		foreach ($list as $item) {
			$this->assertContains($item['name'], $names);
		}

		$cache = $storage->getCache();
		$scanner = $storage->getScanner();
		$scanner->scan('');

		$list = $cache->getFolderContents('');

		$this->assertCount(count($names), $list);
		foreach ($list as $item) {
			$this->assertContains($item['name'], $names);
		}
	}

	public function testLongPath() {

		$storage = new \OC\Files\Storage\Temporary(array());
		\OC\Files\Filesystem::mount($storage, array(), '/');

		$rootView = new \OC\Files\View('');

		$longPath = '';
		$ds = DIRECTORY_SEPARATOR;
		/*
		 * 4096 is the maximum path length in file_cache.path in *nix
		 * 1024 is the max path length in mac
		 * 228 is the max path length in windows
		 */
		$folderName = 'abcdefghijklmnopqrstuvwxyz012345678901234567890123456789';
		$tmpdirLength = strlen(\OC_Helper::tmpFolder());
		if (\OC_Util::runningOnWindows()) {
			$this->markTestSkipped('[Windows] ');
			$depth = ((260 - $tmpdirLength) / 57);
		} elseif (\OC_Util::runningOnMac()) {
			$depth = ((1024 - $tmpdirLength) / 57);
		} else {
			$depth = ((4000 - $tmpdirLength) / 57);
		}
		foreach (range(0, $depth - 1) as $i) {
			$longPath .= $ds . $folderName;
			$result = $rootView->mkdir($longPath);
			$this->assertTrue($result, "mkdir failed on $i - path length: " . strlen($longPath));

			$result = $rootView->file_put_contents($longPath . "{$ds}test.txt", 'lorem');
			$this->assertEquals(5, $result, "file_put_contents failed on $i");

			$this->assertTrue($rootView->file_exists($longPath));
			$this->assertTrue($rootView->file_exists($longPath . "{$ds}test.txt"));
		}

		$cache = $storage->getCache();
		$scanner = $storage->getScanner();
		$scanner->scan('');

		$longPath = $folderName;
		foreach (range(0, $depth - 1) as $i) {
			$cachedFolder = $cache->get($longPath);
			$this->assertTrue(is_array($cachedFolder), "No cache entry for folder at $i");
			$this->assertEquals($folderName, $cachedFolder['name'], "Wrong cache entry for folder at $i");

			$cachedFile = $cache->get($longPath . '/test.txt');
			$this->assertTrue(is_array($cachedFile), "No cache entry for file at $i");
			$this->assertEquals('test.txt', $cachedFile['name'], "Wrong cache entry for file at $i");

			$longPath .= $ds . $folderName;
		}
	}

	public function testTouchNotSupported() {
		$storage = new TemporaryNoTouch(array());
		$scanner = $storage->getScanner();
		\OC\Files\Filesystem::mount($storage, array(), '/test/');
		$past = time() - 100;
		$storage->file_put_contents('test', 'foobar');
		$scanner->scan('');
		$view = new \OC\Files\View('');
		$info = $view->getFileInfo('/test/test');

		$view->touch('/test/test', $past);
		$scanner->scanFile('test', \OC\Files\Cache\Scanner::REUSE_ETAG);

		$info2 = $view->getFileInfo('/test/test');
		$this->assertSame($info['etag'], $info2['etag']);
	}

	public function testWatcherEtagCrossStorage() {
		$storage1 = new Temporary(array());
		$storage2 = new Temporary(array());
		$scanner1 = $storage1->getScanner();
		$scanner2 = $storage2->getScanner();
		$storage1->mkdir('sub');
		\OC\Files\Filesystem::mount($storage1, array(), '/test/');
		\OC\Files\Filesystem::mount($storage2, array(), '/test/sub/storage');

		$past = time() - 100;
		$storage2->file_put_contents('test.txt', 'foobar');
		$scanner1->scan('');
		$scanner2->scan('');
		$view = new \OC\Files\View('');

		$storage2->getWatcher('')->setPolicy(Watcher::CHECK_ALWAYS);

		$oldFileInfo = $view->getFileInfo('/test/sub/storage/test.txt');
		$oldFolderInfo = $view->getFileInfo('/test');

		$storage2->getCache()->update($oldFileInfo->getId(), array(
			'storage_mtime' => $past
		));

		$view->getFileInfo('/test/sub/storage/test.txt');
		$newFolderInfo = $view->getFileInfo('/test');

		$this->assertNotEquals($newFolderInfo->getEtag(), $oldFolderInfo->getEtag());
	}

	/**
	 * @dataProvider absolutePathProvider
	 */
	public function testGetAbsolutePath($expectedPath, $relativePath) {
		$view = new \OC\Files\View('/files');
		$this->assertEquals($expectedPath, $view->getAbsolutePath($relativePath));
	}

	public function testPartFileInfo() {
		$storage = new Temporary(array());
		$scanner = $storage->getScanner();
		\OC\Files\Filesystem::mount($storage, array(), '/test/');
		$storage->file_put_contents('test.part', 'foobar');
		$scanner->scan('');
		$view = new \OC\Files\View('/test');
		$info = $view->getFileInfo('test.part');

		$this->assertInstanceOf('\OCP\Files\FileInfo', $info);
		$this->assertNull($info->getId());
		$this->assertEquals(6, $info->getSize());
	}

	function absolutePathProvider() {
		return array(
			array('/files/', ''),
			array('/files/0', '0'),
			array('/files/false', 'false'),
			array('/files/true', 'true'),
			array('/files/', '/'),
			array('/files/test', 'test'),
			array('/files/test', '/test'),
		);
	}

	/**
	 * @dataProvider relativePathProvider
	 */
	function testGetRelativePath($absolutePath, $expectedPath) {
		$view = new \OC\Files\View('/files');
		// simulate a external storage mount point which has a trailing slash
		$view->chroot('/files/');
		$this->assertEquals($expectedPath, $view->getRelativePath($absolutePath));
	}

	function relativePathProvider() {
		return array(
			array('/files/', '/'),
			array('/files', '/'),
			array('/files/0', '0'),
			array('/files/false', 'false'),
			array('/files/true', 'true'),
			array('/files/test', 'test'),
			array('/files/test/foo', 'test/foo'),
		);
	}

	public function testFileView() {
		$storage = new Temporary(array());
		$scanner = $storage->getScanner();
		$storage->file_put_contents('foo.txt', 'bar');
		\OC\Files\Filesystem::mount($storage, array(), '/test/');
		$scanner->scan('');
		$view = new \OC\Files\View('/test/foo.txt');

		$this->assertEquals('bar', $view->file_get_contents(''));
		$fh = tmpfile();
		fwrite($fh, 'foo');
		rewind($fh);
		$view->file_put_contents('', $fh);
		$this->assertEquals('foo', $view->file_get_contents(''));
	}

	/**
	 * @dataProvider tooLongPathDataProvider
	 * @expectedException \OCP\Files\InvalidPathException
	 */
	public function testTooLongPath($operation, $param0 = null) {

		$longPath = '';
		// 4000 is the maximum path length in file_cache.path
		$folderName = 'abcdefghijklmnopqrstuvwxyz012345678901234567890123456789';
		$depth = (4000 / 57);
		foreach (range(0, $depth + 1) as $i) {
			$longPath .= '/' . $folderName;
		}

		$storage = new \OC\Files\Storage\Temporary(array());
		$this->tempStorage = $storage; // for later hard cleanup
		\OC\Files\Filesystem::mount($storage, array(), '/');

		$rootView = new \OC\Files\View('');

		if ($param0 === '@0') {
			$param0 = $longPath;
		}

		if ($operation === 'hash') {
			$param0 = $longPath;
			$longPath = 'md5';
		}

		call_user_func(array($rootView, $operation), $longPath, $param0);
	}

	public function tooLongPathDataProvider() {
		return array(
			array('getAbsolutePath'),
			array('getRelativePath'),
			array('getMountPoint'),
			array('resolvePath'),
			array('getLocalFile'),
			array('getLocalFolder'),
			array('mkdir'),
			array('rmdir'),
			array('opendir'),
			array('is_dir'),
			array('is_file'),
			array('stat'),
			array('filetype'),
			array('filesize'),
			array('readfile'),
			array('isCreatable'),
			array('isReadable'),
			array('isUpdatable'),
			array('isDeletable'),
			array('isSharable'),
			array('file_exists'),
			array('filemtime'),
			array('touch'),
			array('file_get_contents'),
			array('unlink'),
			array('deleteAll'),
			array('toTmpFile'),
			array('getMimeType'),
			array('free_space'),
			array('getFileInfo'),
			array('getDirectoryContent'),
			array('getOwner'),
			array('getETag'),
			array('file_put_contents', 'ipsum'),
			array('rename', '@0'),
			array('copy', '@0'),
			array('fopen', 'r'),
			array('fromTmpFile', '@0'),
			array('hash'),
			array('hasUpdated', 0),
			array('putFileInfo', array()),
		);
	}

	public function testRenameCrossStoragePreserveMtime() {
		$storage1 = new Temporary(array());
		$storage2 = new Temporary(array());
		$scanner1 = $storage1->getScanner();
		$scanner2 = $storage2->getScanner();
		$storage1->mkdir('sub');
		$storage1->mkdir('foo');
		$storage1->file_put_contents('foo.txt', 'asd');
		$storage1->file_put_contents('foo/bar.txt', 'asd');
		\OC\Files\Filesystem::mount($storage1, array(), '/test/');
		\OC\Files\Filesystem::mount($storage2, array(), '/test/sub/storage');

		$view = new \OC\Files\View('');
		$time = time() - 200;
		$view->touch('/test/foo.txt', $time);
		$view->touch('/test/foo', $time);
		$view->touch('/test/foo/bar.txt', $time);

		$view->rename('/test/foo.txt', '/test/sub/storage/foo.txt');

		$this->assertEquals($time, $view->filemtime('/test/sub/storage/foo.txt'));

		$view->rename('/test/foo', '/test/sub/storage/foo');

		$this->assertEquals($time, $view->filemtime('/test/sub/storage/foo/bar.txt'));
	}

	public function testRenameFailDeleteTargetKeepSource() {
		$this->doTestCopyRenameFail('rename');
	}

	public function testCopyFailDeleteTargetKeepSource() {
		$this->doTestCopyRenameFail('copy');
	}

	private function doTestCopyRenameFail($operation) {
		$storage1 = new Temporary(array());
		/** @var \PHPUnit_Framework_MockObject_MockObject | \OC\Files\Storage\Temporary $storage2 */
		$storage2 = $this->getMockBuilder('\Test\Files\TemporaryNoCross')
			->setConstructorArgs([[]])
			->setMethods(['fopen'])
			->getMock();

		$storage2->expects($this->any())
			->method('fopen')
			->will($this->returnCallback(function ($path, $mode) use ($storage2) {
				$source = fopen($storage2->getSourcePath($path), $mode);
				return \OC\Files\Stream\Quota::wrap($source, 9);
			}));

		$storage1->mkdir('sub');
		$storage1->file_put_contents('foo.txt', '0123456789ABCDEFGH');
		$storage1->mkdir('dirtomove');
		$storage1->file_put_contents('dirtomove/indir1.txt', '0123456'); // fits
		$storage1->file_put_contents('dirtomove/indir2.txt', '0123456789ABCDEFGH'); // doesn't fit 
		$storage2->file_put_contents('existing.txt', '0123');
		$storage1->getScanner()->scan('');
		$storage2->getScanner()->scan('');
		\OC\Files\Filesystem::mount($storage1, array(), '/test/');
		\OC\Files\Filesystem::mount($storage2, array(), '/test/sub/storage');

		// move file
		$view = new \OC\Files\View('');
		$this->assertTrue($storage1->file_exists('foo.txt'));
		$this->assertFalse($storage2->file_exists('foo.txt'));
		$this->assertFalse($view->$operation('/test/foo.txt', '/test/sub/storage/foo.txt'));
		$this->assertFalse($storage2->file_exists('foo.txt'));
		$this->assertFalse($storage2->getCache()->get('foo.txt'));
		$this->assertTrue($storage1->file_exists('foo.txt'));

		// if target exists, it will be deleted too
		$this->assertFalse($view->$operation('/test/foo.txt', '/test/sub/storage/existing.txt'));
		$this->assertFalse($storage2->file_exists('existing.txt'));
		$this->assertFalse($storage2->getCache()->get('existing.txt'));
		$this->assertTrue($storage1->file_exists('foo.txt'));

		// move folder
		$this->assertFalse($view->$operation('/test/dirtomove/', '/test/sub/storage/dirtomove/'));
		// since the move failed, the full source tree is kept
		$this->assertTrue($storage1->file_exists('dirtomove/indir1.txt'));
		$this->assertTrue($storage1->file_exists('dirtomove/indir2.txt'));
		// second file not moved/copied
		$this->assertFalse($storage2->file_exists('dirtomove/indir2.txt'));
		$this->assertFalse($storage2->getCache()->get('dirtomove/indir2.txt'));

	}

	public function testDeleteFailKeepCache() {
		/**
		 * @var \PHPUnit_Framework_MockObject_MockObject | \OC\Files\Storage\Temporary $storage
		 */
		$storage = $this->getMockBuilder('\OC\Files\Storage\Temporary')
			->setConstructorArgs(array(array()))
			->setMethods(array('unlink'))
			->getMock();
		$storage->expects($this->once())
			->method('unlink')
			->will($this->returnValue(false));
		$scanner = $storage->getScanner();
		$cache = $storage->getCache();
		$storage->file_put_contents('foo.txt', 'asd');
		$scanner->scan('');
		\OC\Files\Filesystem::mount($storage, array(), '/test/');

		$view = new \OC\Files\View('/test');

		$this->assertFalse($view->unlink('foo.txt'));
		$this->assertTrue($cache->inCache('foo.txt'));
	}

	function directoryTraversalProvider() {
		return [
			['../test/'],
			['..\\test\\my/../folder'],
			['/test/my/../foo\\'],
		];
	}

	/**
	 * @dataProvider directoryTraversalProvider
	 * @expectedException \Exception
	 * @param string $root
	 */
	public function testConstructDirectoryTraversalException($root) {
		new \OC\Files\View($root);
	}

	public function testRenameOverWrite() {
		$storage = new Temporary(array());
		$scanner = $storage->getScanner();
		$storage->mkdir('sub');
		$storage->mkdir('foo');
		$storage->file_put_contents('foo.txt', 'asd');
		$storage->file_put_contents('foo/bar.txt', 'asd');
		$scanner->scan('');
		\OC\Files\Filesystem::mount($storage, array(), '/test/');
		$view = new \OC\Files\View('');
		$this->assertTrue($view->rename('/test/foo.txt', '/test/foo/bar.txt'));
	}

	public function testSetMountOptionsInStorage() {
		$mount = new MountPoint('\OC\Files\Storage\Temporary', '/asd/', [[]], \OC\Files\Filesystem::getLoader(), ['foo' => 'bar']);
		\OC\Files\Filesystem::getMountManager()->addMount($mount);
		/** @var \OC\Files\Storage\Common $storage */
		$storage = $mount->getStorage();
		$this->assertEquals($storage->getMountOption('foo'), 'bar');
	}

	public function testSetMountOptionsWatcherPolicy() {
		$mount = new MountPoint('\OC\Files\Storage\Temporary', '/asd/', [[]], \OC\Files\Filesystem::getLoader(), ['filesystem_check_changes' => Watcher::CHECK_NEVER]);
		\OC\Files\Filesystem::getMountManager()->addMount($mount);
		/** @var \OC\Files\Storage\Common $storage */
		$storage = $mount->getStorage();
		$watcher = $storage->getWatcher();
		$this->assertEquals(Watcher::CHECK_NEVER, $watcher->getPolicy());
	}

	public function testGetAbsolutePathOnNull() {
		$view = new \OC\Files\View();
		$this->assertNull($view->getAbsolutePath(null));
	}

	public function testGetRelativePathOnNull() {
		$view = new \OC\Files\View();
		$this->assertNull($view->getRelativePath(null));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testNullAsRoot() {
		new \OC\Files\View(null);
	}

	/**
	 * e.g. reading from a folder that's being renamed
	 *
	 * @expectedException \OCP\Lock\LockedException
	 *
	 * @dataProvider dataLockPaths
	 *
	 * @param string $rootPath
	 * @param string $pathPrefix
	 */
	public function testReadFromWriteLockedPath($rootPath, $pathPrefix) {
		$rootPath = str_replace('{folder}', 'files', $rootPath);
		$pathPrefix = str_replace('{folder}', 'files', $pathPrefix);

		$view = new \OC\Files\View($rootPath);
		$storage = new Temporary(array());
		\OC\Files\Filesystem::mount($storage, [], '/');
		$this->assertTrue($view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_EXCLUSIVE));
		$view->lockFile($pathPrefix . '/foo/bar/asd', ILockingProvider::LOCK_SHARED);
	}

	/**
	 * Reading from a files_encryption folder that's being renamed
	 *
	 * @dataProvider dataLockPaths
	 *
	 * @param string $rootPath
	 * @param string $pathPrefix
	 */
	public function testReadFromWriteUnlockablePath($rootPath, $pathPrefix) {
		$rootPath = str_replace('{folder}', 'files_encryption', $rootPath);
		$pathPrefix = str_replace('{folder}', 'files_encryption', $pathPrefix);

		$view = new \OC\Files\View($rootPath);
		$storage = new Temporary(array());
		\OC\Files\Filesystem::mount($storage, [], '/');
		$this->assertFalse($view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_EXCLUSIVE));
		$this->assertFalse($view->lockFile($pathPrefix . '/foo/bar/asd', ILockingProvider::LOCK_SHARED));
	}

	/**
	 * e.g. writing a file that's being downloaded
	 *
	 * @expectedException \OCP\Lock\LockedException
	 *
	 * @dataProvider dataLockPaths
	 *
	 * @param string $rootPath
	 * @param string $pathPrefix
	 */
	public function testWriteToReadLockedFile($rootPath, $pathPrefix) {
		$rootPath = str_replace('{folder}', 'files', $rootPath);
		$pathPrefix = str_replace('{folder}', 'files', $pathPrefix);

		$view = new \OC\Files\View($rootPath);
		$storage = new Temporary(array());
		\OC\Files\Filesystem::mount($storage, [], '/');
		$this->assertTrue($view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_SHARED));
		$view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_EXCLUSIVE);
	}

	/**
	 * Writing a file that's being downloaded
	 *
	 * @dataProvider dataLockPaths
	 *
	 * @param string $rootPath
	 * @param string $pathPrefix
	 */
	public function testWriteToReadUnlockableFile($rootPath, $pathPrefix) {
		$rootPath = str_replace('{folder}', 'files_encryption', $rootPath);
		$pathPrefix = str_replace('{folder}', 'files_encryption', $pathPrefix);

		$view = new \OC\Files\View($rootPath);
		$storage = new Temporary(array());
		\OC\Files\Filesystem::mount($storage, [], '/');
		$this->assertFalse($view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($view->lockFile($pathPrefix . '/foo/bar', ILockingProvider::LOCK_EXCLUSIVE));
	}

	public function dataLockPaths() {
		return [
			['/testuser/{folder}', ''],
			['/testuser', '/{folder}'],
			['', '/testuser/{folder}'],
		];
	}

	public function pathRelativeToFilesProvider() {
		return [
			['admin/files', ''],
			['admin/files/x', 'x'],
			['/admin/files', ''],
			['/admin/files/sub', 'sub'],
			['/admin/files/sub/', 'sub'],
			['/admin/files/sub/sub2', 'sub/sub2'],
			['//admin//files/sub//sub2', 'sub/sub2'],
		];
	}

	/**
	 * @dataProvider pathRelativeToFilesProvider
	 */
	public function testGetPathRelativeToFiles($path, $expectedPath) {
		$view = new \OC\Files\View();
		$this->assertEquals($expectedPath, $view->getPathRelativeToFiles($path));
	}

	public function pathRelativeToFilesProviderExceptionCases() {
		return [
			[''],
			['x'],
			['files'],
			['/files'],
			['/admin/files_versions/abc'],
		];
	}

	/**
	 * @dataProvider pathRelativeToFilesProviderExceptionCases
	 * @expectedException \InvalidArgumentException
	 */
	public function testGetPathRelativeToFilesWithInvalidArgument($path) {
		$view = new \OC\Files\View();
		$view->getPathRelativeToFiles($path);
	}

	public function testChangeLock() {
		$view = new \OC\Files\View('/testuser/files/');
		$storage = new Temporary(array());
		\OC\Files\Filesystem::mount($storage, [], '/');

		$view->lockFile('/test/sub', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_EXCLUSIVE));

		$view->changeLock('//test/sub', ILockingProvider::LOCK_EXCLUSIVE);
		$this->assertTrue($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_EXCLUSIVE));

		$view->changeLock('test/sub', ILockingProvider::LOCK_SHARED);
		$this->assertTrue($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_SHARED));

		$view->unlockFile('/test/sub/', ILockingProvider::LOCK_SHARED);

		$this->assertFalse($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_SHARED));
		$this->assertFalse($this->isFileLocked($view, '/test//sub', ILockingProvider::LOCK_EXCLUSIVE));

	}

	public function basicOperationProvider() {
		return [
			// --- write hook ----
			[
				'touch',
				['touch-create.txt'],
				'touch-create.txt',
				'create',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],
			[
				'fopen',
				['test-write.txt', 'w'],
				'test-write.txt',
				'write',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				null,
				// exclusive lock stays until fclose
				ILockingProvider::LOCK_EXCLUSIVE,
			],
			[
				'mkdir',
				['newdir'],
				'newdir',
				'write',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],
			[
				'file_put_contents',
				['file_put_contents.txt', 'blah'],
				'file_put_contents.txt',
				'write',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],
			[
				'file_put_contents',
				['file_put_contents.txt', fopen('php://temp', 'r+')],
				'file_put_contents.txt',
				'write',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],

			// ---- delete hook ----
			[
				'rmdir',
				['dir'],
				'dir',
				'delete',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],
			[
				'unlink',
				['test.txt'],
				'test.txt',
				'delete',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_EXCLUSIVE,
				ILockingProvider::LOCK_SHARED,
			],

			// ---- read hook (no post hooks) ----
			[
				'file_get_contents',
				['test.txt'],
				'test.txt',
				'read',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_SHARED,
				null,
			],
			[
				'fopen',
				['test.txt', 'r'],
				'test.txt',
				'read',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_SHARED,
				null,
			],
			[
				'opendir',
				['dir'],
				'dir',
				'read',
				ILockingProvider::LOCK_SHARED,
				ILockingProvider::LOCK_SHARED,
				null,
			],

			// ---- no lock, touch hook ---
			['touch', ['test.txt'], 'test.txt', 'touch', null, null, null],

			// ---- no hooks, no locks ---
			['is_dir', ['dir'], 'dir', null],
			['is_file', ['dir'], 'dir', null],
			['stat', ['dir'], 'dir', null],
			['filetype', ['dir'], 'dir', null],
			['filesize', ['dir'], 'dir', null],
			['isCreatable', ['dir'], 'dir', null],
			['isReadable', ['dir'], 'dir', null],
			['isUpdatable', ['dir'], 'dir', null],
			['isDeletable', ['dir'], 'dir', null],
			['isSharable', ['dir'], 'dir', null],
			['file_exists', ['dir'], 'dir', null],
			['filemtime', ['dir'], 'dir', null],
			// TODO: rename
			// TODO: exception case, properly unlocks
			// TODO: fclose case ?
		];
	}

	/**
	 * Test whether locks are set before and after the operation
	 *
	 * @dataProvider basicOperationProvider
	 *
	 * @param string $operation operation name on the view
	 * @param array $operationArgs arguments for the operation
	 * @param string $lockedPath path of the locked item to check
	 * @param string $hookType hook type
	 * @param int $expectedLockBefore expected lock during pre hooks
	 * @param int $expectedLockduring expected lock during operation
	 * @param int $expectedLockAfter expected lock during post hooks
	 * @param int $expectedStrayLock expected lock after returning, should
	 * be null (unlock) for most operations
	 * @param callable $operationFunc operation function
	 */
	public function testBasicOperationLock(
		$operation,
		$operationArgs,
		$lockedPath,
		$hookType,
		$expectedLockBefore = ILockingProvider::LOCK_SHARED,
		$expectedLockDuring = ILockingProvider::LOCK_SHARED,
		$expectedLockAfter = ILockingProvider::LOCK_SHARED,
		$expectedStrayLock = null,
		$operationFunc = null
	) {
		$storage = $this->getMockBuilder('\OC\Files\Storage\Temporary')
			->disableOriginalConstructor()
			->setMethods([$operation])
			->getMock();

		\OC\Files\Filesystem::mount($storage, array(), $this->user . '/');
		$storage->mkdir('files');
		$storage->mkdir('files/dir');
		$storage->file_put_contents('files/test.txt', 'blah');

		$view = new \OC\Files\View('/' . $this->user . '/files/');

		$this->assertFalse(
			$this->isFileLocked($view, $lockedPath, ILockingProvider::LOCK_SHARED),
			'File unlocked before operation'
		);
		$this->assertFalse(
			$this->isFileLocked($view, $lockedPath, ILockingProvider::LOCK_EXCLUSIVE),
			'File unlocked before operation'
		);

		$lockTypePre = null;
		$lockTypePost = null;

		$eventHandler = $this->getMockBuilder('\stdclass')
			->setMethods(['preCallback', 'postCallback'])
			->getMock();

		$eventHandler->expects($this->any())
			->method('preCallback')
			->will($this->returnCallback(
				function() use ($view, $lockedPath, &$lockTypePre){
					$lockTypePre = $this->getFileLockType($view, $lockedPath);
				}
			));
		$eventHandler->expects($this->any())
			->method('postCallback')
			->will($this->returnCallback(
				function() use ($view, $lockedPath, &$lockTypePost){
					$lockTypePost = $this->getFileLockType($view, $lockedPath);
				}
			));

		if ($hookType !== null) {
			\OCP\Util::connectHook(
				Filesystem::CLASSNAME,
				$hookType,
				$eventHandler,
				'preCallback'
			);
			\OCP\Util::connectHook(
				Filesystem::CLASSNAME,
				'post_' . $hookType,
				$eventHandler,
				'postCallback'
			);
		}

		$storage->expects($this->once())
			->method($operation)
			->will($this->returnCallback(
				function() use ($view, $lockedPath, &$lockTypeDuring, $operationFunc){
					$lockTypeDuring = $this->getFileLockType($view, $lockedPath);

					if ($operationFunc !== null) {
						return call_user_func($operationFunc);
					}

					return true;
				}
			));

		// do operation
		call_user_func_array(array($view, $operation), $operationArgs);

		// TODO: expected lock during operation (mock it)
		if ($hookType !== null) {
			$this->assertEquals($expectedLockBefore, $lockTypePre, 'File locked properly during pre-hook');
			$this->assertEquals($expectedLockAfter, $lockTypePost, 'File locked properly during post-hook');
			$this->assertEquals($expectedLockDuring, $lockTypeDuring, 'File locked properly during operation');
		} else {
			$this->assertNull($lockTypeDuring, 'File not locked during operation');
		}

		$this->assertEquals($expectedStrayLock, $this->getFileLockType($view, $lockedPath));
	}

	/**
	 * Returns the file lock type
	 *
	 * @param \OC\Files\View $view view
	 * @param string $path path
	 *
	 * @return int lock type or null if file was not locked
	 */
	private function getFileLockType(\OC\Files\View $view, $path) {
		if ($this->isFileLocked($view, $path, ILockingProvider::LOCK_EXCLUSIVE)) {
			return ILockingProvider::LOCK_EXCLUSIVE;
		} else if ($this->isFileLocked($view, $path, ILockingProvider::LOCK_SHARED)) {
			return ILockingProvider::LOCK_SHARED;
		}
		return null;
	}
}
