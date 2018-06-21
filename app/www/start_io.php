<?php

Header("Content-Type: text/html; charset=UTF-8"); 
header("Access-Control-Allow-Origin:*"); 
header("Access-Control-Allow-Methods:OPTION, POST, GET "); 
header("Access-Control-Allow-Headers:X-Requested-With, Content-Type");
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;

include '/app/extensions/PushNotification/vendor/autoload.php';

// Global array save uid online data
$uidConnectionMap = array();
$aidConnectionMap = array();
// Record the number of online users who last broadcast
$last_online_count = 0;
$alast_online_count = 0;
// Record the number of online pages broadcast last time
$last_online_page_count = 0;
$alast_online_page_count = 0;

// PHPSocketIOservice
$sender_io = new SocketIO(2120);
// When the client initiates a connection event, set various event callbacks for connecting sockets
$sender_io->on('connection', function($socket){
    // Triggered when the client sends a login event
    $socket->on('login', function ($uid)use($socket){
        global $uidConnectionMap, $last_online_count, $last_online_page_count;
        // Already logged in
        if(isset($socket->uid)){
            return;
        }
        // Update online data corresponding to uid
        $uid = (string)$uid;
        if(!isset($uidConnectionMap[$uid]))
        {
            $uidConnectionMap[$uid] = 0;
        }
        // This uid has ++$uidConnectionMap[$uid] socket connections
        ++$uidConnectionMap[$uid];
        //Add this connection to the uid group to facilitate pushing data for uid
        $socket->join($uid);
        $socket->uid = $uid;
        // Update the online data of this socket corresponding page
        $socket->emit('update_online_count', "<b>{$last_online_count}</b><b>{$last_online_page_count}</b>");
    });

    $socket->on('account_login', function ($aid)use($socket){
        global $aidConnectionMap, $alast_online_count, $alast_online_page_count;
        // Already logged in
        if(isset($socket->aid)){
            return;
        }
        // Update online data corresponding to uid
        $aid = (string)$aid;
        if(!isset($uidConnectionMap[$aid]))
        {
            $uidConnectionMap[$aid] = 0;
        }
        // This uid has ++$uidConnectionMap[$uid] socket connections
        ++$uidConnectionMap[$aid];
        //Add this connection to the uid group to facilitate pushing data for uid
        $socket->join($aid);
        $socket->aid = $aid;
    });
    
    // When the client disconnects, it is triggered (usually due to a web page shutdown or a jump refresh)
    $socket->on('disconnect', function () use($socket) {
        if(!isset($socket->uid) || !isset($socket->aid))
        {
            return;
        }
        global $uidConnectionMap, $sender_io;
        global $aidConnectionMap;
        // Decrease the number of uid online sockets by one
        if(--$uidConnectionMap[$socket->uid] <= 0)
        {
            unset($uidConnectionMap[$socket->uid]);
        }

        if(--$aidConnectionMap[$socket->aid] <= 0)
        {
            unset($aidConnectionMap[$socket->aid]);
        }
    });
});

// When $sender_io is started, it listens on an http port, through which data can be pushed to any uid or all uids
$sender_io->on('workerStart', function(){
    // 监听一个http端口
    $inner_http_worker = new Worker('http://0.0.0.0:2121');
    // Listen on a http port
    $inner_http_worker->onMessage = function($http_connection, $data){
        global $uidConnectionMap;
        $_POST = $_POST ? $_POST : $_GET;
        // The url format of the pushed data type=publish&to=uid&content=xxxx
        switch(@$_POST['type']){
            case 'publish':
                global $sender_io;
                $to = @$_POST['to'];
                $acc = @$_POST['acc'];

                $_POST['content'] = htmlspecialchars(@$_POST['content']);

                if($acc) {
                    $sender_io->to($acc)->emit('new_msg', 'To Account: '.$_POST['content']);
                    if($to && !isset($aidConnectionMap[$acc])){
                        return $http_connection->send('offline');
                    }else{
                        return $http_connection->send('ok');
                    }

                    break;
                }
                // If uid is specified, data is sent to the socket group where uid is located.
                if($to){
                    $sender_io->to($to)->emit('new_msg', $_POST['content']);
                // Otherwise push data to all uids
                }else{
                    $sender_io->emit('new_msg', @$_POST['content']);
                }
                // HTTP interface returns if user offline socket returns fail
                if($to && !isset($uidConnectionMap[$to])){
                    return $http_connection->send('offline');
                }else{
                    return $http_connection->send('ok');
                }

                break;
        }
        return $http_connection->send('fail');
    };
    // Perform monitoring
    $inner_http_worker->listen();

    // A timer that periodically pushes the current uid online number and online page number to all uids
    Timer::add(1, function(){
        global $uidConnectionMap, $sender_io, $last_online_count, $last_online_page_count;
        $online_count_now = count($uidConnectionMap);
        $online_page_count_now = array_sum($uidConnectionMap);
        // Only when the number of clients online changes before broadcasting, reduce unnecessary client communication
        if($last_online_count != $online_count_now || $last_online_page_count != $online_page_count_now)
        {
            $sender_io->emit('update_online_count', "<b>{$online_count_now}</b><b>{$online_page_count_now}</b>");
            $last_online_count = $online_count_now;
            $last_online_page_count = $online_page_count_now;
        }
    });
});

if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
