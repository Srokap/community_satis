<?php

class community_satis {

	public static function init() {

	}

	public static function generate() {
		$generator = new SatisConfigGenerator();
		$generator->writeConfigFile(elgg_get_config('dataroot') . 'satis.json');
	}
}