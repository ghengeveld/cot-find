<?php

/* ====================
[BEGIN_COT_EXT]
Hooks=find.sources
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_langfile('find_pages', 'plug');

find_register_source(
	'page',
	array('page', array('al={page_alias}', 'id={page_id}')),
	"{$db_x}pages",
	array(
		'page_title',
		'page_desc',
		'page_text'
	),
	'page_id',
	'page_title',
	'page_begin'
);

?>