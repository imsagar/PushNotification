<?php
require '/app/extensions/PushNotification/vendor/autoload.php';

class App {
	private static $components_path = __DIR__.'/components';

	public static function init() {
		spl_autoload_register(['App', 'autoload']);
	}

	public static function autoload($classname) {
		if(is_file(self::$components_path.'/'.$classname.'.php')) {
			include_once(self::$components_path.'/'.$classname.'.php');			
		}
	}
} App::init();
