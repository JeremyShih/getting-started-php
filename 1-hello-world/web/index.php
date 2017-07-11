<?php

/*
 * Copyright 2015 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// [START index_php]
// require_once __DIR__ . '/../vendor/autoload.php';

// $app = new Silex\Application();

// $app->get('/', function () {
//     return 'Hello World';
// });

// $app->get('/goodbye', function () {
//     return 'Goodbye World';
// });

// @codeCoverageIgnoreStart
// if (PHP_SAPI != 'cli') {
//     $app->run();
// }
// @codeCoverageIgnoreEnd

// return $app;
// [END index_php]
#!/usr/bin/php

// define('LISTEN', true);
// define('PID_FILE', './cboxbot.pid');

// Edit these lines for your Cbox and bot user. 
$box = array('srv' => 4, 'id' => 4337255, 'tag' => 'mtukb5');
$bot = array('name' => '測試機器人二號', 'token' => 'ztLPbrU4w70ODJnH', 'url' => '');
$msg = 'hello world!';


$callmap = array(
	'/(?=.*((密碼)+))(?=.*((如|何|什|怎|麼|\?)+))/' => 'password',
	'/(?=.*((註冊)+))(?=.*((如|何|什|怎|麼|\?)+))/' => 'register',
	'/\bhello bot\b/iu' => 'bot_greet',
	'/\安安/i' => 'bot_greet2',
	'/\btime\?/i' => 'bot_time',
	'/\bweather in ([a-zA-Z-0-9 ,]+)/iu' => 'bot_weather',
	'/\掰/i' => 'bot_bye',
	'/\@測試機器人二號/i' => 'bot_reply'
);

// Basic reply
function bot_time () {
	return $msg['name']." ".date("Y M d H:i:s");
}

// An example incorporating message data. 
function bot_greet ($msg) {
	return "Hello ".$msg['name'];
}
function bot_greet2 ($msg) {
	return $msg['name']."安安";
}
function bot_reply ($msg) {
	return $msg['name']."?";
}
function bot_bye ($msg) {
	return "掰掰 ".$msg['name'];
}
function password ($msg) {
	return "密碼：僅限學術使用，分享於JA";
}
function register ($msg) {
	return "想註冊或有要求權限，請到JA的FB粉專私訊自己的FB名稱與gmail喔\n已私訊仍無法觀看請耐心等待~";
}
function check ($msg) {
	return "想註冊或有要求權限，請到JA的FB粉專私訊自己的FB名稱與gmail喔";
}
function bot_wave ($msg) {
	return $msg['name']."~~~";
}

// An example calling an external API
function bot_weather ($msg, $matches) {
	$place = $matches[1];

	if (!$place) {
		return;
	}

	$query = 'select * from weather.forecast where woeid in (select woeid from geo.places(1) where text="'.addslashes($place).'")';

	$url = 'https://query.yahooapis.com/v1/public/yql?q='.urlencode($query).'&format=json&u=c&env=store%3A%2F%2Fdatatables.org%2Falltableswithkeys';

	$out = file_get_contents($url);

	if (!$out) {
		return;
	}

	$wobj = json_decode($out);

	if (!$wobj->query->results) {
		return;
	}

	$cond = $wobj->query->results->channel->item->condition;
	$desc = $wobj->query->results->channel->description;

	return "[b]".$cond->text." ".$cond->temp."F [/b] (".$desc." at ".$cond->date.")";
}

date_default_timezone_set("Asia/Taipei");
// Do not edit past this point. 

set_time_limit(0); // 設定執行時間無上限
echo "set time limit\n";

$id = cbox_post($msg, $bot, $box, $error);

// if (!$id) {
// 	echo $error;
// }
// else {
// 	echo "Posted ID $id\n";
// }

// if (!LISTEN) {
// 	exit;
// }

// Synchronization.
// PID file is not removed on exit, but it is unlocked. A locked file indicates a running process.
// $fp = fopen(PID_FILE, 'a+');

// if (!flock($fp, LOCK_EX | LOCK_NB)) {
// 	echo "Could not lock PID file. Process already running?\n"; 
// 	exit;
// }

// ftruncate($fp, 0);
// fwrite($fp, posix_getpid()."\n");
// fflush($fp);

do {
	echo "do ".date('H:i:s')."\n";
	$msgs = cbox_get_msgs($id, $bot, $box);

	if (!$msgs || !is_array($msgs)) {
		sleep(3);
		continue;
	}

	$id = (int)$msgs[0]['id'];
	echo "msgs.Count: ".count($msgs)."\n";
	for ($i = 0; $i < count($msgs); $i++) {

		// echo $msgs[$i]['name']." --- ".$bot['name']."\n";
		if ($msgs[$i]['name'] == $bot['name']) {
			continue;	// Ignore bot's own messages.
		}
		$msgtext = $msgs[$i]['message'];
		echo $msgs[$i]['name']." at ".$msgs[$i]['date']." ".$msgs[$i]['time'].": $msgtext\n";
		if (( strtotime(date('H:i:s')) - $msgs[$i]['time'] ) / 60 > 1) {
			// echo ( strtotime(date('H:i:s')) - $msgs[$i]['time'] )."\n";
			continue;
		}
		
		foreach ($callmap as $expr => $func) {
			$matches = array();
			echo "expression: $expr\n";
			if (preg_match($expr, $msgtext, $matches)) {
			
				$reply = call_user_func($func, $msgs[$i], $matches);
				echo "reply: $reply\n";
				if ($reply) {
					cbox_post($reply, $bot, $box, $error);
					break;
				}
			}
		}
	}
	echo "------------------------------------------------\n";
	sleep(3);
} while (true);


function cbox_get_msgs ($id, $user, $box, &$error = '') {
	$srv = $box['srv'];
	$boxid = $box['id'];
	$boxtag = $box['tag'];
	
	$host = "www$srv.cbox.ws";
	$path = "/box/?boxid=$boxid&boxtag=$boxtag&sec=archive";
	$port = 80;
	$timeout = 5;

	$get = array(
		'i' => (int)$id,
		'k' => $user['token'],
		'fwd' => 1,
		'aj' => 1
	);	

	$req = '';
	$res = '';
	
	foreach ($get as $k => $v) {
		$path .= "&$k=".urlencode($v);
	}
	
	$hdr  = "GET $path HTTP/1.1\r\n";
	$hdr .= "Host: $host\r\n\r\n";
	// echo "hdr: $hdr";
	$fp = fsockopen ($host, $port, $errno, $errstr, $timeout);

	if (!$fp) {
		$error = "Could not open socket: $errno - $errstr\n";
		return;
	}
	echo "cbox_get_msgs fputs ".date('H:i:s')."\n";
	fputs ($fp, $hdr);
	echo "cbox_get_msgs fgets ".date('H:i:s')."\n";
	$t = 1;
	while (!feof($fp) && $t < 2) {
		echo "==============================$t==============================\n"; $t += 1;
		$tes = fread ($fp, 8192);
		// echo "$tes\n";
		$res .= $tes;
	}
	echo "\ncbox_get_msgs fclose ".date('H:i:s')."\n";
	fclose ($fp);
	
	if (!$res || !strpos($res, "200 OK")) {
		$error = "Bad response:\r\n $res";
		return;
	}

	$matches = array();

	preg_match_all('/\n([^\t\n]*)\t([^\t]*)\t([^\t]*)\t([^\t]*)\t'.'([^\t]*)\t([^\t]*)\t([^\t]*)\t([^\t]*)\t/', $res, $matches);

	$msgs = array();

	$map = array('id', 'time', 'date', 'name', 'group', 'url', 'message');

	for ($m = 0; $m < count($map); $m++) {
		for ($i = 0; $i < count($matches[$m+1]); $i++) {
			$msgs[$i][$map[$m]] = $matches[$m+1][$i];
		}
	}

	echo "get msgs return\n";
	return $msgs;
}


function cbox_post ($msg, $user, $box, &$error = '') {
	$srv = $box['srv'];
	$boxid = $box['id'];
	$boxtag = $box['tag'];
	echo "cbox_post ".date('H:i:s')."\n";
	$host = "www$srv.cbox.ws";
	$path = "/box/?boxid=$boxid&boxtag=$boxtag&sec=submit";
	$port = 80;
	$timeout = 30;

	$post = array(
		'nme' => $user['name'],
		'key' => $user['token'],
		'eml' => $user['url'],
		'pst' => $msg,
		'aj' => '1'
	);	

	$req = '';
	$res = '';
	
	foreach ($post as $k => $v) {
		$req .= "$k=".urlencode($v)."&";
	}
	$req = substr($req, 0, -1);
	
	$hdr  = "POST $path HTTP/1.1\r\n";
	$hdr .= "Host: $host\r\n";
	$hdr .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$hdr .= "Content-Length: ".strlen($req)."\r\n\r\n";
	
	$fp = fsockopen ($host, $port, $errno, $errstr, $timeout);
	
	if (!$fp) {
		$error = "Could not open socket: $errno - $errstr\n";
		return;
	}

	fputs ($fp, $hdr.$req);
	$t = 1;
	// while (!feof($fp)) {
	// 	echo "$t "; $t += 1;
	// 	$res .= fgets ($fp, 1024);
	// }
	
	fclose ($fp);

	if (!$res || !strpos($res, "200 OK")) {
		$error = "Bad response:\r\n $res";
		return;
	}

	$matches = array();
	preg_match('/1(.*)\t(.*)\t(.*)\t(.*)\t(.*)\t(.*)/', $res, $matches);
	$err = $matches[1];
	echo $matches[2]."\n";
	echo $matches[3]."\n";
	echo $matches[4]."\n";
	echo $matches[5]."\n";
	$id = $matches[6];

	if ($err) {
		$error = "Got error from Cbox: $err";
		return;
	}

	return $id;
}
