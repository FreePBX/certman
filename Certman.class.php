<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//
namespace FreePBX\modules;
class Certman implements \BMO {
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
	private $message = "";

	public function __construct($freepbx = null) {
		if ($freepbx == null)
			throw new \Exception("Not given a FreePBX Object");

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->FreePBX->PJSip = $this->FreePBX->Core->getDriver('pjsip');
		$this->PKCS = $this->FreePBX->PKCS;
		$this->PKCS->timeout = 240; //because of piiiiiis
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
					`id` varchar(20) NOT NULL,
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

			// See if we can random
			if (function_exists('openssl_random_pseudo_bytes')) {
				$passwd = base64_encode(openssl_random_pseudo_bytes(32));
			} else {
				$passwd = "";
			}

			$caid = $this->generateCA('ca', gethostname(), gethostname(), $passwd, true);
			out(_("Done!"));
			outn(_("Generating default certificate..."));
			$this->generateCertificate($caid,_("default"),_("default certificate generated at install time"));
			out(_("Done!"));
		}

		global $db;
		$info = $db->getRow('SHOW COLUMNS FROM certman_mapping WHERE FIELD = "id"', DB_FETCHMODE_ASSOC);
		if($info['Type'] != "varchar(20)") {
			$sql = "ALTER TABLE `certman_mapping` CHANGE COLUMN `id` `id` VARCHAR(20) NOT NULL";
			$result = $db->query($sql);
			if (\DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
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
		$request = $_REQUEST;
		$request['action'] = !empty($request['action']) ? $request['action'] : "";
		$request['replace'] = !empty($request['replace']) ? $request['replace'] : "";
		switch($request['action']) {
			case 'ca':
				$type = !empty($request['type']) ? $request['type'] : "";
				$type = (!empty($_FILES["privatekey"]['name']) && $_FILES["privatekey"]['name'] != '') ? 'upload' : $type;
				$new = false;
				if($request['replace'] === 'replace'){
					$this->removeCA();
				}
				switch($type) {
					case 'generate':
						$sph = (!empty($request['savepassphrase']) && $request['savepassphrase'] == 'yes') ? true : false;
						try {
							$out = $this->generateCA('ca',$request['hostname'],$request['orgname'],$request['passphrase'],$sph);
							$new = true;
						} catch(\Exception $e) {
							$this->message = array('type' => 'danger', 'message' => nl2br($e->getMessage()));
						}
					break;
					case 'upload':
						if ($_FILES["privatekey"]["error"] > 0) {
							$this->message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["privatekey"]["error"]));
							break;
						} else {
							$pi = pathinfo($_FILES["privatekey"]['name']);
							if($pi['extension'] != 'key') {
								$this->message = array('type' => 'danger', 'message' => _('Private key doesnt appear to be a key file'));
								break;
							} else {
								move_uploaded_file($_FILES["privatekey"]["tmp_name"],'/etc/asterisk/keys/ca.key');
							}
						}

						if ($_FILES["certificate"]["error"] > 0) {
							$this->message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["certificate"]["error"]));
							break;
						} else {
							$pi = pathinfo($_FILES["certificate"]['name']);
							if($pi['extension'] != 'crt') {
								$this->message = array('type' => 'danger', 'message' => _('Certificate doesnt appear to be a crt file'));
								break;
							} else {
								move_uploaded_file($_FILES["certificate"]["tmp_name"],'/etc/asterisk/keys/ca.crt');
							}
						}
						$this->generateConfig('ca',$request['hostname'],$request['orgname']);
						$this->saveCA('ca',$request['hostname'],$request['orgname'],$request['passphrase']);
						$this->message = array('type' => 'success', 'message' => _('Successfully generated a Certificate Authority'));
						$new = true;
					break;
					case 'delete':
						$status = $this->removeCA();
						if($status) {
							$this->message = array('type' => 'success', 'message' => _('Successfully deleted the Certificate Authority'));
						}
					break;
					default:
					break;
				}
				$caExists = $this->checkCAexists();
				$html = load_view(__DIR__.'/views/ca.php',array('caExists' => $caExists, 'message' => $this->message, 'new' => $new));
			break;
			case 'new':
				$cas = $this->getAllManagedCAs();
				if(!empty($cas)) {
					if(!empty($request['name']) && $this->checkCertificateName($request['name'])) {
						$this->message = array('type' => 'danger', 'message' => _('Certificate already exists'));
					} else {
						$type = !empty($request['type']) ? $request['type'] : "";
						$type = (!empty($_FILES["privatekey"]['name']) && $_FILES["privatekey"]['name'] != '') ? 'upload' : $type;
						switch($type) {
							case 'generate':
								$ca = $this->getCADetails($request['ca']);
								$passphrase = !empty($request['passphrase']) ? $request['passphrase'] : $ca['passphrase'];
								$out = $this->generateCertificate($request['ca'],$request['name'],$request['description'],$passphrase);
								if($out !== true) {
									$this->message = array('type' => 'danger', 'message' => nl2br($out));
								} else {
									$this->message = array('type' => 'success', 'message' => _('Successfully generated a Certificate'));
									$this->goto = 'overview';
								}
							break;
							case 'upload':
								if ($_FILES["privatekey"]["error"] > 0) {
									$this->message = array('type' => 'danger', 'message' => _('Error uploading ' . $_FILES["privatekey"]["error"]));
									break;
								} else {
									$pi = pathinfo($_FILES["privatekey"]['name']);
									if($pi['extension'] != 'key') {
										$this->message = array('type' => 'danger', 'message' => _('Private key doesnt appear to be a key file'));
										break;
									} else {
										move_uploaded_file($_FILES["privatekey"]["tmp_name"],'/etc/asterisk/keys/'.$request['name'].'.key');
									}
								}

								if ($_FILES["certificate"]["error"] > 0) {
									$this->message = array('type' => 'danger', 'message' => _('Error uploading ' . $_FILES["certificate"]["error"]));
									break;
								} else {
									$pi = pathinfo($_FILES["certificate"]['name']);
									if($pi['extension'] != 'crt') {
										$this->message = array('type' => 'danger', 'message' => _('Certificate does not appear to be a .crt file'));
										break;
									} else {
										move_uploaded_file($_FILES["certificate"]["tmp_name"],'/etc/asterisk/keys/'.$request['name'].'.crt');
									}
								}
								$this->saveCertificate($request['ca'],$request['name'],$request['description']);
								$this->message = array('type' => 'success', 'message' => _('Successfully uploaded a certificate'));

							break;
							default:
							break;
						}
					}
				}
			break;
			case 'view':
				$type = !empty($request['type']) ? $request['type'] : "";
				switch($type){
					case 'update':
						$out = $this->updateCert($request['cid'],$request['name'],$request['description']);
						if($out !== true) {
							$this->message = array('type' => 'danger', 'message' => $out);
						} else {
							$this->message = array('type' => 'success', 'message' => _('Updated Certificate'));
						}
					break;
					default:
					break;
				}
			break;
			case 'delete':
				$this->removeCertificate($_REQUEST['id']);
				$this->message = array('type' => 'success', 'message' => _('Deleted Certificate'));
			break;

		}
		return true;
	}
	public function myShowPage($view=''){
		$view = !empty($this->goto) ? $this->goto : $view;
		$request = $_REQUEST;
		switch($view){
			case 'view':
				$cert = $this->getCertificateDetails($request['id']);
				echo load_view(__DIR__.'/views/view.php',array('cert' => $cert, 'message' => $this->message));
			break;
			case 'new':
				$cas = $this->getAllManagedCAs();
				if($cas){
					echo load_view(__DIR__.'/views/new.php',array('cas' => $cas, 'message' => $this->message));
				}
			break;
			case 'overview':
			default:
				$certs = $this->getAllManagedCertificates();
				$caExists = $this->checkCAexists();
				echo load_view(__DIR__.'/views/overview.php',array('certs' => $certs, 'caExists' => $caExists, 'message' => $this->message));
			break;
		}
	}
	public function getActionBar($request) {
		$buttons = array();
		$request['action'] = !empty($request['action']) ? $request['action'] : "";
		switch($request['display']) {
			case 'certman':
				$buttons = array(
					'delete' => array(
						'name' => 'delete',
						'id' => 'Delete',
						'value' => _('Delete')
						),
						'reset' => array(
							'name' => 'reset',
							'id' => 'Reset',
							'value' => _('Reset')
						),
						'submit' => array(
							'name' => 'submit',
							'id' => 'Submit',
							'value' => _('Submit')
						)
					);
					switch($request['action']){
						case 'view':
							$buttons['submit']['value'] = _('Update Certificate');
							$buttons['delete']['value'] = _('Delete Certificate');
						break;
						case 'new':
							unset($buttons['delete']);
							$buttons['submit']['value'] = _('Generate Certificate');
						break;
						default:
							$buttons['submit']['class'] = 'hidden';
							$buttons['reset']['class'] = 'hidden';
							$buttons['delete']['class'] = 'hidden';
						break;
					}
				break;
			}
		return $buttons;
	}

	public function myDialplanHooks() {
		return true;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		global $core_conf;

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
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_setup', $device['setup']);
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_rekey', $device['rekey']);
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
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
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
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
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
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
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
	 * @param {array} $data	 An array of defined options
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
		$z = $this->getAllManagedCAs();
		if(empty($o) && !empty($z)) {
			//files are missing from hard drive. run delete
			$this->removeCA();
			return false;
		}
		return (empty($o) && empty($z)) ? false : true;
	}

	/**
	 * Get all Certificate Manager Managed Certificate Authorities
	 */
	public function getAllManagedCAs() {
		$sql = "SELECT * FROM certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Generate a New Certificate Authority
	 * @param {string} $basename	 The basename of the file to generate
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
	 * @param {string} $passphrase The password, if null then the certificate will be passwordless (insecure)
	 * @param {bool} $savepass		 Whether to save the password above in the database
	 */
	public function generateCA($basename, $commonname, $orgname, $passphrase, $savepass) {
		$this->generateConfig($basename,$commonname,$orgname);
		$this->PKCS->createCA($basename,$passphrase);
		if ($savepass) {
			return $this->saveCA($basename, $commonname, $orgname, $passphrase);
		} else {
			return $this->saveCA($basename, $commonname, $orgname, '');
		}
	}

	/**
	 * Generate OpenSSL Template Configs
	 * @param {string} $basename	 The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
	 */
	public function generateConfig($basename,$commonname,$orgname) {
		$this->PKCS->createConfig($basename,$commonname,$orgname);
	}

	/**
	 * Save the Certificate Authority Information into the Database
	 * @param {string} $basename	 The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
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
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$data['files']['crt'] = $this->PKCS->getKeysLocation().'/'.$data['basename'].'.crt';
		}
		return $data;
	}

	/**
	 * Generate A Certificate Based on a Certificate Authority
	 * @param {int} $caid						The Managed Certificate Authority ID
	 * @param {string} $base						The base name to generate
	 * @param {string} $description		 Description of this certificate
	 * @param {string} $passphrase=null The provided passphrase,
	 *																	used if the CA requires a passphrase but
	 *																	it was not stored internally
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
	 * @param {int} $caid				The Certificate Authority ID
	 * @param {string} $base				The base name of the certificate
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
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Get details about a specific Authority
	 * @param {int} $cid The Certificate ID
	 */
	public function getCertificateDetails($cid) {
		$sql = "SELECT * from certman_certs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($cid));
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
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
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
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
		} catch(\Exception $e) {
			return false;
		}
		$sql = "TRUNCATE certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		foreach($this->getAllManagedCertificates() as $cert) {
			$this->removeCertificate($cert['cid']);
		}
		return true;
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
	public function getRightNav($request) {
		if(isset($request['action']) && !empty($request['action'])){
			return load_view(__DIR__."/views/rnav.php",array('caExists' => $this->checkCAexists()));
		}
	}
	public function ajaxRequest($req, &$setting) {
			 switch ($req) {
					 case 'getJSON':
							 return true;
					 break;
					 default:
							 return false;
					 break;
			 }
	 }
	public function ajaxHandler(){
		switch ($_REQUEST['command']) {
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'grid':
						return $this->getAllManagedCertificates();
					break;

					default:
						return false;
					break;
				}
			break;

			default:
				return false;
			break;
		}
	}
}
