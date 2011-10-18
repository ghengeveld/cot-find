Find: Index-based search for Cotonti
====================================

Find is a module for Cotonti that provides a platform for index-based search.
The system will index words within nodes in the database, meaning it will 
create a database table of words out of content spread accross the database, 
allowing for fast site-wide search, even on websites with thousands of nodes.

A node can effectively be anything, as long as they have a unique identifier 
and a content field consisting of text. Examples are pages, forum posts, 
comments, tags, events, products, users, messages, categories, etc.

The module provides an API for registering nodes to be indexed. Each node must 
have its own plugin to register it with the Find module. Plugins for the Pages 
and Forums modules are provided by default.

Installation
------------

Upload the module and plugins to the correct location in your site. Then go to 
the admin panel and enable the Find module, as well as the node plugins 
('Find in Forums' / 'Find in Pages').

Check the configuration values in Admin -> Config -> Find. The default values 
should be fine in most cases. Now that setup is complete, all existing nodes in
the system must be indexed before you can find anything. Go to the Admin section 
of the Find module (Extensions -> Find -> Administration) and click on 
"Re-index all nodes". This action will take a few minutes, depending on the 
number of nodes (pages, forum posts) in the database.

You can install a search box in any part of the website, simply by creating a 
form with action="index.php?e=find" and method="GET" (we use GET to prevent 
annoying 're-send form data' messages in the browser and allow for Google 
indexing of results pages). The only form field should have name="q". Here's
an example (with CoTemplate callback for a nicer URL):

    <form action="{PHP|cot_url('find')}" method="GET">
	  <input type="text" name="q" />
	  <button type="submit">{PHP.L.Submit}</button>
	</form>