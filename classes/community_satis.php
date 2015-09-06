<?php

class community_satis {

	public static function init() {
		elgg_register_action('satis/generate', dirname(__DIR__) . '/actions/satis/update.php', 'admin');
	}

	public static function generate() {
		$generator = new SatisConfigGenerator();

		return $generator->writeConfigFile(self::getConfigFilePath());
	}

	/**
	 * @return string
	 */
	public static function getConfigFilePath() {
		$path = elgg_get_plugin_setting('satispath', 'community_satis');
		if (!$path) {
			$path = elgg_get_config('dataroot') . 'satis.json';
		}
		return $path;
	}

	/**
	 * @return bool
	 */
	public static function isConfigFilePathWritable() {
		$configFilePath = community_satis::getConfigFilePath();
		$dirWritable = is_writable(dirname($configFilePath));

		if (file_exists($configFilePath)) {
			return $dirWritable && is_writable($configFilePath);
		} else {
			return $dirWritable;
		}
	}
}