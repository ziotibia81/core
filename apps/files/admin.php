<?php

/**
* ownCloud - ajax frontend
*
* @author Robin Appelman
* @copyright 2010 Robin Appelman icewind1991@gmail.com
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

OCP\User::checkAdminUser();
$l = \OC_L10N::get('files');

$htaccessWorking = getenv('htaccessWorking') == 'true';
$uploadChangable = $htaccessWorking && is_writable(OC::$SERVERROOT.'/.htaccess');
$maxFileSizeUnits = [
	'B' => $l->t('B'),
	'kB' => $l->t('kB'),
	'MB' => $l->t('MB'),
	'GB' => $l->t('GB'),
	'TB' => $l->t('TB'),
];

$phpIni = new \bantu\IniGetWrapper\IniGetWrapper();
$maxUploadFileSize = min($phpIni->getBytes('upload_max_filesize'), $phpIni->getBytes('post_max_size'));
if ($uploadChangable && isset($_POST['maxUploadSizeValue']) && OC_Util::isCallRegistered()) {
	$value = ((int) $_POST['maxUploadSizeValue']) . ' ';
	$value .= (isset($maxFileSizeUnits[(string) $_POST['maxUploadSizeUnit']])) ? $maxFileSizeUnits[(string) $_POST['maxUploadSizeUnit']] : 'B';
	if (($setMaxSize = OC_Files::setUploadLimit(OCP\Util::computerFileSize($value))) !== false) {
		$maxUploadFileSize = $setMaxSize;
	}
}
list($maxUploadFileSizeValue, $maxUploadFileSizeUnit) = explode(' ', \OCP\Util::humanFileSize($maxUploadFileSize));

$tmpl = new OCP\Template( 'files', 'admin' );
$tmpl->assign('uploadChangable', $uploadChangable);
$tmpl->assign('uploadMaxFileSizeValue', $maxUploadFileSizeValue);
$tmpl->assign('uploadMaxFileSizeUnit', $maxUploadFileSizeUnit);
$tmpl->assign('maxFileSizeUnits', $maxFileSizeUnits);
// max possible makes only sense on a 32 bit system
$tmpl->assign('displayMaxPossibleUploadSize', PHP_INT_SIZE===4);
$tmpl->assign('maxPossibleUploadSize', OCP\Util::humanFileSize(PHP_INT_MAX));
return $tmpl->fetchPage();
