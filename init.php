<?php
class Auto_Embed_Original extends Plugin {
	private $host;

	function init($host) {
		$this->host = $host;
		$host->add_hook($host::HOOK_HOTKEY_MAP, $this);
                $host->add_hook($host::HOOK_HOTKEY_INFO, $this);
                $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
                $host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
                $host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);

		$result=db_query( 'SELECT 1 FROM plugin_auto_embed_original_mobilizer' );
		if (db_num_rows($result) == 0) {

db_query("
CREATE TABLE IF NOT EXISTS `plugin_auto_embed_original_mobilizer` (
  `id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `url` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
");	

db_query("INSERT INTO `plugin_auto_embed_original_mobilizer` (`id`, `description`, `url`) VALUES
(0, 'feed content', ''),
(1, 'full original article', '%s'),
(3, 'instapaper mobilized view', 'http://www.instapaper.com/m?u=%s');
");

db_query("CREATE TABLE IF NOT EXISTS `plugin_auto_embed_original_feeds` (
  `id` int(11) NOT NULL,
  `owner_uid` int(11) NOT NULL,
  `mobilizer_id` int(11) NOT NULL,
  PRIMARY KEY (`id`,`owner_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
");
		}


	}

	function about() {
		return array(1.1,
			"Try to automatically display original article content inside tt-rss (if feed is configured)",
			"macfly");
		}

	function api_version() {
		return 2;
	}

	function get_js() {
		return file_get_contents(dirname(__FILE__) . "/init.js");
		}

	function get_css() {
		return file_get_contents(dirname(__FILE__) . "/init.css");
		}

        function hook_hotkey_map($hotkeys) {
        	$hotkeys['*v']           = "auto_embed_original";
        	return $hotkeys;
        	}

	function hook_hotkey_info($hotkeys) {
		$offset = 1 + array_search('"auto_embed_original"', array_keys($hotkeys[__('Article')]));
		$hotkeys[__('Article')] =
		array_slice($hotkeys[__('Article')], 0, $offset, true) +
		array('"auto_embed_original"' => __('automatically embed original Article Content')) +
		array_slice($hotkeys[__('Article')], $offset, NULL, true);
		return $hotkeys;
		}

    
    	function hook_article_button($line) {
		$id = $line["id"];

		$rv = "<img src=\"plugins/auto_embed_original/embed.png\"
			class='tagsPic' style=\"cursor : pointer\"
			onclick=\"autoEmbedOriginalArticle($id,false)\"
			title='".__('Toggle embed original')."' height=\"20\" width=\"20\" >";

		$rv .= "<img src=\"plugins/auto_embed_original/embed_config.png\"
			class='tagsPic' style=\"cursor : pointer\"
			onclick=\"configAutoEmbedOriginal($id)\"
			title='".__('configure embed original')."' height=\"20\" width=\"20\" >";

		return $rv;
	}

	function hook_prefs_edit_feed($feed_id) {
		print "<div class=\"dlgSec\">".__("Feed content")."</div>";
		print "<div class=\"dlgSecCont\">";
		print "<hr/>";

		$contPref   = db_query("SELECT mobilizer_id from plugin_auto_embed_original_feeds where id = '$feed_id' AND
				owner_uid = " . $_SESSION["uid"]);
		$mobilizer_id=0;
		if (db_num_rows($contPref) != 0) {
			$mobilizer_id = db_fetch_result($contPref, 0, "mobilizer_id");
		}
		
		$contResult = db_query("SELECT id,description from plugin_auto_embed_original_mobilizer order by id");

		while ($line = db_fetch_assoc($contResult)) {
			$mobilizer_ids[$line["id"]]=$line["description"];
			}

		print_select_hash("mobilizer_id", $mobilizer_id, $mobilizer_ids, 'dojoType="dijit.form.Select"');
		print "</div>";
	}		


	function hook_prefs_save_feed($feed_id) {
		$mobilizer_id = (int) db_escape_string($_POST["mobilizer_id"]);
		$result = db_query("DELETE FROM plugin_auto_embed_original_feeds 
			WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);
	
		$result = db_query("INSERT INTO plugin_auto_embed_original_feeds
			(id,owner_uid,mobilizer_id)
			VALUES ('$feed_id', '".$_SESSION["uid"]."', '$mobilizer_id')");
		}

	function getUrl() {
		$id = db_escape_string($_REQUEST['id']);
		$mobilizer_id = db_escape_string($_REQUEST['mobilizer_id']);

		$result1 = db_query("SELECT link
			FROM ttrss_entries, ttrss_user_entries
			WHERE id = '$id' AND ref_id = id AND owner_uid = " .$_SESSION['uid']);

		$url = "";

		if (db_num_rows($result1) != 0) {
			$url = db_fetch_result($result1, 0, "link");
			}

		if ($mobilizer_id) {
			$result2 = db_query("SELECT url
				FROM  ttrss_user_entries ue, plugin_auto_embed_original_mobilizer pm
				WHERE ue.ref_id = '$id' and ue.owner_uid = " . $_SESSION['uid'] ." 
				and pm.id = '$mobilizer_id' ");
			} else {
			$result2 = db_query("SELECT url
				FROM  ttrss_user_entries ue, plugin_auto_embed_original_feeds pf, plugin_auto_embed_original_mobilizer pm
				WHERE ue.ref_id = '$id' and ue.owner_uid = " . $_SESSION['uid'] ." 
				and ue.feed_id = pf.id 
				and pf.owner_uid = ue.owner_uid
				and pf.mobilizer_id = pm.id");
			}											

		$mobilizer_url = $url;

		if (db_num_rows($result2) != 0) {
			$mobilizer_url = db_fetch_result($result2, 0, "url");
			if ($mobilizer_url <> "") { # we got an configured url for the feed, lets do search and replace
				$mobilizer_url=str_replace("%s",$url,$mobilizer_url);
				} else {
				$mobilizer_url = $url;
				}
			}

		print json_encode(array("url" => $mobilizer_url, "id" => $id));
	}

	function feedAutoloadContent() {
		$id = db_escape_string($_REQUEST['id']);
		$result = db_query("SELECT count(1) enabled
				FROM  ttrss_user_entries ue, plugin_auto_embed_original_feeds pf, plugin_auto_embed_original_mobilizer pm
				WHERE ue.ref_id = '$id' and ue.owner_uid = " . $_SESSION['uid'] ." 
				and ue.feed_id = pf.id 
				and pf.owner_uid = ue.owner_uid
				and pf.mobilizer_id = pm.id
				and pf.mobilizer_id <> 0");
		$enabled="0";
										
		if (db_num_rows($result) != 0)
			$enabled = db_fetch_result($result, 0, "enabled");

		if ($enabled) print json_encode(array("autoload" => $enabled, "id" => $id));
	}


	function get_config() {
		$article_id = db_escape_string($_REQUEST['param']);

		$result = db_query("SELECT feed_id from ttrss_user_entries where ref_id = '$article_id' and owner_uid = " . $_SESSION["uid"]);
		$feed_id=0;
		if (db_num_rows($result) != 0)
			$feed_id = db_fetch_result($result, 0, "feed_id");

		$result= db_query("SELECT title FROM  ttrss_feeds f WHERE f.id = '$feed_id' and f.owner_uid = " . $_SESSION["uid"]);
		$feed_name="";
		if (db_num_rows($result) != 0)
			$feed_name = db_fetch_result($result, 0, "title");


		$result = db_query("SELECT mobilizer_id from plugin_auto_embed_original_feeds where id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);
		$mobilizer_id=0;
		if (db_num_rows($result) != 0) {
			$mobilizer_id = db_fetch_result($result, 0, "mobilizer_id");
		}
		
		$result = db_query("SELECT id,description from plugin_auto_embed_original_mobilizer order by id");

		while ($line = db_fetch_assoc($result)) {
			$mobilizer_ids[$line["id"]]=$line["description"];
			}

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"auto_embed_original\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save_config\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"feed_id\" value=\"$feed_id\">";
		print "<table width='100%'><tr><td>";
		print __('Feed Name :');
		print "</td><td>";
		print "<input dojoType=\"dijit.form.TextBox\" disabled=\"1\" style=\"width : 30em;\" value=\"$feed_name\">";
		print "</td></tr><tr><td>";
		print __('Display Article Content as');
		print "</td><td>";
		print_select_hash("mobilizer_id", $mobilizer_id, $mobilizer_ids, 'dojoType="dijit.form.Select"');
		print "</td></tr></table>";
		print "<div class='dlgButtons'>";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('configAutoEmbedOriginalDlg').execute()\">".__('Save')."</button> ";
		print "<button dojoType=\"dijit.form.Button\" onclick=\"return dijit.byId('configAutoEmbedOriginalDlg').hide()\">".__('Cancel')."</button>";
		print "</div>";
	}

	function save_config() {
		$feed_id = db_escape_string($_REQUEST['feed_id']);
		$mobilizer_id = db_escape_string($_REQUEST['mobilizer_id']);

		$result = db_query("DELETE FROM plugin_auto_embed_original_feeds 
		WHERE id = '$feed_id' AND owner_uid = " . $_SESSION["uid"]);

		$result = db_query("INSERT INTO plugin_auto_embed_original_feeds
		(id,owner_uid,mobilizer_id)
		VALUES ('$feed_id', '".$_SESSION["uid"]."', '$mobilizer_id')");

		db_query("COMMIT");

		$reply = array();
		$reply['message'] = "UPDATE_COUNTERS";
		print json_encode($reply);
		return;
		} 
}
?>
