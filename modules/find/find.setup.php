<?php
/* ====================
[BEGIN_COT_EXT]
Name=Find
Description=Indexed search
Version=1.0
Date=2011-10-18
Author=Koradhil
Copyright=Copyright (c) Cotonti Team 2008-2011
Notes=BSD License
Auth_guests=R
Lock_guests=W12345A
Auth_members=R
Lock_members=W12345A
Recommends_modules=page,forums
Recommends_plugins=search_pages,search_forums,tags
[END_COT_EXT]

[BEGIN_COT_EXT_CONFIG]
results_per_page=01:string::15:Maximum results per page
min_words_per_page=02:string::10:Minimum word count for page to be indexed
min_word_length=03:string::3:Minimum word length for it to be indexed
extract_count=04:string::3:Maximum number of pieces in extract
extract_length=05:string::120:Maximum length of each piece of extract
cache_ttl=06:string::600:Keep results in cache for * seconds
blacklist=07:radio:Yes,No:No:Enable common word blacklist to reduce DB size
[END_COT_EXT_CONFIG]
==================== */

defined('COT_CODE') or die('Wrong URL');

?>
