<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//
namespace FreePBX\modules\Certman;
class Logger { function __call($name, $arguments) { dbug(date('Y-m-d H:i:s')." [$name] ${arguments[0]}"); }}
namespace FreePBX\modules;
include 'vendor/autoload.php';
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
					`caid` INT NULL,
					`basename` VARCHAR(45) NOT NULL,
					`description` VARCHAR(255) NULL,
					`type` VARCHAR(2) NOT NULL DEFAULT 'ss',
					`default` TINYINT NOT NULL DEFAULT 0,
					PRIMARY KEY (`cid`),
					UNIQUE KEY `basename_UNIQUE` (`basename`))";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$sql = "CREATE TABLE IF NOT EXISTS `certman_csrs` (
					`cid` INT NOT NULL AUTO_INCREMENT,
					`basename` VARCHAR(45) NOT NULL,
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

		global $db;
		$info = $db->getRow('SHOW COLUMNS FROM certman_mapping WHERE FIELD = "id"', DB_FETCHMODE_ASSOC);
		if($info['Type'] != "varchar(20)") {
			$sql = "ALTER TABLE `certman_mapping` CHANGE COLUMN `id` `id` VARCHAR(20) NOT NULL";
			$result = $db->query($sql);
			if (\DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
		$info = $db->getRow('SHOW COLUMNS FROM certman_certs WHERE FIELD = "type"', DB_FETCHMODE_ASSOC);
		if(empty($info)) {
			$sql = "ALTER TABLE `certman_certs` ADD COlUMN `type` varchar (2) NOT NULL DEFAULT 'ss'";
			$result = $db->query($sql);
			if (\DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
		$info = $db->getRow('SHOW COLUMNS FROM certman_certs WHERE FIELD = "default"', DB_FETCHMODE_ASSOC);
		if(empty($info)) {
			$sql = "ALTER TABLE `certman_certs` ADD COlUMN `default` TINYINT NOT NULL DEFAULT 0";
			$result = $db->query($sql);
			if (\DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}
		$info = $db->getRow('SHOW COLUMNS FROM certman_certs WHERE FIELD = "caid"', DB_FETCHMODE_ASSOC);
		if($info['Null'] == "NO") {
			$sql = "ALTER TABLE `certman_certs` CHANGE COLUMN `caid` `caid` INT NULL";
			$result = $db->query($sql);
			if (\DB::IsError($result)) {
				die_freepbx($result->getDebugInfo());
			}
		}

		$certs = $this->getAllManagedCertificates();
		if(empty($certs)) {
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
			$this->generateCertificate($caid,_("default"),_("default self signed certificate generated at install time"));
			out(_("Done!"));
		}

		return true;
	}

	public function uninstall() {
		$this->removeCSR();
		$this->removeCA();
		$certs = $this->getAllManagedCertificates();
		foreach($certs as $cert) {
			$this->removeCertificate($cert['cid']);
		}
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
		$request['certaction'] = !empty($request['certaction']) ? $request['certaction'] : "";
		switch($request['certaction']) {
			case "importlocally":
				$processed = $this->importLocalCertificates();
				if(!empty($processed)) {
					$status = '';
					foreach($processed as $p) {
						if(!$p['status']) {
							$status .= $p['file'].": ".$p['error'] . "</br>";
						}
					}
					if(!empty($status)) {
						$this->message = array('type' => 'danger', 'message' => $status);
					} else {
						$this->message = array('type' => 'success', 'message' => _("Successfully imported certificates"));
					}
				} else {
					$this->message = array('type' => 'info', 'message' => _("No certificates to import"));
				}
			break;
			case "edit":
				switch($request['type']) {
					case "up":
						$cert = $this->getCertificateDetails($_POST['cid']);
						if(!empty($cert)) {
							$name = $cert['basename'];
							$removeCSR = false;
							if(empty($_POST['privatekey']) && !empty($_POST['csrref'])) {
								$csr = $this->getCSRDetails($_POST['csrref']);
								if(empty($csr['files']['key'])) {
									//corruption
									$this->removeCSR();
									$this->message = array('type' => 'danger', 'message' => _('No Private key to reference. Try generating a CSR first.'));
									break 2;
								}
								$pkey = file_get_contents($csr['files']['key']);
								$removeCSR = true;
							} elseif(!empty($_POST['privatekey'])) {
								$pkey = $_POST['privatekey'];
							} else {
								if(empty($cert['files']['key'])) {
									$this->message = array('type' => 'danger', 'message' => _('No Private key to reference.'));
									break 2;
								}
								$pkey = file_get_contents($cert['files']['key']);
							}

							try {
								$status = $this->importCertificate($name,$pkey,$_POST['signedcert'],$_POST['certchain'],$_POST['passphrase']);
							} catch(\Exception $e) {
								$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error importing the certificate: %s'),$e->getMessage()));
								break;
							}

							$this->update($cert['cid'],$_POST['description']);
							if($removeCSR) {
								$this->removeCSR();
							}
							$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
						}
					break;
					case "le":
						$cert = $this->getCertificateDetails($_POST['cid']);
						if(!empty($cert)) {
							try {
								$this->updateLE($cert['basename'],$_POST['C'],$_POST['ST']);
							} catch(\Exception $e) {
								$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error updating the certificate: %s'),$e->getMessage()));
								break;
							}
							$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
							$this->update($cert['cid'],$_POST['description']);
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Certificate is invalid'));
						}
					break;
					case "ss":
						$cert = $this->getCertificateDetails($_POST['cid']);
						if(!empty($cert)) {
							$this->update($cert['cid'],$_POST['description']);
							$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Certificate is invalid'));
						}
					break;
				}
			break;
			case "add":
				switch($request['type']) {
					case "le":
						$host = basename($_POST['host']);
						try{
							$this->updateLE($host,$_POST['C'],$_POST['ST']);
						} catch(\Exception $e) {
							$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error updating the certificate: %s'),$e->getMessage()));
							break 2;
						}
						$this->saveCertificate(null,$host,$_POST['description'],'le');
						$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
					break;
					case "up":
						$name = basename($_POST['name']);
						if($this->checkCertificateName($name)) {
							$this->message = array('type' => 'danger', 'message' => _('Certificate name is already in use'));
							break;
						}
						$removeCSR = false;
						if(empty($_POST['privatekey']) && !empty($_POST['csrref'])) {
							$csr = $this->getCSRDetails($_POST['csrref']);
							if(empty($csr['files']['key'])) {
								//corruption
								$this->removeCSR();
								$this->message = array('type' => 'danger', 'message' => _('No Private key to reference. Try generating a CSR first.'));
								break;
							}
							$pkey = file_get_contents($csr['files']['key']);
							$removeCSR = true;
						} elseif(!empty($_POST['privatekey'])) {
							$pkey = $_POST['privatekey'];
						} else {
							$this->message = array('type' => 'danger', 'message' => _('No Private key to reference. Try generating a CSR first.'));
							break;
						}
						try {
							$status = $this->importCertificate($name,$pkey,$_POST['signedcert'],$_POST['certchain'],$_POST['passphrase']);
						} catch(\Exception $e) {
							$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error importing the certificate: %s'),$e->getMessage()));
						}
						$this->saveCertificate(null,$name,$_POST['description'],'up');
						if($removeCSR) {
							$this->removeCSR();
						}
						$this->message = array('type' => 'success', 'message' => _('Added new certificate'));
					break;
					case "ss":
						if(empty($request['caid'])) {
							//generate new cert authority
							$this->generateConfig('ca',$request['hostname'],$request['orgname']);
							$sph = (!empty($request['savepassphrase']) && $request['savepassphrase'] == 'yes') ? true : false;
							$caid = $this->generateCA('ca',$request['hostname'],$request['orgname'],$request['passphrase'],$sph);
							if(empty($caid)) {
								$this->message = array('type' => 'danger', 'message' => _('Unable to generate Certificate Authority'));
								break;
							}
						} else {
							//get old cert authority
							$dat = $this->getAllManagedCAs();
							if(empty($dat[0]['uid'])) {
								$this->message = array('type' => 'danger', 'message' => _('Unable to find Certificate Authority'));
								break;
							}
							$caid = $dat[0]['uid'];
						}
						$request['passphrase'] = !empty($request['passphrase']) ? $request['passphrase'] : null;
						$this->generateCertificate($caid,$request['name'],$request['description'],$request['passphrase']);
						$this->message = array('type' => 'success', 'message' => _('Added new certificate'));
					break;
					case "csr":
						$location = $this->PKCS->getKeysLocation();
						$name = basename($_POST['name']);
						if($this->checkCertificateName($name) || $this->checkCSRName($name)) {
							$this->message = array('type' => 'danger', 'message' => _('Certificate name is already in use'));
							break 2;
						}
						$valid = array("CN","O","OU","C","ST","L");
						$params = array();
						foreach($_POST as $key => $value) {
							if(in_array($key,$valid)) {
								$params[$key] = $value;
							}
						}
						try {
							$this->PKCS->createCSR($name, $params);
							$this->saveCSR($name);
						} catch(\Exception $e) {
							$this->message = array('type' => 'danger', 'message' => sprintf(_('Unable to create CSR: %s'),$e->getMessage()));
							break;
						}
						$this->message = array('type' => 'success', 'message' => _('Added new certificate signing request'));
					break;
				}
			break;
			case "delete":
				switch($request['type']) {
					case 'ca':
						$status = $this->removeCA();
						if($status) {
							$this->message = array('type' => 'success', 'message' => _('Successfully deleted the Certificate Authority'));
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Unable to remove the Certificate Authority'));
						}
					break;
					case 'csr':
						$status = $this->removeCSR();
						if($status) {
							$this->message = array('type' => 'success', 'message' => _('Successfully deleted the Certificate Signing Request'));
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Unable to remove the Certificate Signing Request'));
						}
					break;
					case 'cert':
						$cert = $this->getCertificateDetails($_REQUEST['id']);
						if(empty($cert)) {
							$this->message = array('type' => 'danger', 'message' => _('Invalid Certificate'));
							break;
						}
						$this->removeCertificate($cert['cid']);
						$this->message = array('type' => 'success', 'message' => _('Deleted Certificate'));
					break;
				}
			break;
		}
		return true;
	}
	public function myShowPage($view=''){
		$view = !empty($this->goto) ? $this->goto : $view;
		$request = $_REQUEST;
		switch($request['action']) {
			case 'download':
				case 'csr':
					$csrs = $this->getAllManagedCSRs();
					$file = $this->PKCS->getKeysLocation()."/".$csrs[0]['basename'].".csr";
					if(!empty($csrs[0]['basename']) && file_exists($file)) {
						$quoted = sprintf('"%s"', addcslashes(basename($file), '"\\'));
						$size = filesize($file);
						header('Content-Description: File Transfer');
						header('Content-Type: application/octet-stream');
						header('Content-Disposition: attachment; filename=' . $quoted);
						header('Content-Transfer-Encoding: binary');
						header('Connection: Keep-Alive');
						header('Expires: 0');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						header('Pragma: public');
						header('Content-Length: ' . $size);
						readfile($file);
					}
					die();
				break;
			break;
			case 'add':
				switch($request['type']) {
					case 'le':
						$hostname = $this->PKCS->getHostname();
						echo load_view(__DIR__.'/views/le.php',array('message' => $this->message, 'hostname' => $hostname));
					break;
					case 'up':
						$csrs = $this->getAllManagedCSRs();
						echo load_view(__DIR__.'/views/up.php',array('message' => $this->message, 'csrs' => $csrs));
					break;
					case 'ss':
						$hostname = $this->PKCS->getHostname();
						$pass = base64_encode(openssl_random_pseudo_bytes(32));
						$caExists = $this->checkCAexists();
						$cas = $this->getAllManagedCAs();
						echo load_view(__DIR__.'/views/ss.php',array('caExists' => $caExists, 'cas' => $cas, 'message' => $this->message,'pass' => $pass, 'hostname' => $hostname));
					break;
					case 'csr':
						$hostname = $this->PKCS->getHostname();
						$csrs = $this->getAllManagedCSRs();
						echo load_view(__DIR__.'/views/csr.php',array('message' => $this->message, 'csrs' => $csrs, 'hostname' => $hostname));
					break;
				}
			break;
			case 'view':
				$cert = $this->getCertificateDetails($request['id']);
				$certinfo = '';
				if(file_exists($cert['files']['crt'])) {
					$certinfo = openssl_x509_parse(file_get_contents($cert['files']['crt']));
				}
				if(!empty($cert)) {
					switch($cert['type']) {
						case 'up':
							$csrs = $this->getAllManagedCSRs();
							echo load_view(__DIR__.'/views/up.php',array('cert' => $cert, 'message' => $this->message, 'csrs' => $csrs, 'certinfo' => $certinfo));
						break;
						case 'ss':
							$caExists = $this->checkCAexists();
							$cas = $this->getAllManagedCAs();
							if($cas){
								echo load_view(__DIR__.'/views/ss.php',array('cert' => $cert, 'caExists' => $caExists, 'cas' => $cas, 'message' => $this->message, 'certinfo' => $certinfo));
							}
						break;
						case 'le':
							echo load_view(__DIR__.'/views/le.php',array('cert' => $cert, 'message' => $this->message, 'certinfo' => $certinfo));
						break;
					}
				}
			break;
			default:
				$certs = $this->getAllManagedCertificates();
				$csr = $this->checkCSRexists();
				$ca = $this->checkCAexists();
				echo load_view(__DIR__.'/views/overview.php',array('certs' => $certs, 'message' => $this->message, 'csr' => $csr, 'ca' => $ca));
			break;
		}
	}
	public function getActionBar($request) {
		$buttons = array();
		$request['action'] = !empty($request['action']) ? $request['action'] : "";
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
				case 'add':
				case 'new':
					unset($buttons['delete']);
					$buttons['submit']['value'] = isset($_REQUEST['type']) && $_REQUEST['type'] == 'csr' ? _('Generate CSR') : _('Generate Certificate');
				break;
				default:
					$buttons = array();
				break;
			}
		return $buttons;
	}

	/**
	 * Update or Add Let's Encrypt
	 * @param  string $host    The hostname (MUST BE A VALID FQDN)
	 * @param  boolean $staging Whether to use the staging server or not
	 * @return boolean          True if success, false if not
	 */
	public function updateLE($host,$countryCode='US',$state='Wisconsin',$staging=false) {
		$location = $this->PKCS->getKeysLocation();
		$logger = new Certman\Logger();
		$host = basename($host);

		$needsgen = false;
		$certfile = $location."/".$host."/cert.pem";
		if (!file_exists($certfile)) {
			// We don't have a cert, so we need to request one.
			$needsgen = true;
		} else {
			// We DO have a certificate.
			$certdata = openssl_x509_parse(file_get_contents($certfile));
			// If it expires in less than a month, we want to renew it.
			$renewafter = $certdata['validTo_time_t']-(86400*30);
			if (time() > $renewafter) {
				// Less than a month left, we need to renew.
				$needsgen = true;
			}
		}

		if($needsgen) {
			$le = new \Analogic\ACME\Lescript($location, $this->FreePBX->Config->get("AMPWEBROOT"), $logger);
			if($staging) {
				$le->ca = 'https://acme-staging.api.letsencrypt.org';
			}
			$le->countryCode = $countryCode;
			$le->state = $state;
			$le->initAccount();
			$le->signDomains(array($host));
		}

		if(file_exists($location."/".$host)) {
			copy($location."/".$host."/private.pem",$location."/".$host.".key"); //webserver.key
			copy($location."/".$host."/fullchain.pem",$location."/".$host.".crt"); //webserver.crt
			$key = file_get_contents($location."/".$host.".key");
			$cert = file_get_contents($location."/".$host.".crt");
			file_put_contents($location."/".$host.".pem",$key."\n".$cert);
			chmod($location."/".$host.".crt",0600);
			chmod($location."/".$host.".key",0600);
			chmod($location."/".$host.".pem",0600);
		}
		return true;
	}

	/**
	 * Validate and import a certificate
	 *
	 * IF any private key has a passphrase this WILL strip the passphrase!!
	 *
	 * @param  string $name              The certificate basename
	 * @param  string $privateKey        RAW Private Key
	 * @param  string $signedCertificate RAW Signed Certificate
	 * @param  string $certificateChain  RAW Certificate Chain
	 * @param  string $passphrase        Passphrase to decrypt private key
	 */
	public function importCertificate($name,$privateKey,$signedCertificate,$certificateChain='',$passphrase='') {
		$location = $this->PKCS->getKeysLocation();
		$name = basename($name);

		if(empty($privateKey)) {
			throw new \Exception(_('No Private key to reference. Try generating a CSR first.'));
		}

		if(empty($signedCertificate)) {
			throw new \Exception(_('No Certificate provided'));
		}

		if(file_exists($location."/".$name.".key") && !is_writable($location."/".$name.".key")) {
			throw new \Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".key"));
		}

		if(file_exists($location."/".$name.".crt") && !is_writable($location."/".$name.".crt")) {
			throw new \Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".crt"));
		}

		if(file_exists($location."/".$name.".pem") && !is_writable($location."/".$name.".pem")) {
			throw new \Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".pem"));
		}

		if(empty($passphrase)) {
			$keyTest = openssl_pkey_get_private($privateKey);
		} else {
			$keyTest = openssl_pkey_get_private($privateKey,$passphrase);
		}
		if($keyTest === false) {
			throw new \Exception(_('Unable to read key. Is it password protected?'));
		}
		$certTest = openssl_x509_read($signedCertificate);
		if(!openssl_x509_check_private_key($certTest, $keyTest)) {
			throw new \Exception(_('Key does not match certificate'));
		}
		file_put_contents($location."/".$name.".key", $privateKey);

		//strip the passphrase
		if(!empty($_POST['passphrase'])) {
			$this->PKCS->runOpenSSL("rsa -in ".$location."/".$name.".key -out ".$location."/".$name."np.key -passin stdin",$passphrase);
			unlink($location."/".$name.".key"); //jic
			$privateKey = file_get_contents($location."/".$name."np.key");
			unlink($location."/".$name."np.key"); //jic
			file_put_contents($location."/".$name.".key", $privateKey);

			//Now test again without the password to make sure this all works correctly
			$keyTest = openssl_pkey_get_private($privateKey);
			if(!openssl_x509_check_private_key($certTest, $keyTest)) {
				throw new \Exception(_('Key does not match certificate after password removal'));
			}
		}

		file_put_contents($location."/".$name.".crt", $signedCertificate);

		if(!empty($certificateChain)) {
			file_put_contents($location."/".$name."-ca-bundle.crt", $certificateChain);
			chmod($location."/".$name."-ca-bundle.crt",0600);
			//TODO: what to do with this?
		}

		file_put_contents($location . "/" . $name . ".pem", $privateKey ."\n". $signedCertificate);
		chmod($location."/".$name.".crt",0600);
		chmod($location."/".$name.".key",0600);
		chmod($location."/".$name.".pem",0600);
		return true;
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
		$o = $this->getAllAuthorityFiles();
		$z = $this->getAllManagedCAs();
		if(empty($o) && !empty($z)) {
			//files are missing from hard drive. run delete
			$this->removeCA();
			return false;
		}
		return (empty($o) && empty($z)) ? false : true;
	}

	/**
	* Return a list of all Certificates from the key folder
	* @return array
	*/
	public function getAllAuthorityFiles() {
		$keyloc = $this->PKCS->getKeysLocation();
		$cas = array();
		$files = $this->PKCS->getFileList($keyloc);
		if(in_array('ca.key',$files)) {
			$cas[] = 'ca.key';
		}
		if(in_array('ca.crt',$files)) {
			$cas[] = 'ca.crt';
		}
		return $cas;
	}

	/**
	 * Check to see if *any* certificate authority exists
	 */
	public function checkCSRexists() {
		$z = $this->getAllManagedCSRs();
		return !empty($z);
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
	 * Save the Certificate Signing Request Information into the Database
	 * @param {string} $basename	 The CSR Basename
	 */
	public function saveCSR($basename) {
		$sql = "INSERT INTO certman_csrs (`basename`) VALUES (?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($basename));
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
			$location = $this->PKCS->getKeysLocation();
			$files = array(".key" => "key",".crt" => "crt",".cfg" => "cfg");
			foreach($files as $f => $type) {
				$file = $location.'/'.$data['basename'].$f;
				if(file_exists($file)) {
					$data['files'][$type] = $file;
				}
			}
		}
		return $data;
	}

	/**
	 * Get details about a certificate signing request
	 * @param  int $csrid The CSR id
	 * @return array        Array of data
	 */
	public function getCSRDetails($csrid) {
		$sql = "SELECT * from certman_csrs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($csrid));
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$location = $this->PKCS->getKeysLocation();
			$files = array(".csr" => "csr",".csr-config" => "csr-config",".key" => "key");
			foreach($files as $f => $type) {
				$file = $location.'/'.$data['basename'].$f;
				if(file_exists($file)) {
					$data['files'][$type] = $file;
				}
			}
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
	 * @param {int}    $caid				The Certificate Authority ID (For Self Signed)
	 * @param {string} $base				The base name of the certificate
	 * @param {string} $description The description of the certificate
	 * @param {string} $type				The type of the certificate: ss:: self signed, up:: upload, le:: let's encrypt
	 */
	public function saveCertificate($caid=null,$base,$description,$type='ss',$default=0) {
		if($this->checkCertificateName($base)) {
			return false;
		}
		$sql = "INSERT INTO certman_certs (`caid`, `basename`, `description`,`type`,`default`) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid,$base,$description,$type,$default));
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
	 * Get all Managed Certificate Signing Requests
	 */
	public function getAllManagedCSRs() {
		$sql = "SELECT * FROM certman_csrs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function getCertificateDetailsByBasename($basename) {
		$sql = "SELECT * from certman_certs WHERE basename = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($basename));
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$location = $this->PKCS->getKeysLocation();
			$files = array(".key" => "key",".crt" => "crt",".csr" => "csr",".pem" => "pem","-ca-bundle.crt" => "ca-bundle");
			foreach($files as $f => $type) {
				$file = $location.'/'.$data['basename'].$f;
				if(file_exists($file)) {
					$data['files'][$type] = $file;
					if($type == 'crt') {
						$data['info']['crt'] = @openssl_x509_parse(file_get_contents($file));
					}
				}
			}
		}
		return $data;
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
			$location = $this->PKCS->getKeysLocation();
			$files = array(".key" => "key",".crt" => "crt",".csr" => "csr",".pem" => "pem","-ca-bundle.crt" => "ca-bundle");
			foreach($files as $f => $type) {
				$file = $location.'/'.$data['basename'].$f;
				if(file_exists($file)) {
					$data['files'][$type] = $file;
					if($type == 'crt') {
						$data['info']['crt'] = @openssl_x509_parse(file_get_contents($file));
					}
				}
			}
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
	 * Check Certificate Name to see if it exists
	 * @param {string} $name Certificate Base Name
	 * @return boolean True if it exists, false if it doesnt
	 */
	public function checkCSRName($name) {
		$sql = "SELECT * FROM certman_csrs WHERE basename = ?";
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
		if(empty($cert)) {
			return false;
		}
		foreach($cert['files'] as $file) {
			if(file_exists($file)) {
				if(!unlink($file)) {
					throw new \Exception(sprintf(_('Unable to remove %s'),$file));
				}
			}
		}
		$sql = "DELETE FROM certman_certs WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($cid));
		return true;
	}

	/**
	 * Import local certificates that are in the keys folder but orphaned
	 * @return array Array of processed keys
	 */
	public function importLocalCertificates() {
		$location = $this->PKCS->getKeysLocation();
		$cas = $this->getAllAuthorityFiles();
		$keys = array();
		$processed = array();
		foreach(glob($location."/*.key") as $file) {
			$name = basename($file,".key");
			if(in_array(basename($file),$cas) || $this->checkCertificateName($name)) {
				continue;
			}
			$raw = file_get_contents($file);
			if(empty($raw)) {
				$processed[] = array(
					"status" => false,
					"error" => _("Key is empty"),
					"file" => $file
				);
				continue;
			}
			$key = openssl_pkey_get_private($raw);
			if($key === false) {
				//key is password protected
				$processed[] = array(
					"status" => false,
					"error" => _("Key is password protected or malformed"),
					"file" => $file
				);
				continue;
			}
			$info = openssl_pkey_get_details($key);
			$keys[] = array(
				"file" => $file,
				"res" => $key,
				"raw" => file_get_contents($file)
			);
		}

		foreach(glob($location."/*.crt") as $file) {
			if(in_array(basename($file),$cas)) {
				continue;
			}
			$raw = file_get_contents($file);
			if(empty($raw)) {
				$processed[] = array(
					"status" => false,
					"error" => _("Certificate is empty"),
					"file" => $file
				);
				continue;
			}
			$info = openssl_x509_read($raw);
			foreach($keys as $key) {
				if (openssl_x509_check_private_key($info, $key['res'])) {
					$name = basename($file,".crt");
					if(!$this->checkCertificateName($name)) {
						try {
							$status = $this->importCertificate($name,$key['raw'],file_get_contents($file));
						} catch(\Exception $e) {
							$processed[] = array(
								"status" => false,
								"error" => $e->getMessage(),
								"file" => $file
							);
							continue;
						}
						$this->saveCertificate(null,$name,_("Imported from file system"),'up');
						$processed[] = array(
							"status" => true,
							"file" => $file
						);
					}
				}
			}
		}
		return $processed;
	}

	/**
	 * Remove a Certificate Authority and all of it's child certificates
	 */
	public function removeCA() {
		$location = $this->PKCS->getKeysLocation();
		if(file_exists($location . "/ca.key")) {
			if(!unlink($location . "/ca.key")) {
				throw new \Exception(_('Unable to remove ca.key'));
			}
		}
		if(file_exists($location . "/ca.crt")) {
			if(!unlink($location . "/ca.crt")) {
				throw new \Exception(_('Unable to remove ca.crt'));
			}
		}
		if(file_exists($location . "/ca.cfg")) {
			if(!unlink($location . "/ca.cfg")) {
				throw new \Exception(_('Unable to remove ca.cfg'));
			}
		}
		if(file_exists($location . "/tmp.cfg")) {
			if(!unlink($location . "/tmp.cfg")) {
				throw new \Exception(_('Unable to remove tmp.cfg'));
			}
		}
		$sql = "TRUNCATE certman_cas";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		foreach($this->getAllManagedCertificates() as $cert) {
			if($cert['type'] == 'ss') {
				$this->removeCertificate($cert['cid']);
			}
		}
		return true;
	}

	/**
	 * Remove a Certificate Signing Request
	 */
	public function removeCSR($keepKey = false) {
		try {
			$csrs = $this->getAllManagedCSRs();
			$loc = $this->PKCS->getKeysLocation();
		} catch(\Exception $e) {
			return false;
		}
		$files[] = $loc ."/".$csrs[0]['basename'].".csr";
		$files[] = $loc ."/".$csrs[0]['basename'].".csr-config";
		if(!$keepKey) {
			$files[] = $loc ."/".$csrs[0]['basename'].".key";
		}
		foreach($files as $file) {
			if(file_exists($file)) {
				unlink($file);
			}
		}
		$sql = "TRUNCATE certman_csrs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return true;
	}

	/**
	 * Update Certificate
	 * @param  int $cid         The Certificate ID
	 * @param  string $name        The certificate name
	 * @param  string $description The certificate description
	 * @return mixed              Bool if true, string if false
	 */
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

	/**
	 * Update Database about Cert
	 * @param  int $cid         The Cert ID
	 * @param  string $description the cert description
	 * @return [type]              [description]
	 */
	public function update($cid,$description) {
		$sql = "UPDATE certman_certs SET description = ? WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		return $sth->execute(array($description,$cid));
	}

	public function getRightNav($request) {
		if(isset($request['action']) && !empty($request['action'])){
			return load_view(__DIR__."/views/rnav.php",array('caExists' => $this->checkCAexists()));
		}
	}

	/**
	 * Make a certificate the default
	 * @param  int $cid The certificate ID
	 * @return bool      True if success
	 */
	public function makeCertDefault($cid) {
		$cert = $this->getCertificateDetails($cid);
		if(empty($cert)) {
			return false;
		}
		$sql = "UPDATE certman_certs SET `default` = 0";
		$sth = $this->db->prepare($sql);
		$sth->execute();

		$sql = "UPDATE certman_certs SET `default` = ? WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array(1,$cid));

		$sslfiles = array("certificate.pem", "ca-bundle.crt", "webserver.crt", "webserver.key");
		foreach ($sslfiles as $f) {
			if (file_exists("/etc/asterisk/keys/integration/$f")) {
				unlink("/etc/asterisk/keys/integration/$f");
			}
		}

		$this->FreePBX->Hooks->processHooks($cert);
		return true;
	}

	/**
	 * Get default certificate details
	 * @return array Array of certificate
	 */
	public function getDefaultCertDetails() {
		$sql = "SELECT * from certman_certs WHERE `default` = 1";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		$data = $sth->fetch(\PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$location = $this->PKCS->getKeysLocation();
			$files = array(".key" => "key",".crt" => "crt",".csr" => "csr",".pem" => "pem","-ca-bundle.crt" => "ca-bundle");
			foreach($files as $f => $type) {
				$file = $location.'/'.$data['basename'].$f;
				if(file_exists($file)) {
					$data['files'][$type] = $file;
				}
			}
		} else {
			$data = array(); //jic
		}
		return $data;
	}

	public function ajaxRequest($req, &$setting) {
		switch ($req) {
			case 'makeDefault':
			case 'getJSON':
				return true;
			break;
		}
		return false;
	}
	public function ajaxHandler(){
		switch ($_REQUEST['command']) {
			case 'makeDefault':
				$res = $this->makeCertDefault($_POST['id']);
				return array("status" => $res, "message" => "");
			break;
			case 'getJSON':
				switch ($_REQUEST['jdata']) {
					case 'grid':
						return $this->getAllManagedCertificates();
					break;
				}
			break;
		}
		return false;
	}
}
