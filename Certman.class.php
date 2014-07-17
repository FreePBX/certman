<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the Certificate Manager Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */

class Certman implements BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null)
			throw new Exception("Not given a FreePBX Object");

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}
	public function install() {

	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function doConfigPageInit($page){
		return true;
	}
	public function checkCAexists() {
		$o = $this->FreePBX->PKCS->getAllAuthorityFiles();
		return !empty($o);
	}
}
