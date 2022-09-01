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
	Portions created by the Initial Developer are Copyright (C) 2008-2019
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

if ($domains_processed == 1) {

	
		


	//delete the tts from memcache
		$fp = event_socket_create($_SESSION['event_socket_ip_address'], $_SESSION['event_socket_port'], $_SESSION['event_socket_password']);
		if ($fp) {
			//get tts languages
			$sql = "select distinct tts_language from v_tts order by tts_language asc ";
			$database = new database;
			$result = $database->select($sql, null, 'all');
			//delete memcache var
			if (is_array($result) && @sizeof($result) != 0) {
				foreach ($result as $row) {
					//clear the cache
					$cache = new cache;
					$cache->delete("languages:".$row['tts_language']);
				}
			}
			unset($sql, $result, $row);
		}
		unset($fp);

}

?>
