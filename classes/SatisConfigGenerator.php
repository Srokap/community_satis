<?php

class SatisConfigGenerator
{
	/**
	 * @param $release
	 * @param $type
	 * @return bool|string
	 */
	protected function guessName($release, &$type)
	{
		$filestorePath = $release->getFilenameOnFilestore();
		$filename = $release->originalfilename;
//		var_dump($filename, $filestorePath);

		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if ($extension == 'gz') {
			if (strtolower(pathinfo(substr($filename, 0, strlen($filename) - 3), PATHINFO_EXTENSION)) == 'tar') {
				$extension = 'tar.gz';
			}
		}

		if (!in_array($extension, array('zip', 'tar.gz'))) {
//			var_dump('BAD ext: ' . $extension);
			return false;
		}

		$resultId = false;
		$resultDir = false;

		/*
		 * guess package name
		 */
		if ($extension == 'zip') {
			$type = 'zip';
			$zip = new ZipArchive();
			if ($errorCode = $zip->open($filestorePath)) {
				$manifestsCnt = 0;
				$dirname = null;
				for ($i = 0; $i < $zip->numFiles; $i++) {
					$stat = $zip->statIndex($i);
					if (basename($stat['name']) == 'manifest.xml') {
						$dirname = trim(pathinfo($stat['name'], PATHINFO_DIRNAME), '/\\');
						if (!empty($dirname) && $dirname !== '.' && strpos($dirname, '/') === false && strpos($dirname, '\\') === false) {
							$resultDir = $dirname;
							if (preg_match('#^([^\-]+)\-(.+)\-([0-9a-fA-F]{7}|master)$#', $resultDir, $matches)) {
								$resultDir = $matches[2];
							} elseif (strpos($resultDir, '-' . $release->version) === strlen($resultDir) - strlen('-' . $release->version)) {
								// cut away version appended by git
								$resultDir = substr($resultDir, 0, strlen($resultDir) - strlen('-' . $release->version));
							}
							$manifestsCnt++;
							$stream = $zip->getStream($stat['name']);
							if (is_resource($stream)) {
								$content = stream_get_contents($stream);
								try {
									$manifest = new ElggPluginManifest($content);
									$manifestArr = $manifest->getManifest();
									if (isset($manifestArr['id'])) {
										$resultId = $manifestArr['id'];
									}
								} catch (Exception $e) {
								}
							}
						}
					}
				}
				if ($manifestsCnt == 1) {
					if ($resultId) {
						return $resultId;
					} else {
						return $resultDir;
					}
				} else {
					return false; //no manifests or too many manifests in proper nesting
				}
			} else {
//				var_dump('ERROR:', $errorCode);
				return false; // cant open archive
			}
		} else { // tar.gz
			$type = 'tar';
			try {
				$tar = new PharData($filestorePath);
			} catch (UnexpectedValueException $e) {
				return false;
			}
			$tarIterator = new RecursiveIteratorIterator($tar);

			$prefix = 'phar://' . $filestorePath . '/';
			$manifestsCnt = 0;
			$dirname = null;
			$tarIterator->rewind();
			/**
			 * @var $file PharFileInfo
			 */
			while ($tarIterator->valid()) {
				try {
					$file = $tarIterator->current();
				} catch (RuntimeException $e) {
					$tarIterator->next();
					continue;
				}

				$path = substr($file->getPathname(), strlen($prefix));
				if ($file->getFilename() == 'manifest.xml') {
					$dirname = trim(pathinfo($path, PATHINFO_DIRNAME), '/\\');
					if (!empty($dirname) && $dirname !== '.' && strpos($dirname, '/') === false && strpos($dirname, '\\') === false) {
						$resultDir = $dirname;
						if (preg_match('#^([^\-]+)\-(.+)\-([0-9a-fA-F]{7}|master)$#', $resultDir, $matches)) {
							$resultDir = $matches[2];
						} elseif (strpos($resultDir, '-' . $release->version) === strlen($resultDir) - strlen('-' . $release->version)) {
							// cut away version appended by git
							$resultDir = substr($resultDir, 0, strlen($resultDir) - strlen('-' . $release->version));
						}
						$manifestsCnt++;
						$content = $file->getContent();
						try {
							$manifest = new ElggPluginManifest($content);
							$manifestArr = $manifest->getManifest();
							if (isset($manifestArr['id'])) {
								$resultId = $manifestArr['id'];
							}
						} catch (Exception $e) {
						}
					}
				}

				$tarIterator->next();
			}
			if ($manifestsCnt == 1) {
				if ($resultId) {
					return $resultId;
				} else {
					return $resultDir;
				}
			} else {
				return false; //no manifests or too many manifests in proper nesting
			}
			return false;
		}
	}

	/**
	 * @param PluginRelease $release
	 * @param PluginProject $pluginProject
	 * @return array
	 */
	protected function getReleaseConfig(PluginRelease $release, PluginProject $pluginProject)
	{
		//FIXME need way better guessing of title (manifest id and dir name)
		$title = elgg_get_friendly_title($project->title);

		$owner = $release->getOwnerEntity();

		if (!$name = $this->guessName($release, $extension)) {
			return false;
		}

		$versions = $release->elgg_version;
		if (!is_array($versions) && $versions) {
			$versions = array($versions);
		}
		//filter out invalid values like '1' for very old plugins
		foreach ($versions as $key => $version) {
			if (strlen($version) >= 3) {
				$versions[$key] = '~' . $version;
			} else {
				unset($versions[$key]);
			}
		}

		$requires = array(
			"composer/installers" => ">=1.0.8",
		);
		if (count($versions)) {
			$requires['elgg/elgg'] = implode('|', $versions);
		}

		$arr = array(
			'type' => 'package',
			'package' => array(
				'name' => $owner->username . '/' . $name,
				'homepage' => $pluginProject->homepage,
				'version' => $release->version,
				"type" => "elgg-plugin",
				"description" => $pluginProject->summary,
				"dist" => array(
					"url" => elgg_normalize_url("plugins/download/" . $release->guid),
					"type" => $extension
				),
				'authors' => array(
					array(
						'name' => $owner->name,
						'homepage' => $owner->getURL(),
						"role" => "Maintainer"
					)
				),
				"require" => $requires,
			),
		);
		return $arr;
	}

	/**
	 * Save satis JSON config to specified file.
	 *
	 * @param $path
	 */
	public function writeConfigFile($path)
	{
		$pluginProjects = new ElggBatch('elgg_get_entities_from_access_id', array(
			'type' => 'object',
			'subtype' => 'plugin_project',
			'access_id' => ACCESS_PUBLIC,
			'limit' => 0,
		));

		$i = 0;
		$validCnt = 0;
		$invalidCnt = 0;

		$result = array();

		foreach ($pluginProjects as $pluginProject) {
			if ($pluginProject instanceof PluginProject) {
				//get all releases associated with the project
				$releases = elgg_get_entities(array(
					'type' => 'object',
					'subtype' => 'plugin_release',
					'container_guid' => $pluginProject->guid,
					'limit' => 0,
				));

				/*
					The version of the package. In most cases this is not required and should be omitted (see below).

					This must follow the format of X.Y.Z or vX.Y.Z with an optional suffix of -dev, -patch, -alpha, -beta or -RC. The patch, alpha, beta and RC suffixes can also be followed by a number.

					Examples:

					1.0.0
					1.0.2
					1.1.0
					0.2.5
					1.0.0-dev
					1.0.0-alpha3
					1.0.0-beta2
					1.0.0-RC5
				 */

				$regExp = '/^[0-9]+(\.[0-9]+){0,}(-(?:dev|patch|alpha|beta|rc)([0-9]+)?)?$/i';
				// 1948 - plugin projects
				// 3355 valid versions - case insensitive
				// 767 invalid versions - case insensitive

				foreach ($releases as $release) {
					if ($release instanceof PluginRelease) {
						$isValidVersionFormat = preg_match($regExp, $release->version) > 0;
						// var_dump($release->version, $isValidVersionFormat);
						if ($isValidVersionFormat) {
							$validCnt++;
							$result[] = $this->getReleaseConfig($release, $pluginProject);

						} else {
							$invalidCnt++;
						}
					} else {
						$invalidCnt++;
					}
				}

				// $latestRelease = $pluginProject->getLatestRelease();
				// if ($latestRelease instanceof PluginRelease) {
				// 	echo '<pre>';
				// 	var_dump($latestRelease);
				// 	echo '</pre>';
				// }
			}

			$i++;
			//	if ($i > 300) {
			//		break;
			//	}
		}
		$result = array_values(array_filter($result, 'is_array'));

		$configuration = array(
			"name" => "Elgg plugins",
			"homepage" => "http://plugins.elgg.org",
			"description" => "For details of usage, have a look at <a class=\"alert-link\" href=\"http://community.elgg.org/pages/view/1630158/experimental-community-plugins-mirror-as-composer-repository\">this instruction</a>",
            "twig-template" => "./views/index.html.twig",
			"output-dir" => "web",
			"require-all" => true,
			"repositories" => $result,
		);

//		var_dump($i, $validCnt, $invalidCnt);

		$json = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

		return file_put_contents($path, $json);
	}

} 