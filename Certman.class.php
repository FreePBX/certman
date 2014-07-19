<?php
// vim: set ai ts=4 sw=4 ft=php:
/**
 * This is the Certificate Manager Object.
 *
 * License for all code of this FreePBX module can be found in the license file inside the module directory
 * Copyright 2006-2014 Schmooze Com Inc.
 */

class Certman implements BMO {
	private $defaults = array(
		"sip" => array(
			"dtlsenable" => "no",
			"dtlsverify" => "fingerprint",
			"dtlscertfile" => "",
			"dtlscafile" => "",
			"dtlssetup" => "actpass",
			"dtlsrekey" => "0"
		),
		"pjsip" => array(
			"media_encryption" => "dtls",
			"dtls_verify" => "fingerprint",
			"dtls_cert_file" => "",
			"dtls_ca_file" => "",
			"dtls_setup" => "actpass",
			"dtls_rekey" => "0"
		)
	);

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
		$sql = "CREATE TABLE `certman_mapping` (
					`id` int(11) NOT NULL,
					`cid` int(11) DEFAULT NULL,
					`verify` varchar(45) DEFAULT NULL,
					`setup` varchar(45) DEFAULT NULL,
					`rekey` int(11) DEFAULT NULL,
					PRIMARY KEY (`id`)";
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
	public function myDialplanHooks() {
		return true;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		global $core_conf;
		$this->FreePBX->Core;

		foreach($this->getAllDTLSOptions() as $device) {
			$o = $this->FreePBX->Core->getDevice($device['id']);
			$cert = $this->getCertificateDetails($device['cid']);
			$ca = $this->getCADetails($cert['caid']);
			switch($o['tech']) {
				case 'sip':
					$core_conf->addSipAdditional($device['id'],'dtlsenable','yes');
					$core_conf->addSipAdditional($device['id'],'dtlsverify',$device['verify']);
					$core_conf->addSipAdditional($device['id'],'dtlscertfile',$cert['files']['pem']);
					$core_conf->addSipAdditional($device['id'],'dtlscafile',$ca['files']['crt']);
					$core_conf->addSipAdditional($device['id'],'dtlssetup',$device['setup']);
					$core_conf->addSipAdditional($device['id'],'dtlsrekey',$device['rekey']);
				break;
				case 'pjsip':
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'media_encryption', 'dtls');
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_verify', $device['verify']);
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_cert_file', $cert['files']['pem']);
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_ca_file', $ca['files']['crt']);
				break;
			}
		}
	}

	public function hook() {
		global $core_conf;
	}
	public function getAllDTLSOptions() {
		$sql = "SELECT * FROM certman_mapping";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}
	public function getDTLSOptions($device) {
		$sql = "SELECT * FROM certman_mapping WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$data['enable'] = 'yes';
		} else {
			return array(
				"enable" => "no",
				"verify" => "fingerprint",
				"certificate" => "",
				"setup" => "actpass",
				"rekey" => "0"
			);
		}
		return $data;
	}
	public function addDTLSOptions($device,$data) {
		$sql = "REPLACE INTO certman_mapping (id, cid, verify, setup, rekey) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device,$data['certificate'],$data['verify'],$data['setup'],$data['rekey']));
	}
	public function removeDTLSOptions($device) {
		$sql = "DELETE FROM certman_mapping WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device));
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
	public function getCADetails($caid) {
		$sql = "SELECT * from certman_cas WHERE uid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$data['files']['crt'] = $this->PKCS->getKeysLocation().'/'.$data['basename'].'.crt';
		}
		return $data;
	}
	public function generateCertificate($caid,$base,$description,$passphrase=null) {
		$ca = $this->getCADetails($caid);
		$this->PKCS->createCert($base,$ca['basename'],$ca['passphrase']);
		$sql = "INSERT INTO certman_certs (`caid`, `basename`, `description`) VALUES (?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid,$base,$description));
	}
	public function getAllManagedCertificates() {
		$sql = "SELECT * FROM certman_certs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}
	public function getCertificateDetails($cid) {
		$sql = "SELECT * from certman_certs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($cid));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$data['files']['pem'] = $this->PKCS->getKeysLocation().'/'.$data['basename'].'.pem';
		}
		return $data;
	}
	public function removeCertificate($cid) {
		$cert = $this->getCertificateDetails($cid);
		$this->PKCS->removeCert($cert['basename']);
		$sql = "DELETE FROM certman_certs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($cid));
		return true;
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
