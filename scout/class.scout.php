<?php
require_once('class.pilot.php');
require_once('class.corporation.php');
require_once('class.alliance.php');
require_once('class.kill.php');

class Scouts
{
	function __construct($kll_id = '')
	{
		if ($kll_id != '')
		{
			$this->init($kll_id);
		}
	}

	function init($kll_id)
	{
		$this->killID_ = $kll_id;
		$this->raw_ = false;
		$this->scouts_ = array();
		$qry = new DBQuery(true);
		$qry->execute("SELECT * FROM kb3_scout WHERE `inp_kll_id` = '".$kll_id."'");
		while ($row = $qry->getRow())
		{
			$this->scouts_[] = $this->getPilotData($row['inp_plt_id'], $row['scout_id']);
		}
	}

	/* Returns an array of pilot information */
	function getPilotData($pilotID, $scoutID = 0)
	{
		if (!isset($pilotID) || !is_numeric($pilotID))
			return;
		$pilot = new Pilot($pilotID);
		$pilot->exists(); //required for r604 to r1027 edk code (also including up to 3.1.5 releases)
		$corp = $pilot->getCorp();
		$corpID = $corp->getID();
		$alliance = $corp->getAlliance();
		$allianceID = $alliance->getID();
		$pilotName = $pilot->getName();
		$corpName = $corp->getName();
		$allianceName = $alliance->getName();
		$img = $pilot->getPortraitURL(64);

		return array('pilotid' => $pilotID, 'scoutid' => $scoutID, 'pilotname' => $pilotName, 'corp' => $corpName, 'corpid' => $corpID, 'alliance' => $allianceName, 'allianceid' => $allianceID, 'pilotimg' => $img, 'killid' => $this->killID_);
	}

	function getScouts($passReq, $isAdmin, $confirmDelete, $confirmDeleteScoutID, $confirmDeletePilotID, $confirmDeletePilotName, $error)
	{
		global $smarty;

		$smarty->assignByRef('scouts', $this->scouts_);
		$smarty->assign('scoutPassReq', $passReq);
		$smarty->assign('isAdmin', $isAdmin);
		$smarty->assign('confirmDelete', $confirmDelete);
		$smarty->assign('confirmDeleteScoutID', $confirmDeleteScoutID);
		$smarty->assign('confirmDeletePilotID', $confirmDeletePilotID);
		$smarty->assign('confirmDeletePilotName', $confirmDeletePilotName);
		$smarty->assign('error', $error);
		return $smarty->fetch('../../../mods/scout/scout.tpl');
	}

	function addScout($pilot_name)
	{
		$scoutship = "9999";

		//get pilot order
		$pqry = new DBPreparedQuery();
		$pqry->prepare("select max(ind_order), ind_timestamp from kb3_inv_detail where ind_kll_id = ? group by ind_timestamp");
		$pqry->bind_param('i', $this->killID_);
		$pqry->bind_result($scoutorder, $timestamp);
		if (!$pqry->execute() || !$pqry->recordCount())
		{
			return false;
		}
		else
		{
			$pqry->fetch();
		}

		$scoutorder = $scoutorder + 1;

		//lookup pilot id by name
		$pilot = Pilot::lookup($pilot_name);
		if (!$pilot) {
		  return false;
		}

		if (isset($pilot)) {
			$pilotid = $pilot->getID();
			if ($pilotid == 0) {
				return false;
			}
		} else {
			return false;
		}

		$qry = new DBQuery(true);
		$qry->execute("INSERT INTO kb3_scout (`inp_kll_id`,`inp_plt_id`) VALUES ('".$this->killID_."','".$pilotid."')");
		$this->scouts_[] = $this->getPilotData($pilotid, $qry->getInsertID());
		if (!isset($this->scouts_))
		{
			return false;
		}

		$qry->execute("INSERT INTO kb3_inv_detail (`ind_kll_id`,`ind_timestamp`,`ind_plt_id`,`ind_all_id`,`ind_crp_id`,`ind_shp_id`,`ind_order`) VALUES ('".$this->killID_."','".$timestamp."','".$pilotid."','".$this->scouts_[0]['allianceid']."','".$this->scouts_[0]['corpid']."','".$scoutship."','".$scoutorder."')");

		//add to pilot's stats
		$qry->execute("SELECT 1 FROM kb3_sum_pilot WHERE psm_plt_id = '".$pilotid."'");
		if ($qry->recordCount() > 0)
		{
		  $this->kill = new Kill($this->killID_);
		  $qry->execute(
				"INSERT INTO kb3_sum_pilot 
                                    (psm_plt_id, psm_shp_id, psm_kill_count, psm_kill_isk) 
                                    VALUES ('".$pilotid."','".
				             $this->kill->getVictimShip()->getClass()->getID()."', 1, '".
				             $this->kill->getISKLoss()."') 
                                    ON DUPLICATE KEY 
                                       UPDATE psm_kill_count = psm_kill_count + 1, 
                                              psm_kill_isk = psm_kill_isk + '".$this->kill->getISKLoss()."'");

			//Jalon debugging - i had to run this query to fix the DB, it was missing a default value:
			//alter table kb3_inv_detail modify column ind_dmgdone int(11) not null default 0;
			//error_log("INSERT INTO kb3_sum_pilot (psm_plt_id, psm_shp_id, psm_kill_count, psm_kill_isk) VALUES ('".$pilotid."','".$this->kill->getVictimShip()->getClass()->getID()."', 1, '".$this->kill->getISKLoss()."') ON DUPLICATE KEY UPDATE psm_kill_count = psm_kill_count + 1, psm_kill_isk = psm_kill_isk + '".$this->kill->getISKLoss()."'");

			$qry->execute("UPDATE kb3_pilots 
                                          SET plt_kpoints = plt_kpoints + '".$this->kill->getKillPoints().
				          "' WHERE plt_id = '".$pilotid."'");
		}

		//make sure involved count is shown correctly (it's generated before this class is loaded)
		header("Location: ?a=kill_detail&kll_id=".$this->killID_);

		exit;
	}

	function delScout($s_id, $pilotid)
	{
		$qry = new DBQuery(true);
		$qry->execute("delete from kb3_scout where inp_kll_id = ".$this->killID_." and scout_id = ".$s_id." limit 1");

		//get pilot order to be deleted
		$pqry = new DBPreparedQuery();
		$pqry->prepare("select ind_order from kb3_inv_detail where ind_kll_id = ? and ind_plt_id = ?");
		$pqry->bind_param('ii', $this->killID_, $pilotid);
		$pqry->bind_result($scoutOrder);
		if (!$pqry->execute() || !$pqry->recordCount())
		{
			return false;
		}
		else
		{
			$pqry->fetch();
		}

		//get highest pilot order
		$pqry = new DBPreparedQuery();
		$pqry->prepare("select max(ind_order) from kb3_inv_detail where ind_kll_id = ?");
		$pqry->bind_param('i', $this->killID_);
		$pqry->bind_result($maxScoutOrder);
		if (!$pqry->execute() || !$pqry->recordCount())
		{
			return false;
		}
		else
		{
			$pqry->fetch();
		}

		$qry->execute("delete from kb3_inv_detail where ind_kll_id = ".$this->killID_." and ind_plt_id = ".$pilotid." and ind_shp_id = '9999' limit 1");

		//reorder remaining scouts
		for($i = $scoutOrder + 1; $i <= $maxScoutOrder; $i++)
		{
			$qry->execute("update kb3_inv_detail set ind_order = '".($i-1)."' where ind_kll_id = '".$this->killID_."' and ind_shp_id = '9999' and ind_order = '".$i."' limit 1");
		}

		//remove from pilot's stats
		$qry->execute("SELECT 1 FROM kb3_sum_pilot WHERE psm_plt_id = '".$pilotid."'");
		if ($qry->recordCount() > 0) {
			$this->kill = new Kill($this->killID_);
			$qry->execute("UPDATE kb3_sum_pilot SET psm_kill_count = psm_kill_count - 1, psm_kill_isk = psm_kill_isk - '".$this->kill->getISKLoss()."' WHERE psm_plt_id = '".$pilotid."' AND psm_shp_id = '".$this->kill->getVictimShip()->getClass()->getID()."'");
			$qry->execute("UPDATE kb3_pilots SET plt_kpoints = plt_kpoints - '".$this->kill->getKillPoints()."' WHERE plt_id = '".$pilotid."'");
		}

		//make sure involved count is shown correctly (it's generated before this class is loaded)
		header("Location: ?a=kill_detail&kll_id=".$this->killID_);
		exit;
	}
}
?>
