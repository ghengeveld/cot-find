<?php

defined('COT_CODE') or die('Wrong URL');

global $sources, $db_x, $sys, $blacklist, $charblacklist;

require_once cot_incfile('find', 'module');

$sources['forums.topics'] = array(
	'table' => "{$db_x}forum_topics",
	'columns' => array(
		'ft_title',
		'ft_desc'
	),
	'col_id' => 'ft_id'
);
$sources['forums.posts'] = array(
	'table' => "{$db_x}forum_posts",
	'columns' => array(
		'fp_text'
	),
	'col_id' => 'fp_id'
);

find_index_all(array('forums.topics', 'forums.posts'));

?>