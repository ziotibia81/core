<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Node;

use OC\Files\Cache\Cache;
use OC\Files\Node\Node;
use OC\Files\NotFoundException;
use OC\Files\NotPermittedException;

class Folder extends \PHPUnit_Framework_TestCase {
	private $user;

	public function setUp() {
		$this->user = new \OC\User\User('', new \OC_User_Dummy);
	}

	public function testDelete() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$root->expects($this->exactly(2))
			->method('emit')
			->will($this->returnValue(true));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('rmdir')
			->with($this->equalTo('foo'));

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('remove')
			->with('foo');

		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_ALL));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
	}

	public function testDeleteHooks() {
		$test = $this;
		$hooksRun = 0;
		/**
		 * @param \OC\Files\Node\File $node
		 */
		$preListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf('\OC\Files\Node\Folder', $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$hooksRun++;
		};

		/**
		 * @param \OC\Files\Node\File $node
		 */
		$postListener = function ($node) use (&$test, &$hooksRun) {
			$test->assertInstanceOf('\OC\Files\Node\NonExistingFolder', $node);
			$test->assertEquals('foo', $node->getInternalPath());
			$test->assertEquals('/bar/foo', $node->getPath());
			$test->assertEquals(1, $node->getId());
			$hooksRun++;
		};

		/**
		 * @var \OC\Files\Mount\Manager $manager
		 */
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		/**
		 * @var \OC\User\Manager $userManager
		 */
		$userManager = $this->getMock('\OC\User\Manager');
		$root = new \OC\Files\Node\Root($manager, $this->user, $userManager);
		$root->listen('\OC\Files', 'preDelete', $preListener);
		$root->listen('\OC\Files', 'postDelete', $postListener);

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->once())
			->method('rmdir')
			->with($this->equalTo('foo'));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_ALL));

		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();
		$cache->expects($this->once())
			->method('remove')
			->with('foo');

		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
		$this->assertEquals(2, $hooksRun);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testDeleteNotPermitted() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$storage->expects($this->never())
			->method('rmdir');

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('get')
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->delete();
	}

	public function testGetDirectoryContent() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		/**
		 * @var \OC\Files\Storage\Storage | \PHPUnit_Framework_MockObject_MockObject $storage
		 */
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$watcher = $this->getMock('\OC\Files\Cache\Watcher', array(), array($storage));
		$watcher->expects($this->once())
			->method('checkUpdate')
			->with('foo');

		$cache = $this->getMock('\OC\Files\Cache\Cache', array(), array(''));
		$cache->expects($this->any())
			->method('getStatus')
			->with('foo')
			->will($this->returnValue(Cache::COMPLETE));

		$cache->expects($this->once())
			->method('getFolderContents')
			->with('foo')
			->will($this->returnValue(array(
				array('fileid' => 2, 'path' => '/bar/foo/asd', 'name' => 'asd', 'size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain'),
				array('fileid' => 3, 'path' => '/bar/foo/qwerty', 'name' => 'qwerty', 'size' => 200, 'mtime' => 55, 'mimetype' => 'httpd/unix-directory')
			)));

		$permissionsCache = $this->getMock('\OC\Files\Cache\Permissions', array(), array('/'));
		$permissionsCache->expects($this->once())
			->method('getDirectoryPermissions')
			->with(1)
			->will($this->returnValue(array(2 => \OCP\PERMISSION_ALL)));
		$permissionsCache->expects($this->once())
			->method('get')
			->with(3)
			->will($this->returnValue(\OCP\PERMISSION_READ));

		$root->expects($this->once())
			->method('getMountsIn')
			->with('/bar/foo')
			->will($this->returnValue(array()));

		$storage->expects($this->any())
			->method('getPermissionsCache')
			->will($this->returnValue($permissionsCache));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));
		$storage->expects($this->any())
			->method('getWatcher')
			->will($this->returnValue($watcher));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$children = $node->getDirectoryListing();
		$this->assertEquals(2, count($children));
		$this->assertInstanceOf('\OC\Files\Node\File', $children[0]);
		$this->assertInstanceOf('\OC\Files\Node\Folder', $children[1]);
		$this->assertEquals('asd', $children[0]->getName());
		$this->assertEquals('qwerty', $children[1]->getName());
		$this->assertEquals(\OCP\PERMISSION_ALL, $children[0]->getPermissions());
		$this->assertEquals(\OCP\PERMISSION_READ, $children[1]->getPermissions());
	}

	public function testGet() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$root->expects($this->once())
			->method('get')
			->with('/bar/foo/asd');

		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$node->get('asd');
	}

	public function testNodeExists() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$child = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo/asd', array('fileid' => 1));

		$root->expects($this->once())
			->method('get')
			->with('/bar/foo/asd')
			->will($this->returnValue($child));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$this->assertTrue($node->nodeExists('asd'));
	}

	public function testNodeExistsNotExists() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));

		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$root->expects($this->once())
			->method('get')
			->with('/bar/foo/asd')
			->will($this->throwException(new NotFoundException()));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$this->assertFalse($node->nodeExists('asd'));
	}

	public function testNewFolder() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage, '/bar/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/bar/foo/asd')
			->will($this->returnValue('foo/asd'));

		$storage->expects($this->once())
			->method('mkdir')
			->with('foo/asd');

		$child = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo/asd', array('fileid' => 1));

		$root->expects($this->once())
			->method('getMount')
			->with('/bar/foo/asd')
			->will($this->returnValue($mount));

		$scanner = $this->getMockBuilder('\OC\Files\Cache\Scanner')
			->disableOriginalConstructor()
			->getMock();
		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();

		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'permissions' => \OCP\PERMISSION_CREATE));

		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'bar', '/bar', array('fileid' => 2, 'permissions' => \OCP\PERMISSION_CREATE));
		$rootNode = new \OC\Files\Node\Folder($root, $storage, '', '/', array('fileid' => 3, 'permissions' => \OCP\PERMISSION_CREATE));

		$root->expects($this->any())
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar/foo/asd', $child),
				array('/bar/foo', $node),
				array('/bar', $parentNode),
				array('/', $rootNode)
			)));

		$result = $node->newFolder('asd');
		$this->assertEquals($child, $result);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testNewFolderNotPermitted() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'permissions' => 0));
		$node->newFolder('asd');
	}

	public function testNewFile() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$mount = $this->getMock('\OC\Files\Mount\Mount', array(), array($storage, '/bar/'));
		$mount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($storage));
		$mount->expects($this->once())
			->method('getInternalPath')
			->with('/bar/foo/asd')
			->will($this->returnValue('foo/asd'));

		$storage->expects($this->once())
			->method('touch')
			->with('foo/asd');

		$scanner = $this->getMockBuilder('\OC\Files\Cache\Scanner')
			->disableOriginalConstructor()
			->getMock();
		$cache = $this->getMockBuilder('\OC\Files\Cache\Cache')
			->disableOriginalConstructor()
			->getMock();

		$storage->expects($this->any())
			->method('getScanner')
			->will($this->returnValue($scanner));
		$storage->expects($this->any())
			->method('getCache')
			->will($this->returnValue($cache));

		$child = new \OC\Files\Node\File($root, $storage, 'foo', '/bar/foo/asd', array('fileid' => 1));

		$root->expects($this->once())
			->method('getMount')
			->with('/bar/foo/asd')
			->will($this->returnValue($mount));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'permissions' => \OCP\PERMISSION_CREATE));

		$parentNode = new \OC\Files\Node\Folder($root, $storage, 'bar', '/bar', array('fileid' => 2, 'permissions' => \OCP\PERMISSION_CREATE));
		$rootNode = new \OC\Files\Node\Folder($root, $storage, '', '/', array('fileid' => 3, 'permissions' => \OCP\PERMISSION_CREATE));

		$root->expects($this->any())
			->method('get')
			->will($this->returnValueMap(array(
				array('/bar/foo/asd', $child),
				array('/bar/foo', $node),
				array('/bar', $parentNode),
				array('/', $rootNode)
			)));

		$result = $node->newFile('asd');
		$this->assertEquals($child, $result);
	}

	/**
	 * @expectedException \OC\Files\NotPermittedException
	 */
	public function testNewFileNotPermitted() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1, 'permissions' => 0));
		$node->newFile('asd');
	}

	public function testGetFreeSpace() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');

		$storage->expects($this->once())
			->method('free_space')
			->with('foo')
			->will($this->returnValue(100));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$this->assertEquals(100, $node->getFreeSpace());
	}

	public function testSearch() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$cache = $this->getMock('\OC\Files\Cache\Cache', array(), array(''));

		$storage->expects($this->once())
			->method('getCache')
			->will($this->returnValue($cache));

		$cache->expects($this->once())
			->method('search')
			->with('%qw%')
			->will($this->returnValue(array(
				array('fileid' => 3, 'path' => 'foo/qwerty', 'name' => 'qwerty', 'size' => 200, 'mtime' => 55, 'mimetype' => 'text/plain')
			)));

		$root->expects($this->once())
			->method('getMountsIn')
			->with('/bar/foo')
			->will($this->returnValue(array()));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$result = $node->search('qw');
		$this->assertEquals(1, count($result));
		$this->assertEquals('/bar/foo/qwerty', $result[0]->getPath());
		$this->assertEquals(3, $result[0]->getId());
	}

	public function testSearchSubStorages() {
		$manager = $this->getMock('\OC\Files\Mount\Manager');
		$root = $this->getMock('\OC\Files\Node\Root', array(), array($manager, $this->user));
		$root->expects($this->any())
			->method('getUser')
			->will($this->returnValue($this->user));
		$storage = $this->getMock('\OC\Files\Storage\Storage');
		$cache = $this->getMock('\OC\Files\Cache\Cache', array(), array(''));
		$subCache = $this->getMock('\OC\Files\Cache\Cache', array(), array(''));
		$subStorage = $this->getMock('\OC\Files\Storage\Storage');
		$subMount = $this->getMock('\OC\Files\Mount\Mount', array(), array(null, ''));

		$subMount->expects($this->once())
			->method('getStorage')
			->will($this->returnValue($subStorage));

		$subMount->expects($this->once())
			->method('getMountPoint')
			->will($this->returnValue('/bar/foo/bar/'));

		$storage->expects($this->once())
			->method('getCache')
			->will($this->returnValue($cache));

		$subStorage->expects($this->once())
			->method('getCache')
			->will($this->returnValue($subCache));

		$cache->expects($this->once())
			->method('search')
			->with('%qw%')
			->will($this->returnValue(array(
				array('fileid' => 3, 'path' => 'foo/qwerty', 'name' => 'qwerty', 'size' => 200, 'mtime' => 55, 'mimetype' => 'text/plain')
			)));

		$subCache->expects($this->once())
			->method('search')
			->with('%qw%')
			->will($this->returnValue(array(
				array('fileid' => 4, 'path' => 'asd/qweasd', 'name' => 'qweasd', 'size' => 200, 'mtime' => 55, 'mimetype' => 'text/plain')
			)));

		$root->expects($this->once())
			->method('getMountsIn')
			->with('/bar/foo')
			->will($this->returnValue(array($subMount)));

		$node = new \OC\Files\Node\Folder($root, $storage, 'foo', '/bar/foo', array('fileid' => 1));
		$result = $node->search('qw');
		$this->assertEquals(2, count($result));
		$this->assertEquals(3, $result[0]->getId());
		$this->assertEquals(4, $result[1]->getId());
	}

	public function testIsSubNode() {
		$file = new Node(null, null, 'foo/bar', '/foo/bar', array());
		$folder = new \OC\Files\Node\Folder(null, null, 'foo', '/foo', array());
		$this->assertTrue($folder->isSubNode($file));
		$this->assertFalse($folder->isSubNode($folder));

		$file = new Node(null, null, 'foobar', '/foobar', array());
		$this->assertFalse($folder->isSubNode($file));
	}
}
