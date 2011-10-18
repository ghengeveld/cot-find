<?php

/* ====================
[BEGIN_COT_EXT]
Hooks=find.sources
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_langfile('find_forums', 'plug');

find_register_source(
	'forums.topics',
	array('forums', 'm=posts&q={ft_id}'),
	"{$db_x}forum_topics",
	array(
		'ft_title',
		'ft_desc'
	),
	'ft_id',
	'ft_title',
	'ft_updated'
);
find_register_source(
	'forums.posts',
	array('forums', 'm=posts&p={fp_id}'),
	"{$db_x}forum_posts",
	'fp_text',
	'fp_id',
	"{$db_x}forum_topics.ft_title ON {$db_x}forum_topics.ft_id = {$db_x}forum_posts.fp_topicid",
	'fp_updated'
);

?>