<?php

$modInfo['known_members']['name'] = "scout";
$modInfo['known_members']['abstract'] = "Allow individuals to opt-in to killmails, for purposes of scouting or logistics.";
$modInfo['known_members']['about'] = "https://github.com/jjlupa/edk-mods/tree/master/scout";

// The docs say you can use this to replace the core class.  But as soon as I do that, it gives me errors.  Easier for now
// to copy the changes into commong/include, and everything just works.
//
// edkloader::register('Kill', dirname(FILE).'/class.kill.php' );

event::register("killDetail_assembling", "ScoutMod::renderScoutsList");

class ScoutMod
{
	function renderScoutsList($page)
	{
		require_once('class.scout.php');

		$page->addBehind("involved", "ScoutMod::listScouts");
	}
	function listScouts($page)
	{
		$scouts = new Scouts($page->kll_id);
		$confirmDelete = false;
		$error = '';

		if (isset($_POST['scoutsubmit']))
		{
		    $pw = false;
		    if (!config::get('scouts_pw') || $page->page->isAdmin())
		    {
		        $pw = true;
		    }
		    if ($_POST['password'] == config::get("scouts_password") || $pw)
		    {
		        if ($_POST['scoutname'] == '')
		        {
		            $error = 'Error: No pilot name specified.';
		        }
		        else
		        {
		            $scout = $_POST['scoutname'];
		            if (!$scouts->addScout($scout))
		            {
		            	$error = 'Error: Pilot not found.';
		            }
			}
		    }
		    else
		    {
		        // Password is wrong
		        $error = 'Error: Wrong password.';
		    }
		}
		else if (isset($_POST['delscout_request']) && isset($_POST['delscout_scoutinfo']))
		{
			$infos = explode(".", $_POST['delscout_scoutinfo']);
			$confirmDeleteScoutID = $infos[0];
			$confirmDeletePilotID = $infos[1];
			$confirmDeletePilotName = $infos[2];
			$confirmDelete = true;
		}
		else if (isset($_POST['delscout_confirm']))
		{
			$scoutid = $_POST['delscout_scoutID'];
			$pilotid = $_POST['delscout_pilotID'];

			$scouts->delScout($scoutid, $pilotid);
		}

		$html .= $scouts->getScouts(config::get('scouts_pw'), $page->page->isAdmin(), $confirmDelete, $confirmDeleteScoutID, $confirmDeletePilotID, $confirmDeletePilotName, $error);

		return $html;
	}
}
?>