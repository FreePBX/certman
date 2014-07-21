<?php
$certman = FreePBX::Certman();
$message = array();
$html = '';
switch($_REQUEST['action']) {
	case 'ca':
		$type = !empty($_POST['type']) ? $_POST['type'] : "";
		switch($type) {
			case 'generate':
				$sph = (!empty($_POST['savepassphrase']) && $_POST['savepassphrase'] == 'yes') ? true : false;
				$certman->generateCA('ca',$_POST['hostname'],$_POST['orgname'],$_POST['passphrase'],$sph);
			break;
			case 'upload':
				if ($_FILES["privatekey"]["error"] > 0) {
					$message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["privatekey"]["error"]));
					break;
				} else {
					$pi = pathinfo($_FILES["privatekey"]['name']);
					if($pi['extension'] != 'key') {
						$message = array('type' => 'danger', 'message' => _('Private Key Doesnt Appear to be a key file'));
						break;
					} else {
						move_uploaded_file($_FILES["privatekey"]["tmp_name"],'/etc/asterisk/keys/ca.key');
					}
				}

				if ($_FILES["certificate"]["error"] > 0) {
					$message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["certificate"]["error"]));
					break;
				} else {
					$pi = pathinfo($_FILES["certificate"]['name']);
					if($pi['extension'] != 'crt') {
						$message = array('type' => 'danger', 'message' => _('Certificate Doesnt Appear to be a crt file'));
						break;
					} else {
						move_uploaded_file($_FILES["certificate"]["tmp_name"],'/etc/asterisk/keys/ca.crt');
					}
				}
				$certman->generateConfig('ca',$_POST['hostname'],$_POST['orgname']);
				$certman->saveCA('ca',$_POST['hostname'],$_POST['orgname'],$_POST['passphrase']);
			break;
			case 'delete':
				$certman->removeCA();
			break;
			default:
			break;
		}
		$caExists = $certman->checkCAexists();
		$html = load_view(__DIR__.'/views/ca.php',array('caExists' => $caExists, 'message' => $message));
	break;
	case 'new':
		$cas = $certman->getAllManagedCAs();
		if(!empty($cas)) {
			if($certman->checkCertificateName($_POST['name'])) {
				$message = array('type' => 'danger', 'message' => _('Certificate Already Exists'));
			} else {
				$type = !empty($_POST['type']) ? $_POST['type'] : "";
				switch($type) {
					case 'generate':
						$ca = $certman->getCADetails($_POST['ca']);
						$certman->generateCertificate($_POST['ca'],$_POST['name'],$_POST['description'],$ca['passphrase']);
					break;
					case 'upload':
						if ($_FILES["privatekey"]["error"] > 0) {
							$message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["privatekey"]["error"]));
							break;
						} else {
							$pi = pathinfo($_FILES["privatekey"]['name']);
							if($pi['extension'] != 'key') {
								$message = array('type' => 'danger', 'message' => _('Private Key Doesnt Appear to be a key file'));
								break;
							} else {
								move_uploaded_file($_FILES["privatekey"]["tmp_name"],'/etc/asterisk/keys/'.$_POST['name'].'.key');
							}
						}

						if ($_FILES["certificate"]["error"] > 0) {
							$message = array('type' => 'danger', 'message' => _('Error Uploading ' . $_FILES["certificate"]["error"]));
							break;
						} else {
							$pi = pathinfo($_FILES["certificate"]['name']);
							if($pi['extension'] != 'crt') {
								$message = array('type' => 'danger', 'message' => _('Certificate Doesnt Appear to be a crt file'));
								break;
							} else {
								move_uploaded_file($_FILES["certificate"]["tmp_name"],'/etc/asterisk/keys/'.$_POST['name'].'.crt');
							}
						}
						$certman->saveCertificate($_POST['ca'],$_POST['name'],$_POST['description']);
					break;
					default:
					break;
				}
			}
			$html = load_view(__DIR__.'/views/new.php',array('cas' => $cas, 'message' => $message));
		}
	break;
	case 'view':
		$type = !empty($_POST['type']) ? $_POST['type'] : "";
		switch($type) {
			case 'update':
				$message = array('type' => 'success', 'message' => _('Updated Certificate'));
			break;
			default:
			break;
		}
		$cert = $certman->getCertificateDetails($_REQUEST['id']);
		if(!empty($cert)) {
			$html = load_view(__DIR__.'/views/view.php',array('cert' => $cert, 'message' => $message));
			break;
		}
	case 'delete':
		$certman->removeCertificate($_REQUEST['id']);
		$message = array('type' => 'success', 'message' => _('Deleted Certificate'));
	default:
		$html = load_view(__DIR__.'/views/overview.php',array());
	break;
}
$certs = $certman->getAllManagedCertificates();
show_view(__DIR__.'/views/rnav.php',array("certs" => $certs));
echo $html;
