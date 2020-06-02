<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//
namespace FreePBX\modules\Certman;

/**
 * Implements a trivial API for use with Certman
 */
class FirewallAPI {

	/** Is firewall available on this machine? */
	private $fw = false;

	/** Firewall object */
	private $fwobj;

	/** Default hosts to allow in to the 'internal' zone */
	private $knownhosts = array ("outbound1.letsencrypt.org", "outbound2.letsencrypt.org", "mirror1.freepbx.org", "mirror2.freepbx.org");

	public function __construct() {
		// Is firewall enabled and active?
		try {
			$this->fwobj = \FreePBX::Firewall();
			$this->fw = $this->fwobj->isEnabled();
		} catch (\Exception $e) {
			// Firewall not active, or not enabled, don't do anything
			return;
		}
	}

	/**
	 * Is firewall available on this machine?
	 *
	 * @return bool
	 */
	public function isAvailable() {
		return $this->fw;
	}
	
	/**
	 * getAdvancedSettings
	 *
	 * @return void
	 */
	public function getAdvancedSettings(){
		if($this->fw){
			return $this->fwobj->getConfig("advancedsettings");
		}
		return false;
	}
		
	
	/**
	 * LE_Rules_Status
	 *
	 * @param  string $status
	 * @return bool
	 */
	public function LE_Rules_Status($status = 'disabled'){
		if(!preg_match('/disabled$|enabled$/', $status)){
			return false;
		}
		$this->fixeLeFilter($status);
		$i	= 0;
		$fw	= false;

		/**
		 * We are waiting Firewall up. 
		 * Set timeout at 10" max.
		 */
		while ($fw == false && $i < 10){
			$i++;
			$fw = $this->fwobj->getConfig("status");
			sleep(1);
		}
		return $fw;
	}
	
	/**
	 * fixeLeFilter
	 *
	 * @return void
	 */
	public function fixeLeFilter($status = 'disabled'){
		$adv = $this->getAdvancedSettings();
		$adv["lefilter"] = $status;
		$this->fwobj->setConfig("advancedsettings", $adv);
		$this->fwobj->restartFirewall();
	}
}



