<?php

class community_satis {

	public static function init() {
		elgg_register_action('satis/generate', dirname(__DIR__) . '/actions/satis/update.php', 'admin');
		elgg_register_plugin_hook_handler('cron', 'fifteenmin', ['community_satis', 'cronGenerate']);
	}

	public static function cronGenerate() {

		$lastTime = elgg_get_plugin_setting('satisbuildtimestamp', 'community_satis', ELGG_ENTITIES_ANY_VALUE);

		//verify that we have any new plugin releases and trigger generate
		$countNewReleases = elgg_get_entities([
			'type' => 'object',
			'subtype' => 'plugin_project',
			'count' => true,
			'modified_time_lower' => $lastTime,
		]);
		if ($countNewReleases > 0) {
			self::generate();
		}
	}

	public static function generate() {
		$generator = new SatisConfigGenerator();

		$writtenBytes = $generator->writeConfigFile(self::getConfigFilePath());

		if ($writtenBytes) {
			// call the build command
			chdir('/var/www/plugins.elgg.org');
			exec('./composer.phar satis:build 2>&1', $output, $returnVal);

			elgg_set_plugin_setting('satisbuildoutput', implode("\n", $output), 'community_satis');
			elgg_set_plugin_setting('satisbuildtimestamp', time(), 'community_satis');

			return $returnVal === 0;
		} else {
			return false;
		}
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