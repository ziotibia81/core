OC.MimeType = {

	mimeTypeAlias: {},

	mimeTypeIcons: {},
	
	init: function() {
		OC.MimeType.mimeTypeAlias = {
			"application/octet-stream" : "file", // use file icon as fallback

			"application/illustrator" : "image/vector",
			"application/postscript" : "image/vector",
			"image/svg+xml" : "image/vector",

			"application/coreldraw" : "image",
			"application/x-gimp" : "image",
			"application/x-photoshop" : "image",
			"application/x-dcraw" : "image",

			"application/font-sfnt" : "font",
			"application/x-font" : "font",
			"application/font-woff" : "font",
			"application/vnd.ms-fontobject" : "font",

			"application/json" : "text/code",
			"application/x-perl" : "text/code",
			"application/x-php" : "text/code",
			"text/x-shellscript" : "text/code",
			"application/yaml" : "text/code",
			"application/xml" : "text/html",
			"text/css" : "text/code",
			"application/x-tex" : "text",

			"application/x-compressed" : "package/x-generic",
			"application/x-7z-compressed" : "package/x-generic",
			"application/x-deb" : "package/x-generic",
			"application/x-gzip" : "package/x-generic",
			"application/x-rar-compressed" : "package/x-generic",
			"application/x-tar" : "package/x-generic",
			"application/vnd.android.package-archive" : "package/x-generic",
			"application/zip" : "package/x-generic",

			"application/msword" : "x-office/document",
			"application/vnd.openxmlformats-officedocument.wordprocessingml.document" : "x-office/document",
			"application/vnd.openxmlformats-officedocument.wordprocessingml.template" : "x-office/document",
			"application/vnd.ms-word.document.macroEnabled.12" : "x-office/document",
			"application/vnd.ms-word.template.macroEnabled.12" : "x-office/document",
			"application/vnd.oasis.opendocument.text" : "x-office/document",
			"application/vnd.oasis.opendocument.text-template" : "x-office/document",
			"application/vnd.oasis.opendocument.text-web" : "x-office/document",
			"application/vnd.oasis.opendocument.text-master" : "x-office/document",

			"application/mspowerpoint" : "x-office/presentation",
			"application/vnd.ms-powerpoint" : "x-office/presentation",
			"application/vnd.openxmlformats-officedocument.presentationml.presentation" : "x-office/presentation",
			"application/vnd.openxmlformats-officedocument.presentationml.template" : "x-office/presentation",
			"application/vnd.openxmlformats-officedocument.presentationml.slideshow" : "x-office/presentation",
			"application/vnd.ms-powerpoint.addin.macroEnabled.12" : "x-office/presentation",
			"application/vnd.ms-powerpoint.presentation.macroEnabled.12" : "x-office/presentation",
			"application/vnd.ms-powerpoint.template.macroEnabled.12" : "x-office/presentation",
			"application/vnd.ms-powerpoint.slideshow.macroEnabled.12" : "x-office/presentation",
			"application/vnd.oasis.opendocument.presentation" : "x-office/presentation",
			"application/vnd.oasis.opendocument.presentation-template" : "x-office/presentation",

			"application/msexcel" : "x-office/spreadsheet",
			"application/vnd.ms-excel" : "x-office/spreadsheet",
			"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" : "x-office/spreadsheet",
			"application/vnd.openxmlformats-officedocument.spreadsheetml.template" : "x-office/spreadsheet",
			"application/vnd.ms-excel.sheet.macroEnabled.12" : "x-office/spreadsheet",
			"application/vnd.ms-excel.template.macroEnabled.12" : "x-office/spreadsheet",
			"application/vnd.ms-excel.addin.macroEnabled.12" : "x-office/spreadsheet",
			"application/vnd.ms-excel.sheet.binary.macroEnabled.12" : "x-office/spreadsheet",
			"application/vnd.oasis.opendocument.spreadsheet" : "x-office/spreadsheet",
			"application/vnd.oasis.opendocument.spreadsheet-template" : "x-office/spreadsheet",
			"text/csv" : "x-office/spreadsheet",

			"application/msaccess" : "database"
		};
	},

	mimetypeIcon: function(mimeType) {
		if (mimeType === undefined) {
			return undefined;
		}

		if (mimeType in OC.MimeType.mimeTypeAlias) {
			mimeType = OC.MimeType.mimeTypeAlias[mimeType];
		}
		if (mimeType in OC.MimeType.mimeTypeIcons) {
			return OC.MimeType.mimeTypeIcons[mimeType];
		}

		if (mimeType == 'dir') {
			return OC.webroot + '/core/img/filetypes/folder.png';
		}
		if (mimeType == 'dir-shared') {
			return OC.webroot + '/core/img/filetypes/folder-shared.png';
		}
		if (mimeType == 'dir-external') {
			return OC.webroot + '/core/img/filetypes/folder-external.png';
		}

		function checkExists(url) {
			var ok;
			$.ajax({
				url:url,
				async: false,
				type:'HEAD',
				error: function() {
					ok = false;
				},
				success: function() {
					ok = true;
				}
			});
			return ok;
		}


		var icon = mimeType.replace(new RegExp('/', 'g'), '-');
		//icon = icon.replace(new RegExp('\\', 'g'), '-');

		if (checkExists(OC.webroot + '/core/img/filetypes/' + icon + '.png')) {
			OC.MimeType.mimeTypeIcons[mimeType] = OC.webroot + '/core/img/filetypes/' + icon + '.png';
			return OC.MimeType.mimeTypeIcons[mimeType];
		}

		mimePart = icon.split('-')[0];
		if (checkExists(OC.webroot + '/core/img/filetypes/' + mimePart + '.png')) {
			OC.MimeType.mimeTypeIcons[mimeType] = OC.webroot + '/core/img/filetypes/' + mimePart + '.png';
			return OC.MimeType.mimeTypeIcons[mimeType];
		} else {
			OC.MimeType.mimeTypeIcons[mimeType] = OC.webroot + '/core/img/filetypes/file.png';
			return OC.MimeType.mimeTypeIcons[mimeType];
		}
	}

};

$(document).ready(OC.MimeType.init);

