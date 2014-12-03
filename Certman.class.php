<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//

class Certman implements BMO {
	/* Asterisk Defaults */
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

	/**
	 * Used to setup the database
	 */
	public function install() {
		$sql = "CREATE TABLE IF NOT EXISTS `certman_cas` (
					`uid` INT NOT NULL AUTO_INCREMENT,
					`basename` VARCHAR(255) NOT NULL,
					`cn` VARCHAR(255) NOT NULL,
					`on` VARCHAR(255) NOT NULL,
					`passphrase` VARCHAR(255) NULL,
					`salt` VARCHAR(255) NULL,
					PRIMARY KEY (`uid`),
					UNIQUE KEY `basename_UNIQUE` (`basename`))";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$sql = "CREATE TABLE IF NOT EXISTS `certman_certs` (
					`cid` INT NOT NULL AUTO_INCREMENT,
					`caid` INT NOT NULL,
					`basename` VARCHAR(45) NOT NULL,
					`description` VARCHAR(255) NULL,
					PRIMARY KEY (`cid`),
					UNIQUE KEY `basename_UNIQUE` (`basename`))";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$sql = "CREATE TABLE IF NOT EXISTS `certman_mapping` (
					`id` int(11) NOT NULL,
					`cid` int(11) DEFAULT NULL,
					`verify` varchar(45) DEFAULT NULL,
					`setup` varchar(45) DEFAULT NULL,
					`rekey` int(11) DEFAULT NULL,
					PRIMARY KEY (`id`))";
		$sth = $this->db->prepare($sql);
		$sth->execute();

		if(!$this->checkCAexists()) {
			out(_("No Certificates exist"));
			outn(_("Generating default CA..."));
			$caid = $this->generateCA('ca',gethostname(),gethostname(),openssl_random_pseudo_bytes(32),true);
			out(_("Done!"));
			outn(_("Generating default certificate..."));
			$this->generateCertificate($caid,_("default"),_("default certificate generated at install time"));
			out(_("Done!"));
		}
		return true;
	}

	public function uninstall() {
		$this->removeCA();
		$sql = "DROP TABLE certman_mapping";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$sql = "DROP TABLE certman_certs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$sql = "DROP TABLE certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return true;
	}

	public function backup(){}
	public function restore($backup){}
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
			if(empty($cert) || empty($ca)) {
				continue;
			}
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

	/**
	 * Get all DTLS Settings for each extension
	 */
	public function getAllDTLSOptions() {
		$sql = "SELECT * FROM certman_mapping";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Get DTLS Settings for a particular extension
	 * @param {int} $device The device/extension number
	 */
	public function getDTLSOptions($device) {
		if(!isset($device)) {
			return false;
		}
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

	/**
	 * Check to make sure said device has a valid certificate
	 * @param {int} $device The Extension/Device Number
	 */
	public function validDTLSDevice($device) {
		$sql = "SELECT * FROM certman_mapping WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			if(!empty($data['cid'])) {
				$cert = $this->getCertificateDetails($data['cid']);
				if(!empty($cert) && $this->checkCAexists()) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Add DTLS Options for a device/extension
	 * @param {int} $device The Device/Extension Number
	 * @param {array} $data   An array of defined options
	 */
	public function addDTLSOptions($device,$data) {
		$sql = "REPLACE INTO certman_mapping (id, cid, verify, setup, rekey) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device,$data['certificate'],$data['verify'],$data['setup'],$data['rekey']));
	}

	/**
	 * Remove the DTLS Options for this device
	 * @param {int} $device The Device/Extension Number
	 */
	public function removeDTLSOptions($device) {
		$sql = "DELETE FROM certman_mapping WHERE id = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($device));
	}

	/**
	 * Check to see if *any* certificate authority exists
	 */
	public function checkCAexists() {
		$o = $this->PKCS->getAllAuthorityFiles();
		return !empty($o);
	}

	/**
	 * Get all Certificate Manager Managed Certificate Authorities
	 */
	public function getAllManagedCAs() {
		$sql = "SELECT * FROM certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Generate a New Certificate Authority
	 * @param {string} $basename   The basename of the file to generate
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname    The organization name
	 * @param {string} $passphrase The password, if null then the certificate will be passwordless (insecure)
	 * @param {bool} $saveph     Whether to save the password above in the database
	 */
	public function generateCA($basename, $commonname, $orgname, $passphrase, $saveph) {
		try {
			$this->generateConfig($basename,$commonname,$orgname);
			$this->PKCS->createCA($basename,$passphrase);
		} catch(Exception $e) {
			return $e->getMessage();
		}
		if(!$saveph) {
			$passphrase = '';
			$key = '';
		}
		$id = $this->saveCA($basename,$commonname,$orgname,$passphrase);
		return $id;
	}

	/**
	 * Generate OpenSSL Template Configs
	 * @param {string} $basename   The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname    The organization name
	 */
	public function generateConfig($basename,$commonname,$orgname) {
		$this->PKCS->createConfig($basename,$commonname,$orgname);
	}

	/**
	 * Save the Certificate Authority Information into the Database
	 * @param {string} $basename   The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname    The organization name
	 * @param {string} $passphrase The passphrase (to be encrypted)
	 */
	public function saveCA($basename,$commonname,$orgname,$passphrase) {
		$sql = "INSERT INTO certman_cas (`basename`, `cn`, `on`, `passphrase`, `salt`) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($basename, $commonname,$orgname,$passphrase,'1'));
		return $this->db->lastInsertId();
	}

	/**
	 * Get Certificate Authority Details
	 * @param {int} $caid The Certificate Authority ID
	 */
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

	/**
	 * Generate A Certificate Based on a Certificate Authority
	 * @param {int} $caid            The Managed Certificate Authority ID
	 * @param {string} $base            The base name to generate
	 * @param {string} $description     Description of this certificate
	 * @param {string} $passphrase=null The provided passphrase,
	 *                                  used if the CA requires a passphrase but
	 *                                  it was not stored internally
	 */
	public function generateCertificate($caid,$base,$description,$passphrase=null) {
		if($this->checkCertificateName($base)) {
			return _('Certificate Already Exists');
		}
		$ca = $this->getCADetails($caid);
		$passphrase = !empty($passphrase) ? $passphrase : $ca['passphrase'];
		try {
			$this->PKCS->createCert($base,$ca['basename'],$passphrase);
		} catch(\Exception $e) {
			return $e->getMessage();
		}
		$this->saveCertificate($caid,$base,$description);
		return true;
	}

	/**
	 * Save Certificate Information into the Database
	 * @param {int} $caid        The Certificate Authority ID
	 * @param {string} $base        The base name of the certificate
	 * @param {string} $description The description of the certificate
	 */
	public function saveCertificate($caid,$base,$description) {
		if($this->checkCertificateName($base)) {
			return false;
		}
		$sql = "INSERT INTO certman_certs (`caid`, `basename`, `description`) VALUES (?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid,$base,$description));
	}

	/**
	 * Get all Managed Certificates (Excluding any Authorities)
	 */
	public function getAllManagedCertificates() {
		$sql = "SELECT * FROM certman_certs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Get details about a specific Authority
	 * @param {int} $cid The Certificate ID
	 */
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

	/**
	 * Check Certificate Name to see if it exists
	 * @param {string} $name Certificate Base Name
	 * @return boolean True if it exists, false if it doesnt
	 */
	public function checkCertificateName($name) {
		$sql = "SELECT * FROM certman_certs WHERE basename = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($name));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		return !empty($data);
	}

	/**
	 * Remove a Certificate
	 * @param {int} $cid The Certificate ID to remove
	 */
	public function removeCertificate($cid) {
		$cert = $this->getCertificateDetails($cid);
		$this->PKCS->removeCert($cert['basename']);
		$sql = "DELETE FROM certman_certs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($cid));
		return true;
	}

	/**
	 * Remove a Certificate Authority and all of it's child certificates
	 */
	public function removeCA() {
		try {
			$this->PKCS->removeCA();
			$this->PKCS->removeConfig();
		} catch(Exception $e) {

		}
		$sql = "TRUNCATE certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		foreach($this->getAllManagedCertificates() as $cert) {
			$this->removeCertificate($cert['cid']);
		}
	}

	public function updateCert($cid,$name,$description) {
		$o = $this->getCertificateDetails($cid);
		if(!empty($o)) {
			$loc = $this->PKCS->getKeysLocation();
			foreach(glob($loc . "/".$o['basename'].".*") as $file) {
				$info = pathinfo($file);
				if(file_exists($loc . "/" . $name . "." . $info['extension'])) {
					return sprintf(_("%s Already Exists at that location!"), $info['basename']);
				}
			}
			foreach(glob($loc . "/".$o['basename'].".*") as $file) {
				$info = pathinfo($file);
				rename($file,$loc . "/" . $name . "." . $info['extension']);
			}
			$sql = "UPDATE certman_certs SET basename = ?, description = ? WHERE cid = ?";
			$sth = $this->db->prepare($sql);
			$sth->execute(array($name,$description,$cid));
			return true;
		} else {
			return _('Certificate ID is unknown!');
		}
	}
}
