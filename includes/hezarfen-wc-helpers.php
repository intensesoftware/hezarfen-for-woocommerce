<?php

defined('ABSPATH') || exit();

/**
 *
 * Update array keys for select option values
 *
 * @param $arr
 * @return array
 */
function hezarfen_wc_checkout_select2_option_format($arr)
{
	$values = [];

	foreach ($arr as $key => $value) {
		$values[sprintf("%d:%s", $key, $value)] = $value;
	}

	return $values;
}
