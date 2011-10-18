<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=module
[END_COT_EXT]
==================== */

// Environment setup
define('COT_FIND', true);
$env['location'] = 'find';

list($usr['auth_read'], $usr['auth_write'], $usr['isadmin']) = cot_auth('find', 'any');

require_once cot_incfile('find', 'module');
require_once cot_langfile('find', 'module');
require_once cot_incfile('forms');

/* === Hook === */
foreach (cot_getextplugins('find.first') as $pl)
{
	include $pl;
}
/* ===== */

list($pg, $d) = cot_import_pagenav('d', $cfg['find']['results_per_page']);

$q = cot_import('q', 'G', 'TXT');
$a = cot_import('a', 'G', 'ALP');
$f = cot_import('f', 'G', 'ARR');
$c = cot_import('c', 'G', 'TXT');
$r = cot_import('r', 'G', 'BOL');

$fields_list = array();
$area_list = array('all' => array());
foreach ($sources as $source => $data)
{
	$parts = explode('.', $source);
	if (!$area_list[$parts[0]])
	{
		$area_list[$parts[0]] = array($source);
	}
	else
	{
		$area_list[$parts[0]][] = $source;
	}
	$area_list['all'][] = $source;
	if ($a == $parts[0])
	{
		$fields_list = array_merge($fields_list, $data['columns']);
	}
}

if (!empty($q))
{
	$qhash = md5(serialize($_GET));
	$options = find_parse_query($q);
	if($cache && $d > 0)
	{
		if ($cache->mem && $cache->mem->exists($qhash, 'find'))
		{
			$results = $cache->mem->get($qhash, 'find');
		}
		elseif ($cache->db && $cache->db->exists($qhash, 'find'))
		{
			$results = $cache->db->get($qhash, 'find');
		}
	}
	if ($results === null)
	{
		$results = find_search($options, $a, $f);
		if ($cache && $cache->mem)
		{
			$cache->mem->store($qhash, $results, 'find', $cfg['find']['cache_ttl']);
		}
		elseif ($cache && $cache->db)
		{
			$cache->db->store($qhash, $results, 'find', $cfg['find']['cache_ttl']);
		}
	}
	$query = htmlentities($q, ENT_QUOTES, 'UTF-8');
}
if (COT_AJAX)
{
	if ($results) echo json_encode($results);
	exit;
}

require_once $cfg['system_dir'].'/header.php';

$tpllevels = array('find', $a, $c);
$tpllevels = array_filter($tpllevels);
$t = new XTemplate(cot_tplfile($tpllevels));

if ($results && $options)
{
	$items = array_slice($results, $d, $cfg['find']['results_per_page']);
	foreach($items as $item)
	{
		$data = find_get_itemdata($item['node_reftype'], $item['node_refid'], $options);
		$t->assign(array(
			'TYPE' => $item['node_reftype'],
			'DATE' => cot_date('date_full', $item['date']),
			'DATE_STAMP' => $item['date'],
			'RATING' => $item['rating'],
			'URL' => $data['url'],
			'TITLE' => $data['title'],
			'EXTRACT' => $data['extract']
		));
		foreach($item['words'] as $word => $count)
		{
			$t->assign(array(
				'WORD' => $word,
				'COUNT' => $count
			));
			$t->parse('MAIN.RESULTS.ITEMS.WORDS');
		}
		$t->parse('MAIN.RESULTS.ITEMS');
	}

	$pagenav = cot_pagenav('find', array('q' => $q), $d, count($results), $cfg['find']['results_per_page']);
	$t->assign(array(
		'RESULTS_TEXT' => cot_rc('find_results', array(
			'count' => $pagenav['entries'],
			'onpage' => $pagenav['onpage'], 
			'results' => cot_declension($pagenav['entries'], $Ls['results']))),
		'COUNT_TOTAL' => $pagenav['entries'],
		'COUNT_ONPAGE' => $pagenav['onpage'],
		'PAGE_CURRENT' => $pagenav['current'],
		'PAGE_PREV' => $pagenav['prev'],
		'PAGE_NEXT' => $pagenav['next'],
		'PAGE_LAST' => $pagenav['last'],
		'PAGE_NAV' => $pagenav['main'],
		'PAGE_COUNT' => $pagenav['total'],
	));
	$t->parse('MAIN.RESULTS');
}
elseif (!empty($q))
{
	$t->parse('MAIN.NORESULTS');
}

foreach ($area_list as $code => $parts)
{
	$area = ($code != 'all') ? $code : '';
	$t->assign(array(
		'TITLE' => $L["find_$code"],
		'URL' => cot_url('find', array('q' => $q, 'a' => $area)),
		'CLASS' => $a == $area ? ' class="selected"' : '',
		'INPUT' => $a == $code ? '<input type="hidden" name="a" value="'.$a.'" />' : ''
	));
	if ($a == $area && $fields_list)
	{
		foreach ($fields_list as $field)
		{
			if ($L["find_{$a}_{$field}"])
			{
				$t->assign('CHECKBOX', cot_checkbox(
					(!$f || in_array($field, $f)),
					'f[]',
					$L["find_{$a}_{$field}"],
					'',
					$field
				));
				// Double-parse allows for field listing both
				// inside and outside of area listing.
				$t->parse('MAIN.SOURCES.FIELDS.FIELD');
				$t->parse('MAIN.FIELDS.FIELD');
			}
		}
		$t->parse('MAIN.SOURCES.FIELDS');
		$t->parse('MAIN.FIELDS');
	}
	$t->parse('MAIN.SOURCES');
}

$t->assign(array(
	'FORM_ACTION' => cot_url('find')
));

$t->parse('MAIN');
$t->out('MAIN');

require_once $cfg['system_dir'].'/footer.php';

?>