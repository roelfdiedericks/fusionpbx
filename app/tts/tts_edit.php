<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2020
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes
	require_once "root.php";
	require_once "resources/require.php";
	require_once "resources/check_auth.php";

//check permissions
	if (permission_exists('tts_add') || permission_exists('tts_edit')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//set the action as an add or an update
	if (is_uuid($_REQUEST["id"])) {
		$action = "update";
		$tts_uuid = $_REQUEST["id"];
	}
	else {
		$action = "add";
	}

//get the form value and set to php variables
	if (count($_POST) > 0) {

		//process the http post data by submitted action
			if ($_POST['action'] != '' && is_uuid($_POST['tts_uuid'])) {
				$array[0]['checked'] = 'true';
				$array[0]['uuid'] = $_POST['tts_uuid'];

				switch ($_POST['action']) {
					case 'delete':
						if (permission_exists('tts_delete')) {
							$obj = new tts;
							$obj->delete($array);
						}
						break;
				}

				header('Location: tts.php');
				exit;
			}

		if (permission_exists('tts_domain')) {
			$domain_uuid = $_POST["domain_uuid"];
		}
		$tts_name = $_POST["tts_name"];
		$tts_language = $_POST["tts_language"];
		$tts_enabled = $_POST["tts_enabled"];
		$tts_description = $_POST["tts_description"];
		$tts_details_delete = $_POST["tts_details_delete"];

		//clean the name
		$tts_name = str_replace(" ", "_", $tts_name);
		$tts_name = str_replace("'", "", $tts_name);
	}

//process the changes from the http post
	if (count($_POST) > 0 && strlen($_POST["persistformvar"]) == 0) {
	
		//get the uuid
			if ($action == "update") {
				$tts_uuid = $_POST["tts_uuid"];
			}

		//validate the token
			$token = new token;
			if (!$token->validate($_SERVER['PHP_SELF'])) {
				message::add($text['message-invalid_token'],'negative');
				header('Location: tts.php');
				exit;
			}

		//check for all required data
			$msg = '';
			if (strlen($tts_name) == 0) { $msg .= $text['message-required']." ".$text['label-name']."<br>\n"; }
			if (strlen($tts_language) == 0) { $msg .= $text['message-required']." ".$text['label-language']."<br>\n"; }
			if (strlen($msg) > 0 && strlen($_POST["persistformvar"]) == 0) {
				require_once "resources/header.php";
				require_once "resources/persist_form_var.php";
				echo "<div align='center'>\n";
				echo "<table><tr><td>\n";
				echo $msg."<br />";
				echo "</td></tr></table>\n";
				persistformvar($_POST);
				echo "</div>\n";
				require_once "resources/footer.php";
				return;
			}

		//add the tts
			if ($_POST["persistformvar"] != "true") {
				if ($action == "add" && permission_exists('tts_add')) {
					//build data array
						$tts_uuid = uuid();
						$array['tts'][0]['domain_uuid'] = $domain_uuid;
						$array['tts'][0]['tts_uuid'] = $tts_uuid;
						$array['tts'][0]['tts_name'] = $tts_name;
						$array['tts'][0]['tts_language'] = $tts_language;
						$array['tts'][0]['tts_enabled'] = $tts_enabled;
						$array['tts'][0]['tts_description'] = $tts_description;

						if ($_POST['tts_detail_function'] != '') {
							if ($_POST['tts_detail_function'] == 'execute' && substr($_POST['tts_detail_data'], 0,5) != "sleep" && !permission_exists("tts_execute")) {
								header("Location: tts_edit.php");
								exit;
							}
							$_POST['tts_detail_tag'] = 'action'; // default, for now
							$_POST['tts_detail_group'] = "0"; // one group, for now

							if ($_POST['tts_detail_data'] != '') {
								$tts_detail_uuid = uuid();
								$array['tts_details'][0]['tts_detail_uuid'] = $tts_detail_uuid;
								$array['tts_details'][0]['tts_uuid'] = $tts_uuid;
								$array['tts_details'][0]['domain_uuid'] = $domain_uuid;
								$array['tts_details'][0]['tts_detail_order'] = $_POST['tts_detail_order'];
								$array['tts_details'][0]['tts_detail_tag'] = $_POST['tts_detail_tag'];
								$array['tts_details'][0]['tts_detail_pattern'] = $_POST['tts_detail_pattern'];
								$array['tts_details'][0]['tts_detail_function'] = $_POST['tts_detail_function'];
								$array['tts_details'][0]['tts_detail_data'] = $_POST['tts_detail_data'];
								$array['tts_details'][0]['tts_detail_method'] = $_POST['tts_detail_method'];
								$array['tts_details'][0]['tts_detail_type'] = $_POST['tts_detail_type'];
								$array['tts_details'][0]['tts_detail_group'] = $_POST['tts_detail_group'];
							}
						}

					//execute insert
						$p = new permissions;
						$p->add('tts_detail_add', 'temp');

						$database = new database;
						$database->app_name = 'tts';
						$database->app_uuid = 'f81a598d-fd7e-4ac8-851e-bcec8bc77996';
						$database->save($array);
						unset($array);

						$p->delete('tts_detail_add', 'temp');

					//save the xml to the file system if the tts directory is set
						//save_tts_xml();

					//clear the cache
						$cache = new cache;
						$cache->delete("languages:".$tts_language.".".$tts_uuid);

					//clear the destinations session array
						if (isset($_SESSION['destinations']['array'])) {
							unset($_SESSION['destinations']['array']);
						}

					//send a redirect
						message::add($text['message-add']);
						header("Location: tts_edit.php?id=".$tts_uuid);
						exit;
				}

			//update the tts
				if ($action == "update" && permission_exists('tts_edit')) {
					//build data array
						$array['tts'][0]['domain_uuid'] = $domain_uuid;
						$array['tts'][0]['tts_uuid'] = $tts_uuid;
						$array['tts'][0]['tts_name'] = $tts_name;
						$array['tts'][0]['tts_language'] = $tts_language;
						$array['tts'][0]['tts_enabled'] = $tts_enabled;
						$array['tts'][0]['tts_description'] = $tts_description;

						if ($_POST['tts_detail_function'] != '') {
							if ($_POST['tts_detail_function'] == 'execute' && substr($_POST['tts_detail_data'], 0,5) != "sleep" && !permission_exists("tts_execute")) {
								header("Location: tts_edit.php?id=".$tts_uuid);
								exit;
							}
							$_POST['tts_detail_tag'] = 'action'; // default, for now
							$_POST['tts_detail_group'] = "0"; // one group, for now

							if ($_POST['tts_detail_data'] != '') {
								$tts_detail_uuid = uuid();
								$array['tts_details'][0]['tts_detail_uuid'] = $tts_detail_uuid;
								$array['tts_details'][0]['tts_uuid'] = $tts_uuid;
								$array['tts_details'][0]['domain_uuid'] = $domain_uuid;
								$array['tts_details'][0]['tts_detail_order'] = $_POST['tts_detail_order'];
								$array['tts_details'][0]['tts_detail_tag'] = $_POST['tts_detail_tag'];
								$array['tts_details'][0]['tts_detail_pattern'] = $_POST['tts_detail_pattern'];
								$array['tts_details'][0]['tts_detail_function'] = $_POST['tts_detail_function'];
								$array['tts_details'][0]['tts_detail_data'] = $_POST['tts_detail_data'];
								$array['tts_details'][0]['tts_detail_method'] = $_POST['tts_detail_method'];
								$array['tts_details'][0]['tts_detail_type'] = $_POST['tts_detail_type'];
								$array['tts_details'][0]['tts_detail_group'] = $_POST['tts_detail_group'];
							}
						}

					//execute update/insert
						$p = new permissions;
						$p->add('tts_detail_add', 'temp');

						$database = new database;
						$database->app_name = 'tts';
						$database->app_uuid = 'f81a598d-fd7e-4ac8-851e-bcec8bc77996';
						$database->save($array);
						unset($array);

						$p->delete('tts_detail_add', 'temp');

					//remove checked tts details
						if (
							is_array($tts_details_delete)
							&& @sizeof($tts_details_delete) != 0
							) {
							$obj = new tts;
							$obj->tts_uuid = $tts_uuid;
							$obj->delete_details($tts_details_delete);
						}

					//clear the cache
						$cache = new cache;
						$cache->delete("languages:".$tts_language.".".$tts_uuid);

					//clear the destinations session array
						if (isset($_SESSION['destinations']['array'])) {
							unset($_SESSION['destinations']['array']);
						}

					//send a redirect
						message::add($text['message-update']);
						header("Location: tts_edit.php?id=".$tts_uuid);
						exit;;

				}

			}
	
	}

//pre-populate the form
	if (count($_GET)>0 && $_POST["persistformvar"] != "true") {
		$tts_uuid = $_GET["id"];
		$sql = "select * from v_tts ";
		$sql .= "where ( ";
		$sql .= " domain_uuid = :domain_uuid or ";
		$sql .= " domain_uuid is null ";
		$sql .= ") ";
		$sql .= "and tts_uuid = :tts_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['tts_uuid'] = $tts_uuid;
		$database = new database;
		$row = $database->select($sql, $parameters, 'row');
		if (is_array($row) && @sizeof($row) != 0) {
			$tts_name = $row["tts_name"];
			$tts_language = $row["tts_language"];
			$tts_enabled = $row["tts_enabled"];
			$tts_description = $row["tts_description"];
		}
		unset($sql, $parameters, $row);
	}

//get the tts details
	if (is_uuid($tts_uuid)) {
		$sql = "select * from v_tts_details ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and tts_uuid = :tts_uuid ";
		$sql .= "order by tts_detail_order asc ";
		$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
		$parameters['tts_uuid'] = $tts_uuid;
		$database = new database;
		$tts_details = $database->select($sql, $parameters, 'all');
		unset($sql, $parameters);
	}

//get the recording names from the database.
	$sql = "select recording_name, recording_filename from v_recordings ";
	$sql .= "where domain_uuid = :domain_uuid ";
	$sql .= "order by recording_name asc ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$recordings = $database->select($sql, $parameters, 'all');
	unset($sql, $parameters);

//create token
	$object = new token;
	$token = $object->create($_SERVER['PHP_SELF']);

//include the header
	if ($action == 'add') { $document['title'] = $text['title-add_tts']; }
	if ($action == 'update') { $document['title'] = $text['title-edit_tts']; }
	require_once "resources/header.php";

//js to control action form input
	echo "<script type='text/javascript'>\n";

	echo "function load_action_options(selected_index) {\n";
	echo "	var obj_action = document.getElementById('tts_detail_data');\n";
	echo "	if (selected_index == 0 || selected_index == 1) {\n";
	echo "		if (obj_action.type == 'text') {\n";
	echo "			action_to_select();\n";
	echo "			var obj_action = document.getElementById('tts_detail_data');\n";
	echo "			obj_action.setAttribute('style', 'width: 300px; min-width: 300px; max-width: 300px;');\n";
	echo "		}\n";
	echo "		else {\n";
	echo "			clear_action_options();\n";
	echo "		}\n";
	echo "	}\n";
	if (if_group("superadmin")) {
		echo "	else {\n";
		echo "		document.getElementById('tts_detail_data_switch').style.display='none';\n";
		echo "		obj_action.setAttribute('style', 'width: 300px; min-width: 300px; max-width: 300px;');\n";
		echo "	}\n";
	}
	echo "	if (selected_index == 0) {\n"; //play
	echo "		obj_action.options[obj_action.options.length] = new Option('', '');\n"; //blank option
	//recordings
		$tmp_selected = false;
		if (is_array($recordings) && @sizeof($recordings) != 0) {
			echo "var opt_group = document.createElement('optgroup');\n";
			echo "opt_group.label = \"".$text['label-recordings']."\";\n";
			foreach ($recordings as &$row) {
				if ($_SESSION['recordings']['storage_type']['text'] == 'base64') {
					echo "opt_group.appendChild(new Option(\"".$row["recording_name"]."\", \"\${lua streamfile.lua ".$row["recording_filename"]."}\"));\n";
				}
				else {
					echo "opt_group.appendChild(new Option(\"".$row["recording_name"]."\", \"".$_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/'.$row["recording_filename"]."\"));\n";
				}
			}
			echo "obj_action.appendChild(opt_group);\n";
		}
		unset($recordings, $row);
	//sounds
		$file = new file;
		$sound_files = $file->sounds();
		if (is_array($sound_files) && @sizeof($sound_files) != 0) {
			echo "var opt_group = document.createElement('optgroup');\n";
			echo "opt_group.label = \"".$text['label-sounds']."\";\n";
			foreach ($sound_files as $value) {
				if (strlen($value) > 0) {
					echo "opt_group.appendChild(new Option(\"".$value."\", \"".$value."\"));\n";
				}
			}
			echo "obj_action.appendChild(opt_group);\n";
		}
		unset($sound_files, $row);
	echo "	}\n";
	echo "	else if (selected_index == 1) {\n"; //pause
	echo "		obj_action.options[obj_action.options.length] = new Option('', '');\n"; //blank option
	for ($s = 0.1; $s <= 5; $s = $s + 0.1) {
		echo "	obj_action.options[obj_action.options.length] = new Option('".number_format($s, 1)."s', 'sleep(".($s * 1000).")');\n";
	}
	echo "	}\n";
	if (if_group("superadmin")) {
		echo "	else if (selected_index == 2) {\n"; //execute
		echo "		action_to_input();\n";
		echo "	}\n";
	}
	echo "}\n";

	echo "function clear_action_options() {\n";
	echo "	var len, groups, par;\n";
	echo "	sel = document.getElementById('tts_detail_data');\n";
	echo "	groups = sel.getElementsByTagName('optgroup');\n";
	echo "	len = groups.length;\n";
	echo "	for (var i=len; i; i--) {\n";
	echo "		sel.removeChild( groups[i-1] );\n";
	echo "	}\n";
	echo "	len = sel.options.length;\n";
	echo "	for (var i=len; i; i--) {\n";
	echo "		par = sel.options[i-1].parentNode;\n";
	echo "		par.removeChild( sel.options[i-1] );\n";
	echo "	}\n";
	echo "}\n";

	if (if_group("superadmin")) {
		echo "function action_to_input() {\n";
		echo "	obj = document.getElementById('tts_detail_data');\n";
		echo "	tb = document.createElement('INPUT');\n";
		echo "	tb.type = 'text';\n";
		echo "	tb.name = obj.name;\n";
		echo "	tb.id = obj.id;\n";
		echo "	tb.value = obj.options[obj.selectedIndex].value;\n";
		echo "	tb.className = 'formfld';\n";
		echo "	tb_width = (document.getElementById('tts_detail_function').selectedIndex == 2) ? '300px' : '267px';\n";
		echo "	tb.setAttribute('style', 'width: '+tb_width+'; min-width: '+tb_width+'; max-width: '+tb_width+';');\n";
		echo "	obj.parentNode.insertBefore(tb, obj);\n";
		echo "	obj.parentNode.removeChild(obj);\n";
		echo "	if (document.getElementById('tts_detail_function').selectedIndex != 2) {\n";
		echo "		tb.setAttribute('style', 'width: 263px; min-width: 263px; max-width: 263px;');\n";
		echo "		document.getElementById('tts_detail_data_switch').style.display='';\n";
		echo "	}\n";
		echo "	else {\n";
		echo "		tb.focus();\n";
		echo "	}\n";
		echo "}\n";

		echo "function action_to_select() {\n";
		echo "	obj = document.getElementById('tts_detail_data');\n";
		echo "	sb = document.createElement('SELECT');\n";
		echo "	sb.name = obj.name;\n";
		echo "	sb.id = obj.id;\n";
		echo "	sb.className = 'formfld';\n";
		echo "	sb.setAttribute('style', 'width: 300px; min-width: 300px; max-width: 300px;');\n";
		echo "	sb.setAttribute('onchange', 'action_to_input();');\n";
		echo "	obj.parentNode.insertBefore(sb, obj);\n";
		echo "	obj.parentNode.removeChild(obj);\n";
		echo "	document.getElementById('tts_detail_data_switch').style.display='none';\n";
		echo "	clear_action_options();\n";
		echo "}\n";
	}
	echo "</script>\n";

//show the content
	echo "<form method='post' name='frm' id='frm'>\n";

	echo "<div class='action_bar' id='action_bar'>\n";
	echo "	<div class='heading'>";
	if ($action == "add") {
		echo "<b>".$text['title-add_tts']."</b>";
	}
	if ($action == "update") {
		echo "<b>".$text['title-edit_tts']."</b>";
	}
	echo "	</div>\n";
	echo "	<div class='actions'>\n";
	echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'id'=>'btn_back','link'=>'tts.php']);
	if ($action == "update" && permission_exists('tts_delete')) {
		echo button::create(['type'=>'button','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'name'=>'btn_delete','style'=>'margin-left: 15px;','onclick'=>"modal_open('modal-delete','btn_delete');"]);
	}
	echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','style'=>'margin-left: 15px;']);
	echo "	</div>\n";
	echo "	<div style='clear: both;'></div>\n";
	echo "</div>\n";

	if ($action == "update" && permission_exists('tts_delete')) {
		echo modal::create(['id'=>'modal-delete','type'=>'delete','actions'=>button::create(['type'=>'submit','label'=>$text['button-continue'],'icon'=>'check','id'=>'btn_delete','style'=>'float: right; margin-left: 15px;','collapse'=>'never','name'=>'action','value'=>'delete','onclick'=>"modal_close();"])]);
	}

	echo "<table width='100%'  border='0' cellpadding='0' cellspacing='0'>\n";

	echo "<tr>\n";
	echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-name']."\n";
	echo "</td>\n";
	echo "<td width='70%' class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='tts_name' maxlength='255' value=\"".escape($tts_name)."\">\n";
	echo "	<br />\n";
	echo "	".$text['description-name']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-language']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='tts_language' maxlength='255' value=\"".escape($tts_language)."\">\n";
	echo "	<br />\n";
	echo "	".$text['description-language']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>";
	echo "<td class='vncell' valign='top'>".$text['label-structure']."</td>";
	echo "<td class='vtable' align='left'>";
	echo "	<table border='0' cellpadding='0' cellspacing='0'>\n";
	echo "		<tr>\n";
	echo "			<td class='vtable'><strong>".$text['label-function']."</strong></td>\n";
	echo "			<td class='vtable'><strong>".$text['label-action']."</strong></td>\n";
	echo "			<td class='vtable' style='text-align: center;'><strong>".$text['label-order']."</strong></td>\n";
	if ($tts_details) {
		echo "			<td class='vtable edit_delete_checkbox_all' onmouseover=\"swap_display('delete_label_details', 'delete_toggle_details');\" onmouseout=\"swap_display('delete_label_details', 'delete_toggle_details');\">\n";
		echo "				<span id='delete_label_details'>".$text['label-delete']."</span>\n";
		echo "				<span id='delete_toggle_details'><input type='checkbox' id='checkbox_all_details' name='checkbox_all' onclick=\"edit_all_toggle('details');\"></span>\n";
		echo "			</td>\n";
	}
	echo "		</tr>\n";
	if (is_array($tts_details) && @sizeof($tts_details) != 0) {
		foreach($tts_details as $x => $field) {
			//clean up output for display
			if ($field['tts_detail_function'] == 'play-file' && substr($field['tts_detail_data'], 0, 21) == '${lua streamfile.lua ') {
				$tts_detail_function = $text['label-play'];
				$tts_detail_data = str_replace('${lua streamfile.lua ', '', $field['tts_detail_data']);
				$tts_detail_data = str_replace('}', '', $tts_detail_data);
			}
			else if ($field['tts_detail_function'] == 'execute' && substr($field['tts_detail_data'], 0, 6) == 'sleep(') {
				$tts_detail_function = $text['label-pause'];
				$tts_detail_data = str_replace('sleep(', '', $field['tts_detail_data']);
				$tts_detail_data = str_replace(')', '', $tts_detail_data);
				$tts_detail_data = ($tts_detail_data / 1000).'s'; // seconds
			}
			else if ($field['tts_detail_function'] == 'play-file') {
				$tts_detail_function = $text['label-play'];
				$tts_detail_data = str_replace($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/', '', $field['tts_detail_data']);
			}
			else {
				$tts_detail_function = $field['tts_detail_function'];
				$tts_detail_data = $field['tts_detail_data'];
			}
			echo "<tr>\n";
			echo "	<td class='vtable'>".escape($tts_detail_function)."&nbsp;</td>\n";
			echo "	<td class='vtable'>".escape($tts_detail_data)."&nbsp;</td>\n";
			echo "	<td class='vtable' style='text-align: center;'>".$field['tts_detail_order']."&nbsp;</td>\n";
			echo "	<td class='vtable' style='text-align: center; padding-bottom: 3px;'>";
			if (is_uuid($field['tts_detail_uuid'])) {
				echo "		<input type='checkbox' name='tts_details_delete[".$x."][checked]' value='true' class='chk_delete checkbox_details' onclick=\"edit_delete_action('details');\">\n";
				echo "		<input type='hidden' name='tts_details_delete[".$x."][uuid]' value='".escape($field['tts_detail_uuid'])."' />\n";
			}
			echo "	</td>\n";
			echo "</tr>\n";
		}
	}
	unset($tts_details, $field);
	echo "<tr>\n";
	echo "	<td class='vtable' style='border-bottom: none;' align='left' nowrap='nowrap'>\n";
	echo "		<select name='tts_detail_function' id='tts_detail_function' class='formfld' onchange=\"load_action_options(this.selectedIndex);\">\n";
	echo "			<option value='play-file'>".$text['label-play']."</option>\n";
	echo "			<option value='execute'>".$text['label-pause']."</option>\n";
	if (if_group("superadmin")) {
		echo "			<option value='execute'>".$text['label-execute']."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "	<td class='vtable' style='border-bottom: none;' align='left' nowrap='nowrap'>\n";
	echo "		<select name='tts_detail_data' id='tts_detail_data' class='formfld' style='width: 300px; min-width: 300px; max-width: 300px;' ".((if_group("superadmin")) ? "onchange='action_to_input();'" : null)."></select>";
	if (if_group("superadmin")) {
		echo "	<input id='tts_detail_data_switch' type='button' class='btn' style='margin-left: 4px; display: none;' value='&#9665;' onclick=\"action_to_select(); load_action_options(document.getElementById('tts_detail_function').selectedIndex);\">\n";
	}
	echo "		<script>load_action_options(0);</script>\n";
	echo "	</td>\n";
	echo "	<td class='vtable' style='border-bottom: none;'>\n";
	echo "		<select name='tts_detail_order' class='formfld'>\n";
	for ($i = 0; $i <= 999; $i++) {
		$i_padded = str_pad($i, 3, '0', STR_PAD_LEFT);
		echo "		<option value='".escape($i_padded)."'>".escape($i_padded)."</option>\n";
	}
	echo "		</select>\n";
	echo "	</td>\n";
	echo "	<td>\n";
	echo button::create(['type'=>'submit','label'=>$text['button-add'],'icon'=>$_SESSION['theme']['button_icon_add']]);
	echo "	</td>\n";

	echo "	</tr>\n";
	echo "</table>\n";

	echo "	".$text['description-structure']."\n";
	echo "	<br />\n";
	echo "</td>";
	echo "</tr>";

	if (permission_exists('tts_domain')) {
		echo "<tr>\n";
		echo "<td class='vncell' valign='top' nowrap='nowrap'>\n";
		echo "	".$text['label-domain']."\n";
		echo "</td>\n";
		echo "<td class='vtable'>\n";
		echo "	<select name='domain_uuid' class='formfld'>\n";
		if (strlen($domain_uuid) == 0) {
			echo "		<option value='' selected='selected'>".$text['label-global']."</option>\n";
		}
		else {
			echo "		<option value=''>".$text['label-global']."</option>\n";
		}
		foreach ($_SESSION['domains'] as $row) {
			if ($row['domain_uuid'] == $domain_uuid) {
				echo "		<option value='".escape($row['domain_uuid'])."' selected='selected'>".escape($row['domain_name'])."</option>\n";
			}
			else {
				echo "		<option value='".escape($row['domain_uuid'])."'>".escape($row['domain_name'])."</option>\n";
			}
		}
		echo "	</select>\n";
		echo "</td>\n";
		echo "</tr>\n";
	}

	echo "<tr>\n";
	echo "<td class='vncellreq' valign='top' align='left' nowrap='nowrap'>\n";
	echo "	".$text['label-enabled']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<select class='formfld' name='tts_enabled'>\n";
	echo "		<option value='true'>".$text['label-true']."</option>\n";
	echo "		<option value='false' ".(($tts_enabled == "false") ? "selected='selected'" : null).">".$text['label-false']."</option>\n";
	echo "	</select>\n";
	echo "	<br />\n";
	echo $text['description-enabled']."\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "<tr>\n";
	echo "<td class='vncell' valign='top' align='left' nowrap>\n";
	echo "	".$text['label-description']."\n";
	echo "</td>\n";
	echo "<td class='vtable' align='left'>\n";
	echo "	<input class='formfld' type='text' name='tts_description' maxlength='255' value=\"".escape($tts_description)."\">\n";
	echo "</td>\n";
	echo "</tr>\n";

	echo "</table>";
	echo "<br><br>";

	if ($action == "update") {
		echo "	<input type='hidden' name='tts_uuid' value='".escape($tts_uuid)."'>\n";
	}
	echo "<input type='hidden' name='".$token['name']."' value='".$token['hash']."'>\n";

	echo "</form>";

//include the footer
	require_once "resources/footer.php";

?>
