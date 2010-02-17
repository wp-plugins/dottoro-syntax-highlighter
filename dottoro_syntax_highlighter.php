<?php

/*
	Plugin Name: Dottoro Syntax Highlighter
	Plugin URI: http://tools.dottoro.com/services/highlighter/plugins/
	Description: Syntax Highlighter for HTML, CSS, JavaScript and XML languages
	Version: 1.0
	Author: Dottoro.com
	Author URI: http://tools.dottoro.com
*/

/*
	Copyright 2010  Dottoro.com  (email : info@dottoro.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$dr_hl_plugin_url = WP_PLUGIN_URL . '/' . "dottoro-syntax-highlighter/"; 

// Add styles and scripts to header
function dr_highlighter_Head_Includes ()
{
	global $dr_hl_plugin_url;
// includes must be placed at the plugins subfolder
	echo '<link rel="stylesheet" href="' . $dr_hl_plugin_url . 'public/dottoro-highlighter-v2-min.css" type="text/css" />';
	echo '<script type="text/javascript" src="' . $dr_hl_plugin_url . 'public/dottoro-highlighter-v2-min.js"></script>';
	$settings = dr_highlighter_Get_Base_Settings ();
	if ($settings['code_theme'] != "code_default") {
		echo '<link rel="stylesheet" href="' . $dr_hl_plugin_url . 'public/themes/' . $settings['code_theme'] . '.css" type="text/css" />';
	}
	if ($settings['frame_theme'] != "frame_default") {
		echo '<link rel="stylesheet" href="' . $dr_hl_plugin_url . 'public/themes/' . $settings['frame_theme'] . '.css" type="text/css" />';
	}
}



/*=============================
*   Wordpress editor addons   *
=============================*/


// Adds dr_syntax tag to the valid elements list in TinyMCE
function dr_highlighter_tinyMCE_allow_custom_attr ($init)
{
    $init['extended_valid_elements'] .= ',pre[drsyntax]';
    return $init;
}

// TinyMCE Editor Adjustments (allow access before TinyMCE initialization)
add_filter( 'tiny_mce_before_init', 'dr_highlighter_tinyMCE_allow_custom_attr' );


function dr_highlighter_addbuttons ()
{
	if (!current_user_can('edit_posts') && !current_user_can('edit_pages') )
		return;

	// Add button to TinyMCE
	if (get_user_option('rich_editing') == 'true') {
		add_filter("mce_external_plugins", "add_action_to_tinymce_button");
		add_filter('mce_buttons', 'register_dr_highlighter_button');
	}
}
 
// Register button for post editing bar
function register_dr_highlighter_button ($buttons)
{
   array_push($buttons, "separator", "dr_syntax_highlighter");
   return $buttons;
}

// Add action to TinyMCE button
function add_action_to_tinymce_button ($plugin_array)
{
	global $dr_hl_plugin_url;
	$plugin_array['dr_syntax_highlighter'] = $dr_hl_plugin_url . 'dr_syntax_highlighter_tinymce.js';
	return $plugin_array;
}

// Initilize buttons
add_action( 'init', 'dr_highlighter_addbuttons' );

// Include necessary files
function dr_highlighter_addfunction ()
{
	global $dr_hl_plugin_url;
	echo '<script>var dr_highlighter_plugin_dir = "' . $dr_hl_plugin_url . '";</script>';

	$jqueryStyleFile = $dr_hl_plugin_url . 'jquery-ui-1.8rc1.custom.css';

	wp_enqueue_style ('dr_syntax_highlighter_style', $jqueryStyleFile, array( ), get_bloginfo('version'), 'all');
	wp_print_styles( array( 'dr_syntax_highlighter_style' ) );

	if (function_exists('wp_enqueue_script')) {
		wp_enqueue_script ('jquery');
		wp_enqueue_script ('jquery-ui-core');
		wp_enqueue_script ('jquery-ui-draggable');
		wp_enqueue_script ('jquery-ui-dialog');

		wp_enqueue_script ('dr_syntax_highlighter', $dr_hl_plugin_url . 'dr_syntax_highlighter_quicktags.js', array('quicktags'));
	}
}

// load quicktag javascript
if ( in_array( $pagenow , array('post.php', 'post-new.php', 'page.php', 'page-new.php') ) ) {
	add_action('admin_print_scripts', 'dr_highlighter_addfunction');
}

/*============================
*   Colorize and save code   *
============================*/

function dr_highlighter_on_after_save($postID)
{
	if($parent_id = wp_is_post_revision($postID)){
		$postID = $parent_id;
	}

	if ($postID)
	{
		dr_highlighter_Create_DBTable ();
		dr_highlighter_Save_Code_Blocks ($postID);
	}
}

function dr_highlighter_Save_Code_Blocks ($postID)
{
	$base_settings = dr_highlighter_Get_Base_Settings ();

	global $wpdb;
	$table_name = $wpdb->prefix . "posts";
	$row = $wpdb->get_row("SELECT post_content FROM " . $table_name . " WHERE ID = $postID", OBJECT);
	$content = $row->post_content;

	$pos = strpos($content, '<pre drsyntax');
	$postNr = 0;
	while ($pos !== false) {
		$endPos = dr_highlighter_Merge_Settings ($content, $pos, $base_settings, $postID, $postNr);
		if ($endPos === false) {
			break;
		}
		$postNr++;
		$pos = strpos($content, '<pre drsyntax', $endPos);
	}
}

function dr_highlighter_Merge_Settings ($content, $pos, $base_settings, $postID, $postNr)
{
	$attrValueStart = strpos($content, '"', $pos) + 1;
	$attrValueEnd = strpos($content, '"', $attrValueStart);
	$code_settings = substr ($content, $attrValueStart, $attrValueEnd - $attrValueStart);

	$base_settings = dr_highlighter_Get_Code_Settings ($base_settings, $code_settings);

	$preOpenEnd = strpos ($content, ">", $pos) + 1;
	$preCloseStart = strpos ($content, "</pre>", $preOpenEnd);

	if ($preCloseStart != false) {
		$source = substr ($content, $preOpenEnd, $preCloseStart - $preOpenEnd);

		$_codeID = $postID . "#" . $postNr;
		if (dr_highlighter_Generation_Is_Needed ($_codeID, $source, $base_settings)) {
			$highlighted_code = dr_highlighter_Generate_Code ($source, $base_settings);
			if ($highlighted_code) {
				$highlighted_code = dr_highlighter_Set_Width_Height ($highlighted_code, $base_settings);
				dr_highlighter_Insert_Into_DBTable ($highlighted_code, $_codeID, $source, $base_settings);
			}
		}
	}
	return $preCloseStart;
}

function dr_highlighter_Get_Code_Settings ($base_settings, $code_settings)
{
	$semiColon = 0;
	$colon = strpos ($code_settings, ":");
	while ($colon) {
		$key = substr ($code_settings, $semiColon, $colon - $semiColon);
		$semiColon = strpos ($code_settings, ";", $colon);
		$colon++;
		if ($semiColon) {
			$value = substr ($code_settings, $colon, $semiColon - $colon);
			$semiColon++;
			$colon = strpos ($code_settings, ":", $semiColon);
			$base_settings[$key] = $value;
		} else {
			$value = substr ($code_settings, $colon);
			$base_settings[$key] = $value;
			break;
		}
	}
	return $base_settings;
}

function dr_highlighter_Generation_Is_Needed ($_codeID, $source, $base_settings)
{
	$settings_str = dr_highlighter_Array_To_String ($base_settings);

	global $wpdb;
	$table_name = $wpdb->prefix . "dr_sh_codes";

	$row = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE codeID = '$_codeID'");
	if ($row) {
		 if ($row->source == $source && $row->settings == $settings_str) {
			 return false;
		 }
	}
	return true;
}

function dr_highlighter_Generate_Code ($source, $base_settings)
{

	$client = new SoapClient("http://tools.dottoro.com/axis2/services/codeTools?wsdl");

	$source = str_replace('&gt;', '>', $source);
	$source = str_replace('&lt;', '<', $source);

	$request = array (
			'key' => "63e9383394bc50ca8db24f9e8404d44e",
			'lang' => $base_settings["lang"],
			'compress' => $base_settings["compress"] == "on"? true : false,
			'lineNumbers' => $base_settings["lineNumbers"] == "on"? true : false,
			'helpLinks' => $base_settings["helpLinks"] == "on"? true : false,
			'nofollow' => $base_settings["nofollow"] == "on"? true : false,
			'tabSize' => $base_settings["tabSize"],
			'viewPlainButton' => ($base_settings['viewPlainButtonCBox'] == "on")? $base_settings["viewPlainButton"] : "",
			'copyButton' => ($base_settings['copyButtonCBox'] == "on")? $base_settings["copyButton"] : "",
			'printButton' => ($base_settings['printButtonCBox'] == "on")? $base_settings["printButton"] : "",
			'source' => $source
			);

	try {
		$response = $client->Highlight ($request);
	}
	catch (SoapFault $fault) {
			// An error occurred
		echo ("Error: " . $fault->faultstring);
		return false;
	};
		/* Ok, the source property of the response contains 
		   the source of the highlighted code */
	return $response->source;
}


function dr_highlighter_Set_Width_Height ($highlighted_code, $base_settings)
{
	$w = $base_settings["width"];
	$h = $base_settings["height"];
	$str = 'class="dr_hl_codeContainer"';
	if ($w || $h) {
		$style = ' style="';
		if ($w) {
			$style .= 'width:' . $w . ';';
		}
		if ($h) {
			$style .= 'height:' . $h . ';';
		}
		$style .= '"';
		
		$pos = strpos ($highlighted_code, $str) + strlen($str);
		$highlighted_code = substr ($highlighted_code, 0, $pos) . $style . substr ($highlighted_code, $pos);

	}
	return $highlighted_code;
}

function dr_highlighter_Insert_Into_DBTable ($highlighted_code, $_codeID, $source, $settings)
{
	global $wpdb;
	$table_name = $wpdb->prefix . "dr_sh_codes";
	$settings = dr_highlighter_Array_To_String ($settings);

	$row = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE codeID = '$_codeID'");
	if ($row) {
		$wpdb->query("UPDATE " . $table_name . " SET highlighted='" . addslashes($highlighted_code) . "', 
														source='" . addslashes($source) . "', 
														settings='" . addslashes($settings) . "' 
														WHERE codeID='" . $_codeID . "'");
	} else {
		$wpdb->query("INSERT INTO " . $table_name . " SET highlighted='" . addslashes($highlighted_code) . "', 
														source='" . addslashes($source) . "', 
														settings='" . addslashes($settings) . "', 
														codeID='" . $_codeID . "'");
	}
}

// create the datatable if not created yet
function dr_highlighter_Create_DBTable ()
{
	global $wpdb;
	$table_name = $wpdb->prefix . "dr_sh_codes";
	if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			  codeID varchar(30),
			  source MEDIUMTEXT,
			  settings varchar(300),
			  highlighted MEDIUMTEXT,
			  UNIQUE KEY codeID (codeID)
			);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}

function dr_highlighter_Get_Base_Settings ()
{
	if (get_option ('dr_highlighter_settings')){
		return unserialize (get_option('dr_highlighter_settings'));
	}
	$settings = array ();
	$settings['lineNumbers'] = "on";
	$settings['replaceTabsCBox'] = "on";
	$settings['tabSize'] = "4";
	$settings['viewPlainButtonCBox'] = "on";
	$settings['viewPlainButton'] = "View Plain";
	$settings['copyButtonCBox'] = "on";
	$settings['copyButton'] = "Copy Code";
	$settings['printButtonCBox'] = "on";
	$settings['printButton'] = "Print";
	$settings['helpLinks'] = "on";
	$settings['nofollow'] = "off";
	$settings['compress'] = "off";
	$settings['code_theme'] = "code_default";
	$settings['frame_theme'] = "frame_default";
	$settings['drlinktype'] = "link";
	return $settings;
}

function dr_highlighter_Array_To_String ($assoc, $inglue = ':', $sep = ';')
{
	$return = '';
	foreach ($assoc as $key => $value) 
	{
		$return .= $sep . $key . $inglue . $value;
	}
	return substr($return,strlen($sep));
}




/*==================================
*   change content on get_content  *
==================================*/

function dr_highlighter_Replace_Content ($content)
{
	if ($content && $content != "") {
		global $post;
		$postID =  $post->ID;

		$start = strpos($content, '<pre drsyntax');
		$postNr = 0;
		while ($start !== false) {
			$_codeID = $postID . "#" . $postNr;
			$end = dr_highlighter_Change_Code_Block ($content, $start, $_codeID);
			if ($end === false) {
				break;
			}
			$postNr++;
			$start = strpos($content, '<pre drsyntax', $end);
		}
	}
	return $content;
}

function dr_highlighter_Change_Code_Block (&$content, $start, $_codeID)
{
	$endStr = '</pre>';
	$end = strpos($content, $endStr, $start) + strlen ($endStr);

	if ($end !== false) {
		global $wpdb;
		$table_name = $wpdb->prefix . "dr_sh_codes";

		$row = $wpdb->get_row("SELECT * FROM " . $table_name . " WHERE codeID = '$_codeID'");
		if ($row) {
			$highlighted_code = $row->highlighted;
			dr_highlighter_Change_DR_Link ($highlighted_code, $row->settings);
			$content = substr ($content, 0, $start) . $highlighted_code . substr ($content, $end);
		}
	}
	return $end;
}

function dr_highlighter_Change_DR_Link (&$highlighted_code, $code_settings)
{
	$base_settings = dr_highlighter_Get_Base_Settings ();
	$base_settings = dr_highlighter_Get_Code_Settings ($base_settings, $code_settings);

	if ($base_settings["drlinktype"] == "link") {
		return;
	}

	$pos = strrpos ($highlighted_code, "dr_hl_trademarkCell", 0);

	$aOpenStart = strpos ($highlighted_code, "<a", $pos);
	$aOpenEnd = strpos ($highlighted_code, ">", $aOpenStart);
	$aCloseStart = strpos ($highlighted_code, "</a>", $aOpenEnd);

	if ($base_settings["drlinktype"] == "text") {
		$highlighted_code = substr ($highlighted_code, 0, $aCloseStart) . "</span>" . substr ($highlighted_code, $aCloseStart + 4);
		$highlighted_code = substr ($highlighted_code, 0, $aOpenStart) . "<span title='Dottoro Syntax Highlighter'>" . substr ($highlighted_code, $aOpenEnd + 1);
	}

	if ($base_settings["drlinktype"] == "none") {
		$highlighted_code = substr ($highlighted_code, 0, $aOpenStart) . substr ($highlighted_code, $aCloseStart + 11);
	}
}

/*==========================
*   Wordpress adin panel   *
==========================*/

// options panel settings init
function dr_highlighter_options_panel()
{

	if (isset ($_POST['dr_highlighter_submit'])) {
		$settings = array();

		$settings['lineNumbers'] = $_POST['lineNumbers']? "on" : "off";
		$settings['replaceTabsCBox'] = $_POST['replaceTabsCBox']? "on" : "off";
		$settings['tabSize'] = $_POST['tabSize']? stripslashes($_POST['tabSize']) : $settings['tabSize'];
		$settings['viewPlainButtonCBox'] = $_POST['viewPlainButtonCBox']? "on" : "off";
		$settings['viewPlainButton'] = stripslashes($_POST['viewPlainButton']);
		$settings['copyButtonCBox'] = $_POST['copyButtonCBox']? "on" : "off";
		$settings['copyButton'] = stripslashes($_POST['copyButton']);
		$settings['printButtonCBox'] = $_POST['printButtonCBox']? "on" : "off";
		$settings['printButton'] = stripslashes($_POST['printButton']);
		$settings['helpLinks'] = $_POST['helpLinks']? "on" : "off";
		$settings['nofollow'] = $_POST['nofollow']? "on" : "off";
		$settings['compress'] = $_POST['compress']? "on" : "off";
		$settings['code_theme'] = $_POST['code_theme'];
		$settings['frame_theme'] = $_POST['frame_theme'];
		$settings['drlinktype'] = $_POST['drlinktype'];
		$setting_str = serialize($settings);
		if (get_option('dr_highlighter_settings'))
			update_option("dr_highlighter_settings", $setting_str);
		else
			add_option("dr_highlighter_settings", $setting_str, '', 'yes');
	}

	$settings = dr_highlighter_Get_Base_Settings ();

	dr_highlighter_show_options ($settings);
}

// Options panel content
function dr_highlighter_show_options($settings)
{	
	global $dr_hl_plugin_url;
?>
	
	<script type="text/javascript">
		function OnCheckStateChanged (input, id) {
			document.getElementById (id + "_Group").style.display = input.checked ? "" : "none";
		}
		function LabelForImg (id, idx) {
			var inputs = document.getElementsByName (id);
			inputs[idx].checked = true;
		}
	</script>
	<div class="wrap">
		<h2>Dottoro Sytax Highlighter Settings</h2>

		<form method="post" action="">
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<td colspan="2"><h3>General</h3></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="lineNumbers">Display Line Numbers</label></th>
						<td>
							<input type="checkbox" name="lineNumbers" id="lineNumbers" 
							<?
							if($settings['lineNumbers'] == "on"){
									echo "checked";
							} 
							?>
							/>
							<div>
								<small>Check this if you want to display line numbers at the left side of your code snippets.</small>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="replaceTabsCBox">Replace tabs with spaces</label></th>
						<td>
							<input type="checkbox" name="replaceTabsCBox" id="replaceTabsCBox" onclick="OnCheckStateChanged (this, 'tabSize')" 
								<?
								if($settings['replaceTabsCBox'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to replace all tabs with spaces in your code snippets.</small>
							</div>
							<div id='tabSize_Group' style='background:#f1f1f1;padding-left:20px; margin-top:8px; font-size:12px; <? if($settings['replaceTabsCBox'] != "on"){echo ("display:none");} ?>'>
								Tab and indent size(1-20):
								<input type='text' name='tabSize' id='tabSize' value='<?php echo($settings['tabSize']); ?>' size='3'/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="viewPlainButtonCBox">View Plain button</label></th>
						<td>
							<input type="checkbox" name="viewPlainButtonCBox" id="viewPlainButtonCBox" onclick="OnCheckStateChanged (this, 'viewPlainButtonTitle')"
								<?
								if($settings['viewPlainButtonCBox'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to display a 'View Plain' button at the bottom footer of your code snippets.</small>
							</div>
							<div id='viewPlainButtonTitle_Group' style='background:#f1f1f1;padding-left:20px; margin-top:8px; font-size:12px; <? if($settings['viewPlainButtonCBox'] != "on"){echo ("display:none");} ?>'>
								Title of the button:
								<input type='text' name='viewPlainButton' id='viewPlainButton' value='<?php echo($settings['viewPlainButton']); ?>' size='20'/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="copyButtonCBox">Copy to Clipboard button</label></th>
						<td>
							<input type="checkbox" name="copyButtonCBox" id="copyButtonCBox" onclick="OnCheckStateChanged (this, 'copyButtonTitle')"
								<?
								if($settings['copyButtonCBox'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to display a 'Copy to Clipboard' button at the bottom footer of your code snippets.</small>
							</div>
							<div id='copyButtonTitle_Group' style='background:#f1f1f1;padding-left:20px; margin-top:8px; font-size:12px; <? if($settings['copyButtonCBox'] != "on"){echo ("display:none");} ?>'>
								Title of the button:
								<input type='text' name='copyButton' id='copyButton' value='<?php echo($settings['copyButton']); ?>' size='20'/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="printButtonCBox">Print button</label></th>
						<td>
							<input type="checkbox" name="printButtonCBox" id="printButtonCBox" onclick="OnCheckStateChanged (this, 'printButtonTitle')"
								<?
								if($settings['printButtonCBox'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to display a 'Print' button at the bottom footer of your code snippets.</small>
							</div>
							<div id='printButtonTitle_Group' style='background:#f1f1f1;padding-left:20px; margin-top:8px; font-size:12px; <? if($settings['printButtonCBox'] != "on"){echo ("display:none");} ?>'>
								Title of the button:
								<input type='text' name='printButton' id='printButton' value='<?php echo($settings['printButton']); ?>' size='20'/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="helpLinks">Help links</label></th>
						<td>
							<input type="checkbox" name="helpLinks" id="helpLinks"
								<?
								if($settings['helpLinks'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to make it easy for your visitors to get more information about the language elements used in your code snippets.</small>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="nofollow">Nofollow links</label></th>
						<td>
							<input type="checkbox" name="nofollow" id="nofollow"
								<?
								if($settings['nofollow'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you want to set rel="nofollow" for all anchor elements.</small>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label>Dottoro Link</label></th>
						<td>
							<table>
								<tr>
									<td><input type="radio" name="drlinktype" id="drlinktype" value="link" <? if($settings['drlinktype'] == "link"){echo "checked";} ?> /></td>
									<td> - Show as link</td>
								</tr>
								<tr>
									<td><input type="radio" name="drlinktype" id="drlinktype" value="text" <? if($settings['drlinktype'] == "text"){echo "checked";} ?> /></td>
									<td> - Show as text</td>
								</tr>
								<tr>
									<td><input type="radio" name="drlinktype" id="drlinktype" value="none" <? if($settings['drlinktype'] == "none"){echo "checked";} ?> /></td>
									<td> - Do not display</td>
								</tr>
							</table>
							<div>
								<small>Change the 'D.r' copyright notice display type.</small>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="compress">Compress source code</label></th>
						<td>
							<input type="checkbox" name="compress" id="compress"
								<?
								if($settings['compress'] == "on"){
										echo "checked";
								} 
								?>
							/>
							<div>
								<small>Check this if you often use extra large code snippets.
									Since the size of the highlighted code can be much more larger than the original source code, the highlighter provides a compression mechanism.
									The compressed code is optimized for search engines, so your code appears in search engines result pages (SERPs) in its original form.
								</small>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<td colspan="2"><h3>Themes</h3></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="compress">Code Themes</label></th>
						<td style="white-space:normal;">
							<div style="float:left; margin:10px;">
								<input type="radio" name="code_theme" id="code_theme" value="code_default" <? if($settings['code_theme']=='code_default'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/code_default.jpg");?>" alt="default" onclick="LabelForImg ('code_theme', 0);"/>
							</div>
							<div style="float:left; margin:10px;">
								<input type="radio" name="code_theme" id="code_theme" value="code_black" <? if($settings['code_theme']=='code_black'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/code_black.jpg");?>" alt="default" onclick="LabelForImg ('code_theme', 1);"/>
							</div>
							<div style="float:left; margin:10px;">
								<input type="radio" name="code_theme" id="code_theme" value="code_squared" <? if($settings['code_theme']=='code_squared'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/code_squared.jpg");?>" alt="default" onclick="LabelForImg ('code_theme', 2);"/>
							</div>
							<div style="float:left; margin:10px;">
								<input type="radio" name="code_theme" id="code_theme" value="code_streaked" <? if($settings['code_theme']=='code_streaked'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/code_streaked.jpg");?>" alt="default" onclick="LabelForImg ('code_theme', 3);"/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="compress">Frame Themes</label></th>
						<td style="white-space:normal;">
							<div style="text-align:left; margin-top:12px;">
								<input type="radio" name="frame_theme" id="frame_theme" value="frame_default" <? if($settings['frame_theme']=='frame_default'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/frame_default.jpg");?>" alt="default" onclick="LabelForImg ('frame_theme', 0);"/>
							</div>
							<div style="text-align:left; margin-top:12px;">
								<input type="radio" name="frame_theme" id="frame_theme" value="frame_black" <? if($settings['frame_theme']=='frame_black'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/frame_black.jpg");?>" alt="default" onclick="LabelForImg ('frame_theme', 1);"/>
							</div>
							<div style="text-align:left; margin-top:12px;">
								<input type="radio" name="frame_theme" id="frame_theme" value="frame_elegant" <? if($settings['frame_theme']=='frame_elegant'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/frame_elegant.jpg");?>" alt="default" onclick="LabelForImg ('frame_theme', 2);"/>
							</div>
							<div style="text-align:left; margin-top:12px;">
								<input type="radio" name="frame_theme" id="frame_theme" value="frame_pure" <? if($settings['frame_theme']=='frame_pure'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/frame_pure.jpg");?>" alt="default" onclick="LabelForImg ('frame_theme', 3);"/>
							</div>
							<div style="text-align:left; margin-top:12px;">
								<input type="radio" name="frame_theme" id="frame_theme" value="frame_green" <? if($settings['frame_theme']=='frame_green'){echo "checked";} ?> style="vertical-align:top;"/>
								<img src="<?php echo ($dr_hl_plugin_url . "images/admin_themes/frame_green.jpg");?>" alt="default" onclick="LabelForImg ('frame_theme', 4);"/>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th style="color:red;">Save Settings:</th>
						<td style="padding:50px 0px 60px 40px;">
							<input type="submit" name="dr_highlighter_submit" value="<?php _e('Save Settings') ?> &raquo; " /><br />
							<small>Some of the settings may not take effect on previously highlighted code until they are updated.</small>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
	</div>
	<?php
}


/*===================================
*   action and admin registration   *
===================================*/

function add_dr_highlighter_options_subpanel ()
{
	if (function_exists('add_options_page')) {
		add_options_page ('Dottoro Highlighter', 'Dottoro Highlighter', 10, __FILE__, 'dr_highlighter_options_panel');
	}
}

add_action('admin_menu', 'add_dr_highlighter_options_subpanel');
add_action('wp_head', 'dr_highlighter_Head_Includes');

// Runs whenever a post or page is created or updated
add_action('save_post', 'dr_highlighter_on_after_save', 1);

// Runs whenever the post content retrieved from the database
add_filter('the_content', 'dr_highlighter_Replace_Content');

?>