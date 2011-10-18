<?php

defined('COT_CODE') or die('Wrong URL');

global $sources, $db_x, $sys, $blacklist, $charblacklist;

require_once cot_incfile('find', 'module');

$sources['page'] = array(
	'table' => "{$db_x}pages",
	'columns' => array(
		'page_title',
		'page_desc',
		'page_text'
	),
	'col_id' => 'page_id'
);

find_index_all(array('page'));

?>