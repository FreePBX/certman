<?php
// vim: set ai ts=4 sw=4 ft=php:
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2014 Schmooze Com Inc.
//	Copyright 2018 Sangoma Technologies.
namespace FreePBX\modules;

include_once __DIR__."/vendor/autoload.php";

use Composer\CaBundle\CaBundle;
use BMO;
use PDO;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Exception;
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
	private $message = "";

	public function __construct($freepbx = null) {
		if ($freepbx == null){
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->FreePBX->PJSip = $this->FreePBX->Core->getDriver('pjsip');
		$this->PKCS = $this->FreePBX->PKCS;
		$this->PKCS->timeout = 240; //because of piiiiiis
	}

	public function setDatabase($pdo){
		$this->db = $pdo;
		return $this;
	}

	public function resetDatabase(){
		$this->db = $this->FreePBX->Database;
	}

	/**
	 * Used to setup the database
	 */
	public function install() {
		$certs = $this->getAllManagedCertificates();
		if(empty($certs)) {
			out(_("No Certificates exist"));

			if(!$this->checkCAexists()) {
				outn(_("Generating default CA..."));
				$hostname = gethostname();
				$hostname = !empty($hostname) ? $hostname : 'localhost';
				$caid = $this->generateCA('ca', $hostname, $hostname);
				out(_("Done!"));
			} else {
				$dat = $this->getAllManagedCAs();
				$caid = $dat[0]['uid'];
			}

			outn(_("Generating default certificate..."));
			// Do not i18n the NAME of the cert, it is 'default'.
			try {
				$cid = $this->generateCertificate($caid,"default",_("Default Self-Signed certificate"));
				$this->makeCertDefault($cid);
				out(_("Done!"));
			} catch(Exception $e) {
				out(sprintf(_("Failed! [%s]"),$e->getMessage()));
				//return false;
			}
		}
		return true;
	}

	public function uninstall() {
		try {
			$this->removeCSR();
			$this->removeCA();
			$certs = $this->getAllManagedCertificates();
			foreach($certs as $cert) {
				$this->removeCertificate($cert['cid']);
			}
		} catch(Exception $e) {}

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
							} catch(Exception $e) {
								$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error importing the certificate: %s'),$e->getMessage()));
								break;
							}

							$this->updateCertificate($cert,$_POST['description']);
							if($removeCSR) {
								$this->removeCSR(true);
							}
							needreload();
							$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
						}
					break;
					case "le":
						$cert = $this->getCertificateDetails($_POST['cid']);
						$host = $cert['basename'];
						$description = $host;
						$san = array_unique(array_filter(array_map('trim', explode("\n", strtolower($_POST['SAN'])))));
						if (!empty($san)) {
							if ($key = array_search($host, $san)) {
								unset($key);
							}
							sort($san);
							$description .= ", " . implode(", ", $san);
						}

						if(!empty($cert)) {
							$additional = array(
								"C" => $_POST['C'],
								"ST" => $_POST['ST'],
								"email" => $_POST['email'],
							);
							if (!empty($san)) {$additional['san'] = $san;}


							if ($additional == $cert['additional']) {
								$this->message = array('type' => 'success', 'message' => _('Nothing to do, no changes made'));
								break;
							}
							ob_start();
							try {
								$this->updateLE($host, array(
									"countryCode" => $_POST['C'],
									"state" => $_POST['ST'],
									"challengetype" => "http", // https will not work.
									"email" => $_POST['email'],
									"san" => $san
								), false, true);
							} catch(Exception $e) {
								$lelog = trim(ob_get_contents());
								ob_end_clean();
								$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
								$api = $this->getFirewallAPI();
								$leoptions = $api->getLeOptions();
								if (!empty($einfo['detail'])) {
									$emessage = $einfo['detail'];
								} else {
									$emessage = $e->getMessage();
									$lelog = $lelog == ''?'': $lelog;
								}

								$hints = !empty($einfo['hints']) ? array_merge($einfo['hints'], $leoptions['hints']) : $leoptions['hints'];
								$this->message = array(	'title' => 'LetsEncrypt Update Failure',
											'type' => 'danger',
											'message' => $emessage,
											'log' => $lelog,
											'hints' => $hints,
										);
								break;
							}
							$lelog = ob_get_contents();
							ob_end_clean();
							$this->updateCertificate($cert, $description, $additional);
							$this->message = array('type' => 'success', 'title' => _('LetsEncrypt Update Success!'), 'log' => $lelog );
							needreload();
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Certificate is invalid'));
						}
					break;
					case "ss":
						$cert = $this->getCertificateDetails($_POST['cid']);
						if(!empty($cert)) {
							$this->updateCertificate($cert,$_POST['description']);
							$this->message = array('type' => 'success', 'message' => _('Updated certificate'));
							needreload();
						} else {
							$this->message = array('type' => 'danger', 'message' => _('Certificate is invalid'));
						}
					break;
				}
			break;
			case "add":
				switch($request['type']) {
					case "le":
						$host = basename(strtolower($_POST['host']));
						$description = $host;
						$san = array_unique(array_filter(array_map('trim', explode("\n", strtolower($_POST['SAN'])))));
						if (!empty($san)) {
							if ($key = array_search($host, $san)) {
								unset($key);
							}
							sort($san);
							$description .= ", " . implode(", ", $san);
						}
						$additional = array(
								"C" => $_POST['C'],
								"ST" => $_POST['ST'],
								"email" => $_POST['email']
						);
						if (!empty($san)) {$additional['san'] = $san;}
						ob_start();
						try{
							if($this->checkCertificateName($host)) {
								throw new Exception(sprintf(_("%s already exists!"),$host));
							}
							$this->updateLE($host, array(
								"countryCode" => $_POST['C'],
								"state" => $_POST['ST'],
								"challengetype" => "http", // https will not work
								"email" => $_POST['email'],
								"san" => $san
							));
							$this->saveCertificate(null, $host, $description, 'le', $additional);
						} catch(Exception $e) {
							$lelog = trim(ob_get_contents());
							ob_end_clean();
							$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
							$api = $this->getFirewallAPI();
							$leoptions = $api->getLeOptions();
							if (!empty($einfo['detail'])) {
								$emessage = $einfo['detail'];
							} else {
								$emessage = $e->getMessage();
								$lelog = $lelog == ''?'': $lelog;
							}
							$hints = !empty($einfo['hints']) ? array_merge($einfo['hints'], $leoptions['hints']) : $leoptions['hints'];
							$this->message = array(	'title' => 'LetsEncrypt Generation Failure',
										'type' => 'danger',
										'message' => $emessage,
										'log' => $lelog,
										'hints' => $hints,
									);

						break 2;
						}
						$lelog = ob_get_contents();
						ob_end_clean();
						$this->message = array('type' => 'success', 'title' => _('LetsEncrypt Generation Success!'), 'log' => $lelog );
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
							$this->saveCertificate(null,$name,$_POST['description'],'up');
						} catch(Exception $e) {
							$this->message = array('type' => 'danger', 'message' => sprintf(_('There was an error importing the certificate: %s'),$e->getMessage()));
							break;
						}
						if($removeCSR) {
							$this->removeCSR(true);
						}
						$this->message = array('type' => 'success', 'message' => _('Added new certificate'));
					break;
					case "ss":
						if(empty($request['caid'])) {
							//generate new cert authority
							$this->generateConfig('ca',$request['hostname'],$request['orgname']);
							$caid = $this->generateCA('ca',$request['hostname'],$request['orgname']);
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
						try {
							$this->generateCertificate($caid,$request['hostname'],$request['description']);
						} catch(Exception $e) {
							$this->message = array('type' => 'danger', 'message' => sprintf(_('Unable to generate certificate: %s'),$e->getMessage()));
							break;
						}
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
						} catch(Exception $e) {
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
						$status = $this->removeCSR(true);
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
						// Have we been asked to update firewall rules?
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
				// Have we been asked to update firewall rules?
				$cert = $this->getCertificateDetails($request['id']);
				$certinfo = array();
				if(file_exists($cert['files']['crt'])) {
					$certinfo = openssl_x509_parse(file_get_contents($cert['files']['crt']));
				}
				//FREEPBX-15408 WhoopsException\ErrorException Illegal string offset 'validTo_time_t'
				if (!array_key_exists("validTo_time_t",$certinfo)){
					$certinfo['validTo_time_t'] = '';
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
				$location = $this->PKCS->getKeysLocation();
				echo load_view(__DIR__.'/views/overview.php',array('certs' => $certs, 'location' => $location, 'message' => $this->message, 'csr' => $csr, 'ca' => $ca));
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
	 * Check and/or update all certificates
	 *
	 * If a certificate can't be automatically updated add a notice in the interface
	 *
	 * @return [type] [description]
	 */
	public function checkUpdateCertificates($force = false) {
		$certs = $this->getAllManagedCertificates();
		$messages = array();
		foreach($certs as $cert) {
			$cert = $this->getAdditionalCertDetails($cert);
			if(empty($cert['files'])) {
				//there are no files so delete this one
				$this->removeCertificate($cert['cid']);
				$messages[] = array('type' => 'danger', 'message' => sprintf(_('There were no files left for certificate "%s" so it was removed'),$cert['basename']));
				continue;
			}
			$validTo = $cert['info']['crt']['validTo_time_t'];
			$renewafter = $validTo-(86400*30);
			$update = false;

			// Has this certificate expired?
			if(time() > $validTo) {
				if($cert['type'] == 'le') {
					try {

						// This will probably fail if they're using http_S_ with an expired
						// cert, but LE should never get to this point.
						$settings = array(
							"countryCode" => $cert['additional']['C'],
							"state" => $cert['additional']['ST'],
							"challengetype" => "http", // https will not work
							"email" => $cert['additional']['email'],
							"san" => $cert['additional']['san'],
						);

						$this->updateLE($cert['info']['crt']['subject']['CN'], $settings, false, $force);

						// If that didn't throw, the certificate was succesfully updated
						$messages[] = array('type' => 'success', 'message' => sprintf(_('Successfully updated certificate named "%s"'),$cert['basename']));
						$this->FreePBX->astman->Reload();

						//Until https://issues.asterisk.org/jira/browse/ASTERISK-25966 is fixed
						$a = fpbx_which("asterisk");
						if(!empty($a)) {
							exec($a . " -rx 'dialplan reload'");
						}
						$update = true;
					} catch(Exception $e) {
						$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
						$hints = array();
						if (!empty($einfo['detail'])) {
							$emessage = $einfo['detail'];
						} else {
							$emessage = $e->getMessage();
						}
						if (!empty($einfo['hints'])) {
							$hints = $einfo['hints'];
						}
						$messages[] = array('type' => 'danger', 'message' => sprintf(_('There was an error updating certificate "%s": %s'),$cert['basename'],$emessage), 'hints' => $einfo['hints']);
						continue;
					}
				} else {
					$messages[] = array('type' => 'warning', 'message' => sprintf(_('Certificate named "%s" has expired. Please update this certificate in Certificate Manager'),$cert['basename']));
					continue;
				}
			} elseif (time() > $renewafter || $force) {
				// It hasn't expired, but it should be renewed.
				if($cert['type'] == 'le') {
					try {
						$settings = array(
							"countryCode" => $cert['additional']['C'],
							"state" => $cert['additional']['ST'],
							"challengetype" => "http", // https will not work
							"email" => $cert['additional']['email'],
							"san" => $cert['additional']['san'],
						);

						$this->updateLE($cert['info']['crt']['subject']['CN'], $settings, false, $force);
						$messages[] = array('type' => 'success', 'message' => sprintf(_('Successfully updated certificate named "%s"'),$cert['basename']));
						$this->FreePBX->astman->Reload();
						//Until https://issues.asterisk.org/jira/browse/ASTERISK-25966 is fixed
						$a = fpbx_which("asterisk");
						if(!empty($a)) {
							exec($a . " -rx 'dialplan reload'");
						}
						$update = true;
					} catch(Exception $e) {
						$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
						$hints = array();
						if (!empty($einfo['detail'])) {
							$emessage = $einfo['detail'];
						} else {
							$emessage = $e->getMessage();
						}
						if (!empty($einfo['hints'])) {
							$hints = $einfo['hints'];
						}
						$messages[] = array('type' => 'danger', 'message' => sprintf(_('There was an error updating certificate "%s": %s'),$cert['basename'],$emessage), 'hints' => $einfo['hints']);
						continue;
					}
				} else {
					$messages[] = array('type' => 'warning', 'message' => sprintf(_('Certificate named "%s" is going to expire in less than a month. Please update this certificate in Certificate Manager'),$cert['basename']));
					continue;
				}
			} else {
				$messages[] = array('type' => 'success', 'message' => sprintf(_('Certificate named "%s" is valid'),$cert['basename']));
			}
			//trigger hook only if we really updated though
			if($update) {
				$this->updateCertificate($cert, $cert['description'], $cert['additional']);
			}
		}
		$nt = \notifications::create();
		$notification = '';
		foreach($messages as $m) {
			if('warning' == $m['type'] || 'danger' == $m['type']){
				$notification .= $m['message'] . "</br>";
			}
		}
		if(!empty($notification)) {
			$nt->add_security("certman", "EXPIRINGCERTS", _("Some Certificates are expiring or have expired"), $notification, "config.php?display=certman", true, true);
		}
		if($update) {
			$nt->add_security("certman", "UPDATEDCERTS", _("Updated Certificates"), _("Some SSL/TLS Certificates have been automatically updated. You may need to ensure all services have the correctly update certificate by restarting PBX services"), "", true,true);
		}
		return $messages;
	}

	/**
	 * Determine location of the system CA Bundle
	 * @method getCABundle
	 * @return string      The location of the system CA bundle
	 */
	public function getCABundle() {
		return CaBundle::getSystemCaRootBundlePath();
	}

	/**
	 * Update or Add Let's Encrypt
	 * @param  string $host     The hostname (MUST BE A VALID FQDN)
	 * @param  array $settings  Array of settings for this certificate
	 * @param  boolean $staging Whether to use the staging server or not
	 *
	 * @return boolean          True if success, false if not
	 */
	public function updateLE($host, $settings = false, $staging = false, $force = false) {
		/**
		 * Enable LE rules and set a delay for disabling LE rules.
		 * The time remaining is between 1 and 2 minutes before to close the door.
		 * It's good to close the door even if there is any error before the end of process.
		 * No need to execute a delay if this one is not performed yet.
		 * Using process to handle enable and disable of LE Rules
		 */
		if (!is_array($settings)) {
			throw new Exception("BUG: Settings is not an array. Old code?");
		}

		if(!$this->checkFirewallAndIpset()){
			throw new Exception("Please install ipset package And Restart the Firewall to continue");
		}
		// Get our variables from $settings
		$countryCode = !empty($settings['countryCode']) ? $settings['countryCode'] : 'CA';
		$state = !empty($settings['state']) ? $settings['state'] : 'Ontario';
		$challengetype = "http"; // Always http
		$email = !empty($settings['email']) ? $settings['email'] : '';
		$san = !empty($settings['san']) ? $settings['san'] : array();

		$location = $this->PKCS->getKeysLocation();
		$logger = $this->FreePBX->Logger->monoLog;
		$host = basename($host);
		$certpath = $location . "/" .$host;
		array_unshift($san, $host);

		$needsgen = false;
		$certfile = $certpath . "/" . $host."/cert.pem";

		$user = $this->FreePBX->Config->get("AMPASTERISKWEBUSER");
		$group = $this->FreePBX->Config->get("AMPASTERISKWEBGROUP");
		$webroot = $this->FreePBX->Config->get("AMPWEBROOT");

		$hints = array();

		if (!file_exists($certfile)) {
			// We don't have a cert, so we need to request one.
			$needsgen = true;
		} else {
			// We DO have a certificate.
			$certdata = openssl_x509_parse(file_get_contents($certfile));
			// If it expires in less than a month, we want to renew it.
			$renewafter = $certdata['validTo_time_t']-(86400*30);
			if (time() > $renewafter || $force) {
				// Less than a month left, we need to renew.
				$needsgen = true;
			}
		}

		try{
			$this->enableFirewallLeRules();

			$localip = gethostbyname($host);
			$localip = @inet_pton($localip) ? $localip : 'dns error';
			$publicip = $this->getPublicIP($host);
			$publicip = (!empty($publicip) && @inet_pton($publicip[0])) ? $publicip[0] : 'dns error';

			print(sprintf(_("Processing: %s, Local IP: %s, Public IP: %s\n"), $host, $localip, $publicip));

			//self-test first
			//	if this fails the equivalent code in Lesript.php will fail cryptically
			if($needsgen) {
				$basePathCheck = "/.freepbx-known";
				if(!file_exists($webroot.$basePathCheck)) {
					$mkdirok = @mkdir($webroot.$basePathCheck,0777);
					if (!$mkdirok) {
						throw new Exception(_("Unable to create directory ").$webroot.$basePathCheck);
					}
				}
				$token = bin2hex(openssl_random_pseudo_bytes(16));
				$pathCheck = $basePathCheck."/".$token;
				file_put_contents($webroot.$pathCheck,$token);

				print(_("Self test: trying ") . "http://" . $host.$pathCheck . "\n");
				$pest = new \Pest("http://".$host);
				$pest->curl_opts[CURLOPT_FOLLOWLOCATION] = true;
				$pest->curl_opts[CURLOPT_CONNECTTIMEOUT] = 5;
				$pest->curl_opts[CURLOPT_TIMEOUT] = 5;
				try {
					$selftest = $pest->get($pathCheck);
				} catch(Exception $e) {
					$selftesterr = _("Self test error: ") . get_class($e) . " - " . trim(strip_tags($e->getMessage()));
					$hints[] = sprintf(_("Does DNS for %s resolve correctly?\nLocal DNS result: %s, External DNS result: %s"), $host, $localip, $publicip);
					print($selftesterr ."\n");
					@unlink($webroot.$pathCheck);
					throw new Exception(json_print_pretty(json_encode(array('type' => 'selftest', 'detail' => $selftesterr)), "  "));
				}
				if(empty($selftest)) {
					$selftesterr = _("Self test error: no token data");
					print($selftesterr ."\n");
					@unlink($webroot.$pathCheck);
					throw new Exception($selftesterr);
				}
				print("Self test: received ". $selftest . "\n");

			//Now check freepbx.org
			//	on failure, save error as hint and continue
				try {
					$pest = new \PestJSON('http://mirror1.freepbx.org');
					$pest->curl_opts[CURLOPT_FOLLOWLOCATION] = true;
					$pest->curl_opts[CURLOPT_CONNECTTIMEOUT] = 10;
					$pest->curl_opts[CURLOPT_TIMEOUT] = 30;
					$thing = $pest->get('/lechecker.php', array('host' => $host, 'path' => $pathCheck, 'token' => $token, 'type' => $challengetype));
					if(empty($thing)) {
						$lecheckerr = _("No valid response from http://mirror1.freepbx.org");
					} elseif(!$thing['status']) {
						$lecheckerr = $thing['message'];
					}
				} catch(Exception $e) {
					$lecheckerr =  _("lechecker: ") . get_class($e) . " - " . trim(strip_tags($e->getMessage()));
				}
				if ($lecheckerr) {
					print($lecheckerr . "\n");
					$hints[] = $lecheckerr;
				}
				@unlink($webroot.$pathCheck);
			}

			//Now check let's encrypt
			if($needsgen) {
				$tokenpath = $webroot . "/.well-known/acme-challenge";
				$prechallengefiles = glob($tokenpath .'/*'); // */
				$le = new \Analogic\ACME\Lescript($location, $webroot, $logger);
				if($staging) {
					$le->ca = 'https://acme-staging.api.letsencrypt.org';
				}
				$le->countryCode = $countryCode;
				$le->state = $state;
				// Email should not be a per-cert entry.
				// It is only used whem le account is created on first cert request.
				// Probably should be a single module-level setup entry or removed.
				if (!empty($email)) {
					$le->contact = array("mailto:" . $email);
				}
				$le->initAccount();
				$le->signDomains($san);
			}
			$this->disableFirewallLeRules();

			if(!file_exists($certpath . "/private.pem") || !file_exists($certpath . "/cert.pem")) {
				throw new Exception(_("Certificates are missing. Unable to continue"));
			}

			if(file_exists($certpath)) {
				//https://community.letsencrypt.org/t/solved-why-isnt-my-certificate-trusted/2479/4
				copy($certpath . "/private.pem", $certpath . ".key"); //webserver.key
				copy($certpath . "/chain.pem", $certpath . "-ca-bundle.crt"); //ca-bundle.crt
				copy($certpath . "/cert.pem", $certpath . ".crt"); //webserver.crt
				$key = file_get_contents($certpath . ".key");
				$cert = file_get_contents($certpath . ".crt");
				$bundle = file_get_contents($certpath . "-ca-bundle.crt");
				//https://issues.freepbx.org/browse/FREEPBX-14631
				$root = file_get_contents(__DIR__."/files/x3-root-ca.cert");
				$bundle = $bundle."\n-----BEGIN CERTIFICATE-----\n".$root."-----END CERTIFICATE-----\n";
				file_put_contents($certpath . "-ca-bundle.crt", $bundle);
				file_put_contents($certpath . ".pem", $key . "\n" . $cert . "\n" . $bundle);

				$chown[] = $certpath;
				$exts = array(".key", ".crt", ".pem", "-ca-bundle.crt");
				foreach($exts as $ext){
					chmod($certpath . $ext, 0600);
					$chown[] = $certpath . $ext;
				}
				$lefiles = array_diff(scandir($certpath), array('..', '.'));
				foreach($lefiles as $lefile) {
					chmod($certpath . "/" . $lefile, 0600);
					$chown[] = $certpath . "/" . $lefile;
				}
			}
			if(file_exists($location . "/_account")) {
				$chown[] = $location . "/_account";
				$lefiles = array_diff(scandir($location . "/_account"), array('..', '.'));
				foreach($lefiles as $lefile) {
					chmod($location . "/_account/" . $lefile, 0600);
					$chown[] = $location . "/_account/" . $lefile;
				}
			}
			if(!empty($chown) && posix_geteuid() === 0) {
				foreach($chown as $file) {
					chown($file, $user);
					chgrp($file, $group);
				}
			}
			return true;
		} catch(Exception $e) {
			$this->disableFirewallLeRules();
			// clean up challenge tokens on failed requests
			// we know the lechecker file name
			@unlink($webroot.$pathCheck);
			// Lescript,php doesn't expose the token name, assume we own any new files
			if(is_dir($tokenpath)) {
				$postchallengefiles = array_diff(glob($tokenpath .'/*'), $prechallengefiles);
				foreach($postchallengefiles as $tokenfile) {
					// ignore unlink errs - it's possible we don't own the new file */
					if (is_file($tokenfile)) @unlink($tokenfile);
				}
			}
			$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
			if (!$einfo) $einfo = array();
			$einfo['detail'] = !empty($einfo['detail']) ? $einfo['detail'] : $e->getMessage();
			$einfo['type'] = !empty($einfo['type']) ? $einfo['type'] : "unknown";
			$einfo['hints'] = !empty($einfo['hints']) ? array_merge($hints, $einfo['hints']) : $hints;
			throw new Exception(json_print_pretty(json_encode($einfo), "  "));
		}
	}

	/* enable firewall rules */
	private function enableFirewallLeRules() {
		$api = $this->getFirewallAPI();
		$module_info = module_getinfo('firewall', MODULE_STATUS_ENABLED);
		if(isset($module_info["firewall"]) && $api->isAvailable()){
			$api->enableLeRules();
			usleep(500000);
		}
	}

	/* disable firewall lerules */
	private function disableFirewallLeRules() {
		$api = $this->getFirewallAPI();
		$module_info = module_getinfo('firewall', MODULE_STATUS_ENABLED);
		if(isset($module_info["firewall"]) && $api->isAvailable()){
			$api->disableLeRules();
		}
	}

	/* check firewall is enabled or not*
	if firewall enabled then check the version 13.0.60.13
	check ipset is installed or not
	return true/flase
	*/
	private function checkFirewallAndIpset() {
		$mf = \module_functions::create();
		$firewall = $mf->getinfo('firewall');
		//firewall not present in system
		if(!isset($firewall['firewall'])){
			return true;
		}
		//fire wall module not enabled
		if ($firewall['firewall']['status'] != 2) {
			return true;
		}
		$api = $this->getFirewallAPI();
		if(!$api->isAvailable()){
			return true;
		}
		// check firewall version having ipset
		if (version_compare_freepbx($firewall['firewall']['version'],'15.0.6.29','<')) {
			throw new Exception(_("There was an error updating the certificate: Firewall v 15.0.6.29 and above required please install"));
		}
		$ipset = fpbx_which("ipset") ;
		if($ipset ==''){
			return false;
		}
		return true;
	}
	/**
	 * FreePBX chown hooks
	 */
	public function chownFreepbx() {
		$certs = $this->getAllManagedCertificates();
		$location = $this->PKCS->getKeysLocation();
		$files[] = array('type' => 'rdir',
				'path' => $location . "/_account",
				'perms' => 0600);
		$files[] = array('type' => 'file',
				'path' => $location . "/_account",
				'perms' => 0755);
		foreach($certs as $cert) {
			if ($cert['type'] == 'le') {
				$files[] = array('type' => 'rdir',
					'path' => $location . "/" . $cert['basename'],
					'perms' => 0600);
				$files[] = array('type' => 'file',
					'path' => $location . "/" . $cert['basename'],
					'perms' => 0755);
			}
			$details = $this->getCertificateDetails($cert['cid']);
			if(!empty($details['files'])) {
				foreach($details['files'] as $file) {
					$files[] = array('type' => 'file',
						'path' => $file,
						'perms' => 0600);
				}
			}
			if(!empty($details['integration']['files'])) {
				foreach($details['integration']['files'] as $file) {
					$files[] = array('type' => 'file',
						'path' => $file,
						'perms' => 0600);
				}
			}
		}
		return $files;
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
	public function importCertificate($name, $privateKey, $signedCertificate, $certificateChain='', $passphrase='') {
		$location = $this->PKCS->getKeysLocation();
		$name = basename($name);

		if(empty($privateKey)) {
			throw new Exception(_('No Private key to reference. Try generating a CSR first.'));
		}

		if(empty($signedCertificate)) {
			throw new Exception(_('No Certificate provided'));
		}

		//https://stackoverflow.com/questions/11852476/php-removing-windows-m-character
		//Remove Windows control characters from certificate files as Asterisk
		//refuses to load the certifcate if it has them - Joao
		$privateKey = str_ireplace("\x0D", "", $privateKey);
		$signedCertificate = str_ireplace("\x0D", "", $signedCertificate);
		if(!empty($certificateChain)) {
			$certificateChain = str_ireplace("\x0D", "", $certificateChain);
		}

		if(file_exists($location."/".$name.".key") && !is_writable($location."/".$name.".key")) {
			throw new Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".key"));
		}

		if(file_exists($location."/".$name.".crt") && !is_writable($location."/".$name.".crt")) {
			throw new Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".crt"));
		}

		if(file_exists($location."/".$name.".pem") && !is_writable($location."/".$name.".pem")) {
			throw new Exception(sprintf(_('Unable to write to %s'),$location."/".$name.".pem"));
		}

		if(empty($passphrase)) {
			$keyTest = openssl_pkey_get_private($privateKey);
		} else {
			$keyTest = openssl_pkey_get_private($privateKey,$passphrase);
		}
		if($keyTest === false) {
			throw new Exception(_('Unable to read key. Is it password protected?'));
		}
		$certTest = openssl_x509_read($signedCertificate);
		if(!openssl_x509_check_private_key($certTest, $keyTest)) {
			throw new Exception(_('Key does not match certificate'));
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
				throw new Exception(_('Key does not match certificate after password removal'));
			}
		}

		file_put_contents($location."/".$name.".crt", $signedCertificate);

		if(!empty($certificateChain)) {
			file_put_contents($location."/".$name."-ca-bundle.crt", $certificateChain);
			$bundle = file_get_contents($location."/".$name."-ca-bundle.crt");
			file_put_contents($location . "/" . $name . ".pem", $privateKey ."\n". $signedCertificate."\n".$bundle);
			chmod($location."/".$name."-ca-bundle.crt",0600);
		} else {
			file_put_contents($location . "/" . $name . ".pem", $privateKey ."\n". $signedCertificate);
		}


		chmod($location."/".$name.".crt",0600);
		chmod($location."/".$name.".key",0600);
		chmod($location."/".$name.".pem",0600);
		return true;
	}

	public function myDialplanHooks() {
		return true;
	}

	/**
	 * Check if DTLS Autogenerated Certificate option is supported
	 * @return boolean
	 */
	public function pjsipDTLSAutoGenerateCertSupported() {
		$asteriskVersion = $this->FreePBX->Config->get('ASTVERSION');
		if (version_compare($asteriskVersion, '15.2', 'le')) {
			return false;
		}

		return true;
	}

	public function doDialplanHook(&$ext, $engine, $priority) {
		global $core_conf;

		foreach($this->getAllDTLSOptions() as $device) {
			$o = $this->FreePBX->Core->getDevice($device['id']);
			if(empty($o)) {
				continue;
			}

			// If auto_generate_cert is enabled but the asterisk version is not
			// supported, let's assume this is a downgrade situation and use the
			// default certificate
			$cert = array();
			if ((integer)$device['auto_generate_cert'] === 1) {
				if (!$this->pjsipDTLSAutoGenerateCertSupported()) {
					$cert = $this->getDefaultCertDetails();
				}
			} else {
				$cert = $this->getCertificateDetails($device['cid']);

				// if no certificate file found, do not enable DTLS
				if(empty($cert['files']['crt']) || empty($cert['files']['key'])) {
					continue;
				}
			}

			if($o['tech'] === 'sip') {
				$core_conf->addSipAdditional($device['id'], 'dtlsenable', 'yes');
				$core_conf->addSipAdditional($device['id'], 'dtlsverify', $device['verify']);
				$core_conf->addSipAdditional($device['id'], 'dtlscertfile', $cert['files']['crt']);
				$core_conf->addSipAdditional($device['id'], 'dtlsprivatekey', $cert['files']['key']);
				$core_conf->addSipAdditional($device['id'], 'dtlssetup', $device['setup']);
				$core_conf->addSipAdditional($device['id'], 'dtlsrekey', $device['rekey']);
			}

			if($o['tech'] === 'pjsip') {
				if (isset($o['media_encryption']) && $o['media_encryption'] == 'dtls') {
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_verify', $device['verify']);
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_setup', $device['setup']);
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_rekey', $device['rekey']);
				}
				if ((integer)$device['auto_generate_cert'] === 1 && $this->pjsipDTLSAutoGenerateCertSupported()) {
					$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_auto_generate_cert', 'yes');
					continue;
				}

				$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_cert_file', $cert['files']['crt']);
				$this->FreePBX->PJSip->addEndpoint($device['id'], 'dtls_private_key', $cert['files']['key']);
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
				"cid" => "",
				"setup" => "actpass",
				"rekey" => "0",
				"auto_generate_cert" => "0"
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
	 * @param {int} $device  The Device/Extension Number
	 * @param {array} $data	 An array of defined options
	 */
	public function addDTLSOptions($device, $data) {
		$autoGenerateCert = !empty($data['auto_generate_cert'])
			? $data['auto_generate_cert'] : 0;

		if ($autoGenerateCert && !$this->pjsipDTLSAutoGenerateCertSupported()) {
			dbug('DTLS autogenerate certificate option not available');
			return;
		}

		$certificate = empty($data['certificate']) ? null : $data['certificate'];
		if (!$autoGenerateCert && empty($certificate)) {
			dbug('DTLS certificate file not specified');
			return;
		}

		$sql = "REPLACE INTO certman_mapping (
			id,
			cid,
			verify,
			setup,
			rekey,
			auto_generate_cert
		) VALUES (?, ?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array(
			$device,
			$certificate,
			$data['verify'],
			$data['setup'],
			$data['rekey'],
			$autoGenerateCert
		));
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
		if((!empty($o) && empty($z)) || (empty($o) && !empty($z))) {
			//files are missing from hard drive/database, so something is corrupt.
			//run delete to resolve
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
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Generate a New Certificate Authority
	 * @param {string} $basename	 The basename of the file to generate
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
	 */
	public function generateCA($basename, $commonname, $orgname) {
		if(empty($basename)) {
			throw new Exception("Basename can not be empty!");
		}
		if(empty($commonname)) {
			throw new Exception("Commonname can not be empty!");
		}
		if(empty($orgname)) {
			throw new Exception("Organization can not be empty!");
		}
		$this->generateConfig($basename,$commonname,$orgname);
		$this->PKCS->createCA($basename);
		return $this->saveCA($basename, $commonname, $orgname);
	}

	/**
	 * Generate OpenSSL Template Configs
	 * @param {string} $basename	 The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
	 */
	public function generateConfig($basename, $commonname, $orgname) {
		$this->PKCS->createConfig($basename,$commonname,$orgname);
	}

	/**
	 * Save the Certificate Authority Information into the Database
	 * @param {string} $basename	 The CA Basename
	 * @param {string} $commonname The common name, usually FQDN or IP
	 * @param {string} $orgname		The organization name
	 * @param {string} $passphrase The passphrase (to be encrypted)
	 */
	public function saveCA($basename, $commonname, $orgname) {
		$sql = "INSERT INTO certman_cas (`basename`, `cn`, `on`) VALUES (?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($basename, $commonname,$orgname));
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
		$data = $sth->fetch(PDO::FETCH_ASSOC);
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
		$data = $sth->fetch(PDO::FETCH_ASSOC);
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
	 */
	public function generateCertificate($caid, $base, $description) {
		if($this->checkCertificateName($base)) {
			throw new Exception(sprintf(_("%s already exists!"),$base));
		}
		$ca = $this->getCADetails($caid);
		$passphrase = $ca['passphrase'];
		$this->PKCS->createCert($base,$ca['basename'],$passphrase);
		return $this->saveCertificate($caid,$base,$description);
	}

	/**
	 * Save Certificate Information into the Database
	 * @param {int}    $caid				The Certificate Authority ID (For Self Signed)
	 * @param {string} $base				The base name of the certificate
	 * @param {string} $description The description of the certificate
	 * @param {string} $type				The type of the certificate: ss:: self signed, up:: upload, le:: let's encrypt
	 * @param {string} $additional  Additional data in an array format
	 */
	public function saveCertificate($caid=null, $base, $description, $type='ss', $additional=array()) {
		if($this->checkCertificateName($base)) {
			throw new Exception(sprintf(_("%s already exists!"),$base));
		}
		if (!is_array($additional)) {
			$additional = array();
		}

		$sql = "INSERT INTO certman_certs (`caid`, `basename`, `description`, `type`, `additional`) VALUES (?, ?, ?, ?, ?)";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($caid,$base,$description,$type,json_encode($additional)));
		return $this->db->lastInsertId();
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
	 * Get all Managed Certificate Signing Requests
	 */
	public function getAllManagedCSRs() {
		$sql = "SELECT * FROM certman_csrs";
		$sth = $this->db->prepare($sql);
		$sth->execute();
		return $sth->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getCertificateDetailsByBasename($basename) {
		$sql = "SELECT * from certman_certs WHERE basename = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($basename));
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$default = !empty($data['default']) ? true : false;
			$data = $this->getAdditionalCertDetails($data, $default);
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
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$default = !empty($data['default']) ? true : false;
			$data = $this->getAdditionalCertDetails($data, $default);
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
	 * Check Certificate Name to see if it exists
	 * @param {string} $name Certificate Base Name
	 * @return boolean True if it exists, false if it doesnt
	 */
	public function checkCSRName($name) {
		$sql = "SELECT * FROM certman_csrs WHERE basename = ?";
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
		$location = $this->PKCS->getKeysLocation();
		if(empty($cert)) {
			return false;
		}
		foreach($cert['files'] as $file) {
			if(file_exists($file)) {
				if(!unlink($file)) {
					throw new Exception(sprintf(_('Unable to remove %s'),$file));
				}
			}
		}
		if ($cert['type'] == 'le' && is_dir($location . "/" . $cert['basename'])) {
			rrmdir($location . "/" . $cert['basename']);
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
			//API key from api module should ignore
			if($name == 'api_oauth' || $name == 'api_oauth_public') {
				continue;
			}
			if(in_array(basename($file),$cas)) {
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
					$success = false;
					if($this->checkCertificateName($name)) {
						$oldDetails = $this->getCertificateDetailsByBasename($name);
						if (!empty($oldDetails) && $oldDetails['type'] == 'up') {
							$this->updateCertificate($oldDetails,_("Imported from file system"));
							$success = true;
						}
					} else {
						$this->saveCertificate(null,$name,_("Imported from file system"),'up');
						$success = true;
					}
					if ($success) {
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
				throw new Exception(_('Unable to remove ca.key'));
			}
		}
		if(file_exists($location . "/ca.crt")) {
			if(!unlink($location . "/ca.crt")) {
				throw new Exception(_('Unable to remove ca.crt'));
			}
		}
		if(file_exists($location . "/ca.cfg")) {
			if(!unlink($location . "/ca.cfg")) {
				throw new Exception(_('Unable to remove ca.cfg'));
			}
		}
		if(file_exists($location . "/tmp.cfg")) {
			if(!unlink($location . "/tmp.cfg")) {
				throw new Exception(_('Unable to remove tmp.cfg'));
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
		} catch(Exception $e) {
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
	 * Update Database about Cert
	 * @param  array $details         Old Cert info
	 * @param  string $description the cert description
	 * @return [type]              [description]
	 */
	public function updateCertificate($oldDetails, $description, $additional=array()) {
		if(!is_array($additional)) {
			$additional = array();
		}
		$sql = "UPDATE certman_certs SET description = ?, additional = ? WHERE cid = ?";
		$sth = $this->db->prepare($sql);
		$sth->execute(array($description,json_encode($additional),$oldDetails['cid']));

		$newDetails = $this->getCertificateDetails($oldDetails['cid']);
		if(empty($newDetails)) {
			throw new Exception("Could not find updated certificates");
		}
		if(is_array($newDetails['files']) && posix_geteuid() === 0) {
			$user = $this->FreePBX->Config->get("AMPASTERISKWEBUSER");
			$group = $this->FreePBX->Config->get("AMPASTERISKWEBGROUP");
			foreach($newDetails['files'] as $file) {
				chown($file,$user);
				chgrp($file,$group);
			}
		}
		$this->FreePBX->Hooks->processHooks($newDetails,$oldDetails);
		//if this is a default certificate then run the default hooks
		if($newDetails['default']) {
			$this->makeCertDefault($oldDetails['cid']);
		}
		return ;
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

		$user = $this->FreePBX->Config->get("AMPASTERISKWEBUSER");
		$group = $this->FreePBX->Config->get("AMPASTERISKWEBGROUP");

		$location = $this->PKCS->getKeysLocation();
		if(!file_exists($location.'/integration')) {
			mkdir($location.'/integration',0777,true);
			if(posix_geteuid() === 0) {
				chown($location."/integration",$user);
				chgrp($location."/integration",$group);
			}
		}

		if(empty($cert['files']['crt']) || empty($cert['files']['key'])) {
			throw new Exception("Unable to make certficate default. Certificates are missing");
		}

		$sslfiles = array("pem" => "certificate.pem", "ca-crt" => "ca-bundle.crt", "crt" => "webserver.crt", "key" => "webserver.key");
		foreach ($sslfiles as $key => $f) {
			if (file_exists($location."/integration/$f")) {
				unlink($location."/integration/$f");
			}
			if(isset($cert['files'][$key])) {
				copy($cert['files'][$key],$location."/integration/$f");
				$cert['integration']['files'][$key] = $location."/integration/$f";
				chmod($location."/integration/$f",0600);
				if(posix_geteuid() === 0) {
					chown($location."/integration/$f",$user);
					chgrp($location."/integration/$f",$group);
				}
			}
		}

		if(isset($cert['files']['pem'])) {
			$this->FreePBX->Config->update("HTTPTLSCERTFILE",$location."/integration/certificate.pem");
		} else {
			$this->FreePBX->Config->update("HTTPTLSCERTFILE",$location."/integration/webserver.crt");
		}
		$this->FreePBX->Config->update("HTTPTLSPRIVATEKEY",$location."/integration/webserver.key");
		$this->FreePBX->Config->update("HTTPTLSENABLE",true);
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
		$data = $sth->fetch(PDO::FETCH_ASSOC);
		if(!empty($data)) {
			$data = $this->getAdditionalCertDetails($data, true);
		} else {
			$data = array(); //jic
		}
		return $data;
	}

	/**
	 * Get Additional Details about a certificate
	 * @param  array $details The previous details
	 * @param  boolean $default If this is a default certificate or not
	 * @return array          $details with more!
	 */
	private function getAdditionalCertDetails($details, $default=false) {
		$location = $this->PKCS->getKeysLocation();
		$files = array(".key" => "key",".crt" => "crt",".csr" => "csr",".pem" => "pem","-ca-bundle.crt" => "ca-bundle");
		$details['files'] = !empty($details['files']) ? $details['files'] : array();
		$details['hashes'] = !empty($details['hashes']) ? $details['hashes'] : array();
		$details['info'] = !empty($details['info']) ? $details['info'] : array();
		foreach($files as $f => $type) {
			$file = $location.'/'.$details['basename'].$f;
			if(file_exists($file)) {
				if(!is_readable($file)) {
					throw new Exception(sprintf(_("Certificate %s is not readable! Can not continue!"),$file));
				}
				$details['files'][$type] = $file;
				if($type == 'crt') {
					$details['info']['crt'] = @openssl_x509_parse(file_get_contents($file));
				}
				$details['hashes'][$type] = sha1_file($file);
			}
		}
		if($default) {
			$details['integration'] = !empty($details['integration']) ? $details['integration'] : array();
			foreach($files as $f => $type) {
				$file = $location.'/integration/webserver'.$f;
				if(file_exists($file)) {
					if(!is_readable($file)) {
						throw new Exception(sprintf(_("Certificate %s is not readable! Can not continue!"),$file));
					}
					$details['integration']['files'][$type] = $file;
					$details['integration']['hashes'][$type] = sha1_file($file);
				}
			}
			$file = $location.'/integration/certificate.pem';
			if(file_exists($file)) {
				if(!is_readable($file)) {
					throw new Exception(sprintf(_("Certificate %s is not readable! Can not continue!"),$file));
				}
				$details['integration']['files']['pem'] = $file;
				$details['integration']['hashes']['pem'] = sha1_file($file);
			}
		}
		if(!empty($details['additional'])) {
			$tmp = @json_decode($details['additional'],true);
			$details['additional'] = !empty($tmp) && is_array($tmp) ? $tmp : array();
		} else {
			$details['additional'] = array();
		}

		// Make sure that additional ALWAYS has an 'email' attribute, even if it's blank
		if (!isset($details['additional']['email'])) {
			$details['additional']['email'] = "";
		}

		return $details;
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
		if('makeDefault' == $_REQUEST['command']){
			$res = $this->makeCertDefault($_POST['id']);
			return array("status" => $res, "message" => "");
		}
		if('getJSON' == $_REQUEST['command'] && 'grid' == $_REQUEST['jdata']){
			return $this->getAllManagedCertificates();
		}
		return false;
	}

	/**
	 * Get FirewallAPI Object
	 *
	 * @return FirewallAPI
	 */
	public function getFirewallAPI() {
		static $api = false;
		if (!$api) {
			if (!class_exists('FirewallAPI')) {
				include __DIR__."/FirewallAPI.class.php";
			}
			$api = new Certman\FirewallAPI();
		}
		return $api;
	}

	/**
	 * Return a human readable expiration date
	 *
	 * This tries to use the 'humanReadable' function from FreePBX 14. If
	 * that is not available, a rough implementation is used instead.
	 * This will also return 'Expired!' if the date is in the past.
	 *
	 * @param timestamp int utime
	 */
	public function getReadableExpiration($timestamp = false) {
		if (!is_numeric($timestamp)) {
			return _("Unknown Expiration");
		}

		$ret = date('Y-m-d', $timestamp)." ";

		// How many seconds until it expires?
		$diff = $timestamp - time();

		// If $diff is negative, it means that the certificate has ALREADY expired.
		if ($diff < 0) {
			$ret .= "<strong>"._("Certificate Expired!")."</strong> ";
		}

		// humanDiff is not available in 13.
		$days = floor($diff/86400);
		$ret .= sprintf(_("(%s days)"), $days);

		return $ret;
	}

	function getPublicIP($host) {
		$ips = array();
		$queries = array(
			array('url' => 'https://dns.google/resolve?name=' . $host . '&type=1&do=1'),
			array('url' => 'https://cloudflare-dns.com/dns-query?name=' . $host . '&type=1&do=1'),
			array('url' => 'https://dns.quad9.net:5053/dns-query?name=' . $host . '$host&type=1&do=1'),
		);
		foreach($queries as $q) {
			$requests = \FreePBX::Curl()->requests($q['url']);
			$options = array(
				'timeout' => 2,
			);
			$headers = array(
				'Accept' => 'application/dns-json', //only needed for quad9, add headers to queries?
			);
			try {
				$response = $requests->get('', $headers, $options);
			} catch(\Exception $e) { continue; }

			$decoded = json_decode($response->body, 1);
			if (!empty($decoded['Answer']) && $decoded['status'] == 0) {
				foreach($decoded['Answer'] as $a) {
					if($a['type'] == 1)  $ips[] = $a['data'];
				}
				break;
			}
		}
		return $ips;
	}
}
