<?php

/* ====================
[BEGIN_COT_EXT]
Hooks=find.search.loop
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

global $structure, $c, $db_x;

if ($a == 'forums' && $c && $structure[$a][$c])
{
	if ($row['node_reftype'] == 'forums.topics')
	{
		$cat = $db->query("
			SELECT ft_cat FROM {$db_x}forum_topics
			WHERE ft_id = ?", array($row['node_refid'])
		)->fetchColumn();
		if ($c != $cat) $skip = true;
	}
	if ($row['node_reftype'] == 'forums.posts')
	{
		$cat = $db->query("
			SELECT fp_cat FROM {$db_x}forum_posts
			WHERE fp_id = ?", array($row['node_refid'])
		)->fetchColumn();
		if ($c != $cat) $skip = true;
	}
}

?>