<?php
class PushNotification {
	public static $user_connection_map = [];
	public static $account_connection_map = [];
	public static $users_online = [];
	public static $accounts_online = [];

	private $sender_io;
	private $http_connection;

	public function __construct(&$sender_io, &$http_connection) {
		$this->sender_io = &$sender_io;
		$this->http_connection = &$http_connection;
	}
	
	public function handle_request($data) {
		$type = $data['type'] ?? null; 

		switch($type){
            case 'publish':
            	$content = isset($data['content']) ? htmlspecialchars($data['content']) : null;
            	if(isset($data['uid']) && $data['uid'] != "") {
            		return $this->send_message_to_user($data['uid'], $content);
            	} elseif (isset($data['aid']) && $data['aid'] != "") {
            		return $this->send_message_to_account($data['aid'], $content);
            	} else {
            		return $this->send_message_to_all($content);
            	}

                break;

           	default:
           		break;
        }

        $this->http_connection->send(Utils::str_out(['status' => 'fail', 'err' => '`type` not defined']);
        return false;
	}

	public function send_message_to_user($user_id, $message) {
		$this->sender_io->to($user_id)->emit('data', $message);
		if(!isset(PushNotification::$user_connection_map[$user_id])){
			$this->http_connection->send(Utils::str_out(['status' => 'fail', 'err' => 'User offline']);
			return false;
        }else{
        	$this->http_connection->send(Utils::str_out(['status' => 'fail', 'msg' => 'Message sent', 'user' => $user_id]);
        	return true;
        }
	}

	public function send_message_to_account($account_id, $message) {
		$this->sender_io->to($account_id)->emit('data', $message);
		if(!isset(PushNotification::$account_connection_map[$account_id])){
			$this->http_connection->send(['status' => 'fail', 'err' => 'Account users offline', 'account' => $account_id]);
			return false;
        }else{
        	$this->http_connection->send(['status' => 'success', 'msg' => 'Message sent', 'account' => $account_id]);
        	return true;
        }
	}

	public function send_message_to_all($message) {
		$this->sender_io->emit('data', $message);
		$this->http_connection->send(['status' => 'success', 'msg' => 'Message sent', 'global' => true);
	}

	public static function update_user_connection($user_id) {
        if(!isset(PushNotification::$user_connection_map[$user_id])) {
            PushNotification::$user_connection_map[$user_id] = 0;
        }

        // This $user_id has ++PushNotification::$user_connection_map[$user_id] socket connections
        PushNotification::$user_connection_map[$user_id]++;
	}

	public static function update_account_connection($account_id) {
        if(!isset(PushNotification::$account_connection_map[$account_id])) {
            PushNotification::$account_connection_map[$account_id] = 0;
        }

        // This $account_id has ++PushNotification::$account_connection_map[$account_id] socket connections
        PushNotification::$account_connection_map[$account_id]++;
	}

	public static function disconnect($socket) {
		// Decrease the number of user_id/ account_id online sockets by one
        if(--PushNotification::$user_connection_map[$socket->user_id] <= 0) {
            unset(PushNotification::$user_connection_map[$socket->user_id]);
        }

        if(--PushNotification::$account_connection_map[$socket->account_id] <= 0) {
            unset(PushNotification::$account_connection_map[$socket->account_id]);
        }
	}
}