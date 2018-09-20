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
	 * Is port 80 ('letsencrypt') open to the world?
	 *
	 * @return bool
	 */
	public function portIsOpen() {
		// Just in case..
		if (!$this->fw || !$this->fwobj) {
			return false;
		}

		// If they're somehow running an old firewall, it'll throw here.
		try {
			$le = $this->fwobj->getService("letsencrypt");
		} catch (\Exception $e) {
			return false;
		}

		if (!empty($le['zones']) && in_array("external", $le['zones'])) {
			return true;
		}
		return false;
	}
}



