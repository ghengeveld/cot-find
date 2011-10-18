<?php

/**
 * Search and indexer functions
 *
 * @package find
 * @version 5.0
 * @author Koradhil
 * @copyright Copyright (c) Cotonti Team 2008-2011
 * @license BSD
 */

defined('COT_CODE') || die('Wrong URL.');

require_once cot_incfile('find', 'module', 'indexer.blacklist');

$sources = array();

/* === Hook === */
foreach (cot_getextplugins('find.sources') as $pl)
{
	include $pl;
}
/* ===== */

/**
 * Parse search query into various options
 *
 * @param string $query Search query
 * @return array
 *	Multidimensional array with primary keys
 *  'words', 'phrases', 'required', and 'excluded'
 */
function find_parse_query($query)
{
	$words = find_get_words($query);
	$required = find_get_words($query, 'required');
	$excluded = find_get_words($query, 'excluded');
	$phrases = find_get_words($query, 'phrases');
	return array(
		'words' => array_diff($words, $excluded),
		'required' => $required,
		'excluded' => $excluded,
		'phrases' => $phrases
	);
}

/**
 * Get search results
 *
 * @param array $options As returned by find_parse_query()
 * @return array
 */
function find_search($options, $a, $f)
{
	global $db, $db_x, $sources, $area_list;

	$results = array();
	if (count($options['words']) > 0)
	{
		$joins = array();
		$where = array();
		$where[] = "w.word_value IN (
			'" . implode("', '", $options['words']) . "'
		)";
		if ($f)
		{
			$where[] = "o.location IN (
				'" . implode("', '", $f) . "'
			)";
		}
		foreach ($options['required'] as $word)
		{
			$where[] = "o.node_id IN (
				SELECT o.node_id
				FROM {$db_x}indexer_words AS w
				INNER JOIN {$db_x}indexer_occurrences AS o
				ON o.word_id = w.word_id
				WHERE w.word_value = '".$db->prep($word)."'
			)";
		}
		if (count($options['excluded']) > 0)
		{
			$where[] = "o.node_id NOT IN (
				SELECT o.node_id
				FROM {$db_x}indexer_words AS w
				INNER JOIN {$db_x}indexer_occurrences AS o
				ON o.word_id = w.word_id
				WHERE w.word_value IN (
					'" . implode("', '", $options['excluded']) . "'
				)
			)";
		}
		if ($a)
		{
			$where[] = "n.node_reftype IN ('".implode("', '", $area_list[$a])."')";
		}

		/* === Hook === */
		foreach (cot_getextplugins('find.search.query') as $pl)
		{
			include $pl;
		}
		/* ===== */

		$joins = implode(' ', $joins);
		$where = implode(' AND ', $where);
		$res = $db->query("
			SELECT
				n.node_id, n.node_reftype, n.node_refid,
				GROUP_CONCAT(w.word_value) AS words_csv,
				GROUP_CONCAT(o.occurrences) AS occurrences_csv
			FROM {$db_x}indexer_words AS w
			INNER JOIN {$db_x}indexer_occurrences AS o
			ON o.word_id = w.word_id
			INNER JOIN {$db_x}indexer_nodes AS n
			ON n.node_id = o.node_id
			$joins
			WHERE $where
			GROUP BY n.node_refid
		");

		/* === Hook - Part1 : Set === */
		$extp = cot_getextplugins('find.search.loop');
		/* ===== */

		while ($row = $res->fetch())
		{
			$table = $sources[$row['node_reftype']]['table'];
			$columns = $sources[$row['node_reftype']]['columns'];
			$col_id = $sources[$row['node_reftype']]['col_id'];
			$col_date = $sources[$row['node_reftype']]['col_date'];
			$col_section = $sources[$row['node_reftype']]['col_section'];

			$words = array_combine(explode(',', $row['words_csv']), explode(',', $row['occurrences_csv']));
			unset($row['words_csv'], $row['occurrences_csv']);

			$skip = false;
			/* Plugins can set $skip=true to continue the loop, preventing the 
			 * current result from being stored in final $results array.
			 */

			/* === Hook - Part2 : Include === */
			foreach ($extp as $pl)
			{
				include $pl;
			}
			/* ===== */

			if ($skip) continue; // Continue $res->fetch()

			if ($table && $col_id)
			{
				// Check phrases
				if (count($options['phrases']) > 0 && $columns)
				{
					foreach($options['phrases'] as $phrase)
					{
						$where = array();
						foreach ($columns as $column)
						{
							$where[] = "$column LIKE '%$phrase%'";
						}
						$where = implode(' OR ', $where);
						$num = $db->query("
							SELECT COUNT($col_id) FROM $table
							WHERE $col_id = '{$row['node_refid']}'
							AND ($where)
						");
						if (!$num->fetchColumn())
						{
							// Phrase wasn't found, remove the words in the phrase from result
							$phrasewords = explode(' ', $phrase);
							foreach($phrasewords as $phraseword)
							{
								if ($words[$phraseword])
								{
									unset($words[$phraseword]);
									if (count($words) == 0)
									{
										continue 3; // Continue $res->fetch()
									}
								}
							}
						}
					}
				}
				// Get datetime information
				$row['date'] = 0;
				if ($col_date)
				{
					list($colname, $tablejoin) = find_parse_column($col_date);
					$res2 = $db->query("
						SELECT $colname FROM $table $tablejoin
						WHERE $table.$col_id = ?", array($row['node_refid'])
					);
					$row['date'] = $res2->fetchColumn();
				}
			}
			// Calculate rating based on word occurrences and total number of matched words.
			$row['rating'] = count($words) * array_sum($words);
			$row['words'] = $words;
			$results[] = $row;
		}
	}
	// Sort the results by their rating and publication date.
	if (count($results) > 0)
	{
		$s_rating = array();
		$s_date = array();
		foreach ($results as $key => $row) {
			$s_rating[$key] = $row['rating'];
			$s_date[$key] = $row['date'];
		}
		array_multisort($s_rating, SORT_DESC, $s_date, SORT_DESC, $results);
	}

	/* === Hook === */
	foreach (cot_getextplugins('find.search.done') as $pl)
	{
		include $pl;
	}
	/* ===== */

	return $results;
}

/**
 * Get data for a specific item.
 *
 * @param string $reftype Node type
 * @param int $refid Reference ID
 * @param array $options As returned by find_parse_query()
 * @return array
 */
function find_get_itemdata($reftype, $refid, $options)
{
	global $cfg, $db, $sources;
	$table = $sources[$reftype]['table'];
	$columns = $sources[$reftype]['columns'];
	$col_id = $sources[$reftype]['col_id'];
	$col_title = $sources[$reftype]['col_title'];
	$urlparams = $sources[$reftype]['urlparams'];
	if (!$table || !$columns || !$col_id || !$col_title || !$urlparams) return;

	// URL generation
	if (!is_array($urlparams[1]))
	{
		$urlparams[1] = array($urlparams[1]);
	}
	foreach ($urlparams[1] as $format)
	{
		if (strpos($format, '{'.$col_id.'}') !== false)
		{
			$urlparams[1] = str_replace('{'.$col_id.'}', $refid, $format);
			break;
		}
		if (preg_match_all('/{([a-zA-Z0-9_-]+)}/', $format, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$val = $db->query("
					SELECT $match[1] FROM $table
					WHERE $col_id = $refid
				")->fetchColumn();
				if (!$val) continue 2;
				$format = str_replace($match[0], $val, $format);
			}
		}
		$urlparams[1] = $format;
		break;
	}
	// Highlighting
	$hlparam = $cfg['plugin']['highlight']['param'];
	$highlight = '';
	if($hlparam)
	{
		$hlwords = trim(implode(' ', array_unique(array_merge($options['words'], explode(' ', implode(' ', $options['phrases']))))));
		$highlight = "&{$hlparam}=$hlwords";
	}
	$url = cot_url($urlparams[0], $urlparams[1].$highlight);

	// Title
	list($col_title, $tablejoin) = find_parse_column($col_title);
	$res = $db->query("
		SELECT $col_title FROM $table $tablejoin
		WHERE $table.$col_id = ?", array($refid)
	);
	$title = $res->fetchColumn();

	// Extract
	$extractcolumns = $columns;
	$col_title_pos = array_search($col_title, $extractcolumns);
	if ($col_title_pos !== false) unset($extractcolumns[$col_title_pos]);
	$text = strip_tags($db->query("
		SELECT CONCAT(".implode(', " ... ", ', $extractcolumns).")
		FROM $table WHERE $col_id = ?", array($refid))->fetchColumn());
	$extract = find_get_extract($text, $options['words'], $options['phrases']);

	/* === Hook === */
	foreach (cot_getextplugins('find.itemdata') as $pl)
	{
		include $pl;
	}
	/* ===== */

	return array(
		'url' => $url,
		'title' => $title,
		'extract' => $extract
	);
}

/* ================================================== */

/**
 * Build index for given node
 *
 * @param string $reftype Node type
 * @param id $refid Node reference identifier
 * @return int Query count
 */
function find_build_index($reftype, $refid)
{
	global $db, $db_x, $sources, $sys;
	if (!$sources[$reftype] || !is_int($refid)) return;
	$table = $sources[$reftype]['table'];
	$columns = $sources[$reftype]['columns'];
	$col_id = $sources[$reftype]['col_id'];
	set_time_limit(30);

	find_remove_index($reftype, $refid);
	
	$qcount = 0;
	$joins = array();
	$where = array("$col_id = $refid");

	/* === Hook === */
	foreach (cot_getextplugins('find.indexer.query') as $pl)
	{
		include $pl;
	}
	/* ===== */

	$select = implode(', ', $columns);
	$joins = implode(' ', $joins);
	$where = ($where) ? 'WHERE ' . implode(' AND ', $where) : '';
	$res = $db->query("SELECT $select FROM $table $joins $where");
	$qcount++;
	if ($row = $res->fetch())
	{
		$words = array();
		foreach ($columns as $column)
		{
			$words[$column] = array_count_values(find_get_words($row[$column]));
		}

		$res = $db->query("
			SELECT node_id
			FROM {$db_x}indexer_nodes
			WHERE node_reftype = '$reftype'
			AND node_refid = $refid")->fetch();
		$qcount++;
		if ($res)
		{
			// node already exists
			$node_id = $res['node_id'];
		}
		else
		{
			// create new node
			$db->insert("{$db_x}indexer_nodes", array('node_reftype' => $reftype, 'node_refid' => $refid));
			$qcount++;
			$node_id = $db->lastInsertId();
		}

		foreach ($columns as $column)
		{
			foreach ($words[$column] as $word => $count)
			{
				$res = $db->query("
					SELECT word_id
					FROM {$db_x}indexer_words
					WHERE word_value = ?", array($word)
				);
				$qcount++;
				if ($row = $res->fetch())
				{
					// word already exists
					$word_id = $row['word_id'];
				}
				else
				{
					// create new word
					$db->insert("{$db_x}indexer_words", array('word_value' => $word));
					$qcount++;
					$word_id = $db->lastInsertId();
				}
				$db->query("
					REPLACE INTO {$db_x}indexer_occurrences (node_id, word_id, location, occurrences)
					VALUES (?, ?, ?, ?)", array($node_id, $word_id, $column, $count));
				$qcount++;
			}
		}
		$db->prepare("
			UPDATE {$db_x}indexer_nodes
			SET node_indexed = ?
			WHERE node_id = ?")->execute(array($sys['now_offset'], $node_id));
		$qcount++;
	}
	return $qcount;
}

/**
 * Remove index for given node.
 *
 * @param string $reftype Node type
 * @param int $refid Node reference identifier
 */
function find_remove_index($reftype, $refid = null)
{
	global $db, $db_x, $sources;
	if (!$sources[$reftype]) return;

	$sqlrefid = ($refid != null) ? "AND node_refid = '$refid'" : '';

	$res = $db->query("
		SELECT node_id
		FROM {$db_x}indexer_nodes
		WHERE node_reftype = '$reftype'
		$sqlrefid
	");
	while ($row = $res->fetch())
	{
		$db->query("
			DELETE FROM {$db_x}indexer_occurrences
			WHERE node_id = {$row['node_id']}");
		$db->query("
			DELETE FROM {$db_x}indexer_nodes
			WHERE node_id = {$row['node_id']}");
	}
}

/**
 * Index all nodes, optionally by reftype
 *
 * @param mixed $reftypes Array of reftypes, single reftype as String or all types if empty
 * @return int Query count
 */
function find_index_all($reftypes = array())
{
	global $db, $sources;
	if (!is_array($reftypes)) $reftypes = array($reftypes);
	if (count($reftypes)==0) $reftypes = array_keys($sources);
	$qcount = 0;
	foreach($reftypes as $reftype)
	{
		if (!$sources[$reftype]) continue;
		find_remove_index($reftype);
		$res = $db->query("
			SELECT {$sources[$reftype]['col_id']}
			FROM {$sources[$reftype]['table']}");
		$qcount++;
		while ($row = $res->fetch())
		{
			$qcount += find_build_index($reftype, (int)$row[$sources[$reftype]['col_id']]);
		}
	}
	return $qcount;
}

/* ================================================== */

/**
 * Attempt to transliterate to ASCII.
 * Returns ASCII string if succesful, otherwise returns the original string.
 * Returns lowercase version in both cases.
 *
 * @param string $str
 * @return string
 */
function find_get_ascii($str)
{
	setlocale(LC_ALL, 'en_US.UTF8');
	$str = trim($str);
    $str2 = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    $j = 0;
	$ascii = '';
    for ($i = 0; $i < mb_strlen($str2, 'UTF-8'); $i++)
	{
        $char1 = $str2[$i];
        $char2 = @mb_substr($str, $j++, 1, 'UTF-8');
        if (strstr('`^~\'"', $char1) !== false)
		{
            if ($char1 != $char2)
			{
                --$j;
                continue;
            }
        }
        $ascii .= ($char1 == '?') ? $char2 : $char1;
    }
	$ascii = trim($ascii);
	if (strlen($ascii) >= 3 && strlen($ascii) >= mb_strlen($str, 'UTF-8') * 0.8)
	{
		return strtolower($ascii);
	}
	return mb_strtolower($str);
}

/**
 * Seperate a text into words or phrases.
 *
 * @param string $text Input text
 * @param string $type 
 *	Match type. Can be one of these:
 *	'words', 'phrases', 'required', 'excluded'
 * @return array Words or phrases
 */
function find_get_words($text, $type = 'words')
{
	$text = strip_tags($text);
	$regex = array(
		'words' => "/[^\s\n\r]+/",
		'phrases' => '/"[^"]+"/',
		'required' => "/\+[^\s\n\r]+/",
		'excluded' => "/-[^\s\n\r]+/"
	);
	$words = (preg_match_all($regex[$type], $text, $words) && $words[0]) ? $words[0] : array();
	array_walk($words, 'find_word_prep');
	return array_filter($words, 'find_word_filter');
}

/**
 * Register a new source for indexing and searching.
 *
 * @param string $code
 *	Source node type, usually script name. Use a 'namespace' dot-syntax to group several sources under
 *	one title. Example: "forums.posts" and "forums.topics" would be grouped as "forums" search.
 *	Make sure there's a $L["find_$namespace"] defined in the language file, otherwise it won't
 *	show up in the sources list.
 * @param array $urlparams
 *  Array containing first two parameters for cot_url(). This determines the url for search results.
 *	All columns on the item can be used as variables, like {page_id} or {page_alias}. 
 *  The second parameter can be an array of possible parameter sets. The first 
 *  item where all variables were succesfully replaced will be returned. The last item 
 *  should always contain the value of $col_id.
 *  Example: array('page', array('al={page_alias}', 'id={page_id}'))
 * @param string $table
 *	Database table name.
 * @param mixed $columns
 *	Columns to use, usually type varchar or text.
 *	Can be given as array or comma-separated string.
 * @param string $col_id
 *	Identifying database column name.
 * @param string $col_title
 *	Column name used for retrieving title. Can also be on a foreign table.
 *	In that case, append the table name to the column name with a dot seperator,
 *	and provide an ON clause for joining the tables. Example:
 *	"topics.ft_title ON topics.ft_id = posts.fp_topicid", which results in:
 *	SELECT topics.ft_title FROM posts INNER JOIN topics ON topics.ft_id = posts.fp_topicid
 * @param string $col_date (optional)
 *	Column name containing datetime information.
 *	Can also be a foreign table, using the method described for $col_title.
 * @param boolean $force (optional)
 *	Override existing source.
 * @return boolean
 *	True on success, false otherwise.
 */
function find_register_source(
	$code, $urlparams, $table, $columns, $col_id, $col_title,
	$col_date = null, $force = false
)
{
	global $sources;
	if ($sources[$code] && !$force) return false;
	if (is_string($columns)) $columns = explode(',', $columns);
	$sources[$code] = array(
		'urlparams' => $urlparams,
		'table' => $table,
		'columns' => $columns,
		'col_id' => $col_id,
		'col_title' => $col_title,
		'col_date' => $col_date
	);
	return true;
}

/**
 * Filter function for filtering words
 *
 * @global array $blacklist
 * @param string $str
 * @return bool
 */
function find_word_filter($word)
{
	global $blacklist;
	if (strlen($word) < 3 || strlen($word) > 50) return false;
	if (in_array($word, $blacklist)) return false;
	return true;
}

/**
 * Modify each word in an array to prepare for indexing.
 * Should be used with array_walk().
 *
 * @global array $charblacklist
 * @param string $word Current word in the array iteration
 */
function find_word_prep(&$word)
{
	global $charblacklist;
	$word = find_get_ascii($word);
	$word = str_replace($charblacklist, '', $word);
}

/**
 * Modify text to generate extract.
 *
 * @param string $text
 * @param array $words
 */
function find_get_extract($text, $words, $phrases)
{
	global $cfg, $charblacklist;
	$pieces = array();
	$words = array_diff($words, explode(' ', implode(' ', $phrases)));
	$words = array_merge($words, $phrases);
	$text_length = mb_strlen($text);
	$extract_count = $cfg['find']['extract_count'];
	$extract_length = $cfg['find']['extract_length'];
	foreach($words as $word)
	{
		$wordpos = mb_stripos($text, $word);
		if ($wordpos !== false)
		{
			$start = ($wordpos - $extract_length / 2 > 0) ? $wordpos - $extract_length / 2 : 0;
			$piece = trim(mb_substr($text, $start), implode($charblacklist)." \n\r");
			if ($start > 0)
			{
				$matches = array();
				preg_match("/([.!?]\s|\n|\r)/", $piece, $matches, PREG_OFFSET_CAPTURE);
				if ($matches[0][1])
				{
					$piece = trim(mb_substr($piece, $matches[0][1]), implode($charblacklist)." \n\r");
				}
			}
			$piece = trim(mb_substr($piece, 0, $extract_length), implode($charblacklist)." \n\r");
			$piece = preg_replace("/($word)/i", "<em><strong>\\1</strong></em>", $piece);
			$pieces[] = $piece;
		}
	}
	return implode(' ... ', $pieces);
}

/**
 * Parse a column name in case it references a foreign table.
 *
 * @param string $colname Column name
 * @return array Column name and optional INNER JOIN statement
 */
function find_parse_column($colname)
{
	$join = '';
	$dotpos = strpos($colname, '.');
	$onpos = stripos($colname, ' ON ');
	if ($dotpos !== FALSE && $onpos !== FALSE)
	{
		$foreign = substr($colname, 0, $dotpos);
		$joinclause = substr($colname, $onpos + 4);
		$colname = substr($colname, 0, $onpos);
		$tablejoin = "INNER JOIN $foreign ON $joinclause";
	}
	return array($colname, $tablejoin);
}

?>