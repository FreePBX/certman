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
		$this->PKCS = $this->FreePBX->PKCS;
	}
	public function install() {
		$sql = "CREATE TABLE `certman_cas` (
					`uid` INT NOT NULL AUTO_INCREMENT,
					`basename` VARCHAR(255) NOT NULL,
					`cn` VARCHAR(255) NOT NULL,
					`on` VARCHAR(255) NOT NULL,
					`passphrase` VARCHAR(255) NULL,
					`salt` VARCHAR(255) NULL,
					PRIMARY KEY (`uid`)),
					UNIQUE KEY `basename_UNIQUE` (`basename`)";
		$sql = "CREATE TABLE `certman_certs` (
					`cid` INT NOT NULL AUTO_INCREMENT,
					`caid` INT NOT NULL,
					`basename` VARCHAR(45) NOT NULL,
					`description` VARCHAR(255) NULL,
					PRIMARY KEY (`cid`)),
					UNIQUE KEY `basename_UNIQUE` (`basename`)";
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
		$o = $this->PKCS->getAllAuthorityFiles();
		return !empty($o);
	}
	public function getAllManagedCAs() {
		$sql = "SELECT * FROM certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}
	public function generateCA($commonname, $orgname, $passphrase, $saveph) {
		try {
			$this->PKCS->createConfig('ca',$commonname,$orgname);
			$this->PKCS->createCA('ca',$passphrase);
		} catch(Exception $e) {

		}
		if(!$saveph) {
			$passphrase = '';
			$key = '';
		}
		$sql = "INSERT INTO certman_cas (`basename`, `cn`, `on`, `passphrase`, `salt`) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array('ca', $commonname,$orgname,$passphrase,'1'));
	}
	public function generateCert($caid,$base,$description,$passphrase=null) {
		$sql = "INSERT INTO certman_certs (`caid`, `basename`, `description`) VALUES (?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid,$base,$description));
	}
	public function removeCA() {
		try {
			$this->PKCS->removeCA();
			$this->PKCS->removeConfig();
		} catch(Exception $e) {

		}
		$sql = "TRUNCATE certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
	}
}
