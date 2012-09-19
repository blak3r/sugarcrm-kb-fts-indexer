KB FTS Indexer

The KB Module in Sugar 6.5 is still a "legacy" module and therefore the kb contents is currently not indexable
out of the box.  This script will index all the contents into the "Description" field which allows
 
@author Blake Robertson, http://www.blakerobertson.com
@copyright Copyright (C) 2002-2005 Free Software Foundation, Inc. http://www.fsf.org/
@license http://www.gnu.org/licenses/gpl.html GNU General Public License
@version 1.0 9/18/2012

  
  INSTRUCTIONS:
    1) Make sure the KB module is enabled for FTS, then make sure the description field and name fields are enabled.
    2) Put this script somewhere (i put it in /custom)
    3) Edit script parameters at the top... put path to your sugarconfig.
    4) Create a sugar scheduler to call this script nightly to update the index.
 
  LIMITATIONS:
    - Until a after save logic hook is created, when you save a KB article sugar will update the index record with a blank
      description.  So, any KB articles which are modified will not be searchable until this script is rerun.
 
   Improvements:
   - Figure out how to do curl in PHP code so that we don't need to do shell_exec
   - Figure out how to get the elastic index id from sugarconfig.
   - Create a logic hoook to index each document on save
   - Refactor out mysql calls to use the sugar db object.
   - Respect team id's and owner id (currently hardcoding to 1 == global.."