<?php

$pluginName = 'community_satis';

$isWritable = community_satis::isConfigFilePathWritable();

if (!$isWritable) {
	register_error(elgg_echo('community_satis:settings:not_writable', [community_satis::getConfigFilePath()]));
}

echo '<p class="mts">' . elgg_view('output/url', [
	'href' => elgg_add_action_tokens_to_url(elgg_normalize_url('action/satis/generate')),
	'class' => 'elgg-button elgg-button-submit',
	'text' => elgg_echo('community_satis:settings:rebuild:button'),
]) . '</p>';

echo '<p>';
echo '<label>' . elgg_echo('community_satis:settings:satispath') . '</label>';

echo elgg_view('input/text', [
	'name' => 'params[satispath]',
	'value' => community_satis::getConfigFilePath()
]);
echo '</p>';

echo '<pre>';

exec('/usr/bin/env php -v', $out, $ret);

echo "Exit code: $ret\n";
echo implode("\n", $out);

echo '</pre>';

echo '<p>';
echo '<label>' . elgg_echo('community_satis:settings:satisbuildtimestamp') . '</label>';

echo elgg_view('output/friendlytime', [
	'time' => elgg_get_plugin_setting('satisbuildtimestamp', $pluginName),
]);

echo elgg_view('input/plaintext', [
	'class' => 'mtm mbm',
	'value' => elgg_get_plugin_setting('satisbuildoutput', $pluginName),
	'disabled' => 'disabled',
]);
echo '</p>';
