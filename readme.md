### SugarCRM v6.5 Knowledge Base Full Text Search Indexer

In Sugar 6.5, the KB module is still a __legacy__ module.  As a result, the KB article contents aren't indexable.
The contents of the articles are stored in a different database table and don't appear as a field you can select in studio.
This script is intended to be stop gap solution to allow users to perform full text searches against the KB Articles
until KB module indexing is built in to sugar.

### General
 * Author: Blake Robertson, <http://www.blakerobertson.com>
 * License is GPLv3.


#### How it works
Basically what this script does is does a database query to get all the KB articles.  Then, it posts the kb article contents
into the elastic search engine via rest calls.  In order to get the results to be appear, we maps the KB Article contents into
the description field (which is not used - but conveniently can be indexed in studio).  This script should be called as a Scheduler job to periodically
update the index or can be run manually.
  
### Instructions

    1) Make sure the KB module is enabled for FTS, then make sure the description field and name fields are enabled.
    2) Put this script somewhere (i put it in /custom)
    3) Edit script parameters at the top.
    4) Create a sugar scheduler to call this script nightly to update the index.  If you put it in custom, the url would be
       http://<your_hostname>/custom/kb_indexer.php
 
### Limitations

Keep in mind that this script is intended to be a temporary workaround until KB indexing support is built in to sugar.
Here are some suggestions of ways it could be improved but didn't seem worth the time/effort.  If you're a developer and you make any of these changes, please
submit a pull request so we can all benefit.

 1. Any new or modified KB Articles will be unavailable for searching until this script is run again.  A potential solution
 to this would be to create an on save logic hook.   When you save a KB article sugar will push the article to elastic search, but
 since the description field will be blank when you save it... it's not searchable.  Workaround: run this script via scheduler every couple hours.
 A better solution would be to create an "on save" logic hook and update the index for the particular bean.
 2. If you run sugar on a windows box, you will need to have something like cygwin or git installed so you have a curl binary on your path.
 3. Refactor out mysql calls and use the Sugar $GLOBAL->db object instead.  This way MSSQL users could also use this script.

 #### Other Improvement Ideas

 * Create an GET Param option ?fast which would only fetch records from the database that were updated in the last say hour and only index those.  Then, you could call this script much more frequently without worrying about performance issues.