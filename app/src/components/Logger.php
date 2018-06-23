<?php
class Logger {
	private static $log_file = '/tmp/socket.log';
	private static $fp = null;

	public static function d($message) {
		if(SocketEngine::$debug === false) return false;
		
		if(self::$fp === null) {
			self::$fp = fopen(self::$log_file, 'w');
		}

		fwrite(self::$fp, '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL);
	}
}