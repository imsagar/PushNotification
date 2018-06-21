<?php
//$pods_ip = shell_exec('kubectl describe service push-notification-service | grep ":2121" | awk -F " " \'{print $2}\'');
$pods_ip = ['10.32.0.10','10.32.0.13','10.32.0.14'];

// Specify who to push, empty to push to all online users
$to_uid = "";
// The URL of the URL to use, using its own server address
foreach($pods_ip as $ip) {
	$push_api_url = "http://".$ip.":2121/";
	$post_data = array(
	   "type" => "publish",
	   "content" => "This is the push test data",
	   "to" => $to_uid, 
	);
	$ch = curl_init ();
	Curl_setopt ( $ch, CURLOPT_URL, $push_api_url );
	Curl_setopt ( $ch, CURLOPT_POST, 1 );
	Curl_setopt ( $ch, CURLOPT_HEADER, 0 );
	Curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
	Curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post_data );
	Curl_setopt ($ch, CURLOPT_HTTPHEADER, array("Expect:"));
	$return = curl_exec ( $ch );
	Curl_close ( $ch );
	Var_export($return);
}