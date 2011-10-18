<?php

/* ====================
[BEGIN_COT_EXT]
Hooks=find.search.loop
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

global $structure, $c, $r, $db_x;

if ($a == 'page' && $c && $structure[$a][$c])
{
	$categories = array($c);
	if ($r)
	{
		$categories = explode(',', $db->query("
			SELECT GROUP_CONCAT(structure_code)
			FROM {$db_x}structure
			WHERE structure_path LIKE CONCAT((
				SELECT structure_path FROM {$db_x}structure
				WHERE structure_code = ?), '%')
		", array($c))->fetchColumn());
	}
	$cat = $db->query("
		SELECT page_cat FROM {$db_x}pages
		WHERE page_id = ?", array($row['node_refid'])
	)->fetchColumn();
	if (!in_array($cat, $categories)) $skip = true;
}

?>