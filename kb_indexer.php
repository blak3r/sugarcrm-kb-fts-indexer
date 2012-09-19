<?php
/**
 * Knowledgebase Full Text Search Indexer
 *
 * The KB Module in Sugar 6.5 is still a "legacy" module and therefore the kb contents is currently not indexable
 * out of the box.  This script will index all the contents into the "Description" field which allows
 * 
 * @author Blake Robertson, http://www.blakerobertson.com
 * @copyright Copyright (C) 2002-2005 Free Software Foundation, Inc. http://www.fsf.org/
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @version 1.0 9/18/2012
 *
 * See: http://www.github.com/blak3r/sugarcrm-kb-fts-indexer
 *
 * INSTRUCTIONS:
 *   1) Make sure the KB module is enabled for FTS, then make sure the description field and name fields are enabled.
 *   2) Put this script somewhere (i put it in /custom)
 *   3) Edit script parameters at the top... put path to your sugarconfig.
 *   4) Create a sugar scheduler to call this script nightly to update the index.
 *
 * LIMITATIONS:
 *   - Until a after save logic hook is created, when you save a KB article sugar will update the index record with a blank
 *     description.  So, any KB articles which are modified will not be searchable until this script is rerun.
 *
 *  Improvements:
 *  - Figure out how to do curl in PHP code so that we don't need to do shell_exec
 *  - Create a logic hook to index each document on save
 *  - Refactor out mysql calls to use the sugar db object.
 */
 

//if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point');
//require_once('include/utils.php');
//require_once('include/export_utils.php');
//global $sugar_config;
//global $locale;
//global $current_user;
//require_once('include/entryPoint.php');


	// CONSTANTS 

    $CONFIG_FILE_PATH = "../config.php"; // Path if you put in the /custom folder
    $TEMP_FILE = sys_get_temp_dir() . "/temp_kb.txt"; //just set this to a file that can be written to.  Elastic Search rest api is pretty picky.  Had trouble getting it working when passed to curl -d


	if(is_file($CONFIG_FILE_PATH))
	{
		require_once($CONFIG_FILE_PATH); // TODO probably should also include config_override...
		$db_user_name	= $sugar_config['dbconfig']['db_user_name'];
		$db_password	= $sugar_config['dbconfig']['db_password'];
		$db_name		= $sugar_config['dbconfig']['db_name'];
		$db_host_name	= $sugar_config['dbconfig']['db_host_name'];
        $elastic_index_id = $sugar_config['unique_key'];
        $elastic_host =  $sugar_config['Elastic']['host'];
        $elastic_port = $sugar_config['Elastic']['port'];
	}
	else
	{
        // Fill out these values if you want to manually set these instead of loading from sugar config.php.
        // Set $CONFIG_FILE_PATH to ""
        print "<h3>Unable to find config.php, loading hardcoded settings in script.<h3>";
		$db_user_name	= 'root';
		$db_password	= 'your_password';
		$db_name		= 'sugarcrm';
		$db_host_name	= 'localhost';
        $elastic_index_id = "YOUR-index-id";
        $elastic_host = "127.0.0.1";
        $elastic_port = "9200";
	}

	//--------------[ DO NOT MODIFY ANYTHING BELOW THIS LINE ]------------------//
		
	// Useful if you extend this class to use soap or to invoke from a logic hook...
    //define('sugarEntry', TRUE);
	//chdir('../');
	//require_once('./include/entryPoint.php');
	//$GLOBALS['log'] =& LoggerManager::getLogger('SugarCRM');
	//error_reporting(E_ALL ^ E_NOTICE); //ignore notices
	$is_cli = php_sapi_name() == "cli";

	$con = mysql_connect($db_host_name,$db_user_name,$db_password);
	if (!$con)
	 {
		print_wrapper('Could not connect: ' . mysql_error());
		die('Could not connect: ' . mysql_error());
	 }

	mysql_select_db($db_name, $con);

$taggedQuery=<<<ALLTAGGED
select DISTINCT kbdocuments.id, kbdocuments.kbdocument_name, kbdocuments.status_id,
  kbdocuments.date_entered, kbdocuments.date_modified, kbcontents.kbdocument_body,
  kbdocument_revisions.latest, kbdocument_revisions.revision,
  kbdocuments.team_set_id, kbdocuments.assigned_user_id
FROM kbdocuments
  JOIN kbdocuments_kbtags on kbdocuments.id = kbdocuments_kbtags.kbdocument_id
  JOIN kbdocument_revisions on kbdocument_revisions.kbdocument_id = kbdocuments.id
  JOIN kbcontents on kbcontents.id = kbdocument_revisions.kbcontent_id
WHERE
  kbdocuments.deleted="0" AND
  kbdocuments.status_id = "Published" AND
  kbcontents.deleted="0"
ALLTAGGED;

    $articles = mysql_query_wrapper($taggedQuery);
    print "<H3>" . mysql_num_rows($articles) . " KB Articles Found</h3>";
    print "<BR><b>DB Query:</b><BR><PRE>$taggedQuery</PRE>";

    while($row = mysql_fetch_array($articles))
    {
        $kbdocument_name = htmlentities($row['kbdocument_name']);
        $kbbody =  htmlentities( $row['kbdocument_body']);
        print "<P><B>Indexing: " . $row['kbdocument_name'] . "</b><BR>";
        $post_data =<<<END
        {"index":{"_index":"$elastic_index_id","_type":"KBDocuments","_id":"{$row['id']}"}}
END;

        // Consider removing any html using class like: http://code.google.com/p/iaml/source/browse/trunk/org.openiaml.model.runtime/src/include/html2text/html2text.php
        // This would reduce the amount of data to have to index...
        $indexArr = array();
        $indexArr['kbdocument_name'] = $kbdocument_name;
        $indexArr['module'] = "KBDocuments";
        $indexArr['description'] = $row['kbdocument_body'];
        $indexArr['team_set_id'] = $row['team_set_id'];
        $indexArr["doc_owner"]=    $row['assigned_user_id'];

        $post_data .= "\r\n" . json_encode($indexArr) . "\r\n";

        // Hack: see Improvements at top of file.
        file_put_contents($TEMP_FILE,$post_data);

        $bulkIndexUrl = "http://$elastic_host:$elastic_port/_bulk";

        $cmd = "curl -XPUT $bulkIndexUrl --data-binary @$TEMP_FILE";
        $response  = shell_exec($cmd); // TODO should be replaced with PHP cURL for OnDemand users

        echo "<P><b>SHELL: </b>$cmd<BR><b>RESPONSE:</b>$response<BR<BR>";
        //echo "<b>POST DATA:<BR></B>$post_data<BR>";



        /*
        My Initial attempt to get php cURL support working were unsuccessful... so I just went with calling shell curl
        Someone with experience with cURL could probably get it working pretty easily.


        $bulkIndexUrl = "http://127.0.0.1:9200/_bulk";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $bulkIndexUrl);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
        );
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        print "<BR><BR>Code: " . $info['http_code'] . "<BR>$response<BR><HR>";
        */
    }

    mysql_close($con);

//--------------------[ PRIVATE UTILITIY METHODS ]-------------------------//

// Wrapper method which just prints the error out to standard out if one occurs.
function mysql_query_wrapper( $query ) {
	$temp = mysql_query( $query );
	if( $temp == NULL ) {
		$msg = "MYSQL ERROR: " . mysql_error() . "\nQUERY: $query\n";
		print $msg . "<BR>";
	}
	return $temp;
}