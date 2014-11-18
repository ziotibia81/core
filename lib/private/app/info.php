<?php

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\App;

use OCP\App\IInfo;

class Info implements IInfo {
	private $appId;

	private $appPath;

	private $version;

	private $installedVersion;

	private $remote = array();

	private $public = array();

	private $types = array();

	private $description;

	private $documentation = array();

	private $name;

	private $licence;

	private $author;

	public function __construct($appId, $appPath, $version, $installedVersion) {
		$this->appId = $appId;
		$this->appPath = $appPath;
		$this->version = $version;
		$this->installedVersion = $installedVersion;

		$content = file_get_contents($appPath . '/appinfo/info.xml');
		$xml = new \SimpleXMLElement($content);
		foreach ($xml->children() as $child) {
			/**
			 * @var $child \SimpleXMLElement
			 */
			switch ($child->getName()) {
				case 'remote':
					foreach ($child->children() as $remote) {
						/**
						 * @var $remote \SimpleXMLElement
						 */
						$this->remote[$remote->getName()] = (string)$remote;
					}
					break;
				case 'public':
					foreach ($child->children() as $public) {
						/**
						 * @var $public \SimpleXMLElement
						 */
						$this->public[$public->getName()] = (string)$public;
					}
					break;
				case 'types':
					$data['types'] = array();
					foreach ($child->children() as $type) {
						/**
						 * @var $type \SimpleXMLElement
						 */
						$this->types[] = $type->getName();
					}
					break;
				case 'description':
					$xml = (string)$child->asXML();
					$this->description = substr($xml, 13, -14); //script <description> tags
					break;
				case 'documentation':
					foreach ($child as $subChild) {
						$url = (string)$subChild;

						// If it is not an absolute URL we assume it is a key
						// i.e. admin-ldap will get converted to go.php?to=admin-ldap
						if (!\OC::$server->getHTTPHelper()->isHTTPURL($url)) {
							$url = \OC_Helper::linkToDocs($url);
						}

						$this->documentation[$subChild->getName()] = $url;
					}
					break;
				case 'name':
					$this->name = (string)$child;
					break;
				case 'licence':
					$this->licence = (string)$child;
					break;
				case 'author':
					$this->author = (string)$child;
					break;
			}
		}
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->appId;
	}

	/**
	 * @return string
	 */
	public function getAppPath() {
		return $this->appPath;
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public function isType($type) {
		return array_search($type, $this->types) !== false;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @return string[]
	 */
	public function getDocumentation() {
		return $this->documentation;
	}

	/**
	 * @return string
	 */
	public function getLicence() {
		return $this->licence;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getAuthor() {
		return $this->author;
	}

	/**
	 * @return string[]
	 */
	public function getPublic() {
		return $this->public;
	}

	/**
	 * @return string[]
	 */
	public function getRemote() {
		return $this->remote;
	}

	/**
	 * @return string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * @return string
	 */
	public function getInstalledVersion() {
		return $this->installedVersion;
	}

	/**
	 * Check if the app needs to be updated
	 *
	 * @return bool
	 */
	public function needsUpdate() {
		return version_compare($this->getVersion(), $this->getInstalledVersion(), '>');
	}
}
