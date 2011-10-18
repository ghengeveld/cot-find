<?php

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('find', 'module');

find_remove_index('forums.topics');
find_remove_index('forums.posts');

?>