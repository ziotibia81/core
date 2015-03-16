<?php

$request = $_POST['request'];

if ($request == "domain") {
	$username = $_POST['username'];

	$mail = explode("@",$username);
	if ($mail[1]) {
		$mail = explode(".", $mail[1]);
		array_pop($mail);
		$mail = implode(".", $mail);
	} else {
		http_response_code(422);
		echo json_encode(array('error' => array('message' => 'Username not supported.')));
	}
	$base_domain = "achernar.uberspace.de/oc8ee";
	$domain = "https://" . $mail . "." . $base_domain;
	
	// $domain = "https://owncloud.achernar.uberspace.de/oc8ee";

	echo json_encode(array('domain' => $domain));
	exit();
	
} else {
	http_response_code(422);
	echo json_encode(array('error' => array('message' => 'Request not supported.')));
	exit();
}

