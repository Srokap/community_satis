<?php

if (!community_satis::generate()) {
	register_error(elgg_echo('community_satis:action:generate:fail'));
} else {
	system_message(elgg_echo('community_satis:action:generate:success'));
}
