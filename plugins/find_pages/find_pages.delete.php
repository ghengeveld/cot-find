<?php

/* ====================
[BEGIN_COT_EXT]
Hooks=page.edit.update.done,page.admin.delete.done
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

if (cot_module_active('find'))
{
	require_once cot_incfile('find', 'module');
	find_remove_index('page', $id);
}

?>