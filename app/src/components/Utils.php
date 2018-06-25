<?php
class Utils {
	public static function str_out($data) {
		if(is_array($data)) {
			echo json_encode($data);
		} else {
			echo $data;
		}
	}
}