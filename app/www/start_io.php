<?php
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Lib\Timer;
use PHPSocketIO\SocketIO;

include '/app/extensions/PushNotification/vendor/autoload.php';

// Global array save uid online data
$uidConnectionMap = array();
// Record the number of online users who last broadcast
$last_online_count = 0;
// Record the number of online pages broadcast last time
$last_online_page_count = 0;

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
        echo count($uidConnectionMap);
        //Add this connection to the uid group to facilitate pushing data for uid
        $socket->join($uid);
        $socket->uid = $uid;
        // Update the online data of this socket corresponding page
        $socket->emit('update_online_count', "<b>{$last_online_count}</b><b>{$last_online_page_count}</b>");
    });
    
    // When the client disconnects, it is triggered (usually due to a web page shutdown or a jump refresh)
    $socket->on('disconnect', function () use($socket) {
        if(!isset($socket->uid))
        {
             return;
        }
        global $uidConnectionMap, $sender_io;
        // 将uid的在线socket数减一
        if(--$uidConnectionMap[$socket->uid] <= 0)
        {
            unset($uidConnectionMap[$socket->uid]);
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
                $_POST['content'] = htmlspecialchars(@$_POST['content']);
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
