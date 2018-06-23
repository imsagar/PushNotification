<?php
require_once('init.php');

class SocketEngine {
    private $sender_io;
    public static $debug = false;
    const SOCKET_PORT = 2120;
    const WEBSERVER_URL = 'http://0.0.0.0:2121';

    public static function init() {
        self::set_env();
        
        // Global array save uid online data
        PushNotification::$user_connection_map = [];
        PushNotification::$account_connection_map = [];        

        $engine = new SocketEngine;
        $engine->start_socker_server();
        $engine->start_web_server();

        Workerman\Worker::runAll();
    }

    private static function set_env() {
        global $argv;
        if(in_array('--log', $argv)) {
            self::$debug = true;
        }
    }

    public function start_socker_server() {
        // PHPSocketIOservice
        $this->sender_io = new PHPSocketIO\SocketIO(SocketEngine::SOCKET_PORT);

        // When the client initiates a connection event, set various event callbacks for connecting sockets
        $this->sender_io->on('connection', function($socket){
            // Triggered when the client sends a login event
            $socket->on('user', function ($user_id) use ($socket){

                // Already logged in
                if(isset($socket->user_id)){
                    return;
                }

                // Update online data corresponding to user_id
                PushNotification::update_user_connection($user_id);
                Logger::d('User ID: '.$user_id);

                //Add this connection to the user_id group to facilitate pushing data for user_id
                $socket->join($user_id);
                $socket->user_id = $user_id;
            });

            $socket->on('account', function ($account_id) use ($socket){
                // Already logged in
                if(isset($socket->account_id)){
                    return;
                }

                // Update online data corresponding to uid
                PushNotification::update_account_connection($account_id); 
                Logger::d('Account ID: '.$account_id);

                //Add this connection to the uid group to facilitate pushing data for uid
                $socket->join($account_id);
                $socket->account_id = $account_id;
            });
            
            // When the client disconnects, it is triggered (usually due to a web page shutdown or a jump refresh)
            $socket->on('disconnect', function () use($socket) {
                if(!isset($socket->user_id) || !isset($socket->account_id)) {
                    return;
                }

                // Decrease the number of user_id/ account_id online sockets by one
                PushNotification::disconnect($socket);
            });
        });
    }

    public function start_web_server() {
        // When $sender_io is started, it listens on an http port, through which data can be pushed to any uid or all uids
        $this->sender_io->on('workerStart', function(){
            // Listen on a http port
            $inner_http_worker = new Workerman\Worker(SocketEngine::WEBSERVER_URL);

            $inner_http_worker->onMessage = function($http_connection, $data){
                $push = new PushNotification($this->sender_io, $http_connection);
                $data = $_POST ? $_POST : $_GET;
                $push->handle_request($data);
            };

            // Perform monitoring
            $inner_http_worker->listen();
        });
    }
} SocketEngine::init();
