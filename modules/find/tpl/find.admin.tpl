<!-- BEGIN: MAIN -->

<h2>{PHP.L.indexer_title}</h2>

<!-- BEGIN: INDEXING_DONE -->
<div class="done">
	<h4>{PHP.L.indexer_complete}</h4>
	<p>{EXECUTED}</p>
</div>
<!-- END: INDEXING_DONE -->

<p>&raquo; <a href="{INDEXALL_URL}">{PHP.L.indexer_reindex_all}</a> ({PHP.L.indexer_reindex_all_note})</p>

<h2>{PHP.L.indexer_statistics}</h2>

<div class="column">
	<h3>Counts</h3>
	<table>
		<tr>
			<td style="text-align:right;">{NODES_COUNT}</td>
			<td>&nbsp;{PHP.L.indexer_nodes}</td>
		</tr>
		<tr>
			<td style="text-align:right;">{WORDS_COUNT}</td>
			<td>&nbsp;{PHP.L.indexer_words}</td>
		</tr>
		<tr>
			<td style="text-align:right;">{OCCURRENCES_COUNT}</td>
			<td>&nbsp;{PHP.L.indexer_occurrences}</td>
		</tr>
	</table>
</div>
<div class="column">
	<h3>Top 5 words</h3>
	<ol>
		<!-- BEGIN: TOP5 -->
		<li>{WORD} ({COUNT})</li>
		<!-- END: TOP5 -->
	</ol>
</div>

<!-- END: MAIN -->