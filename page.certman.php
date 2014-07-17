<?php
$certman = FreePBX::Certman();
show_view(__DIR__.'/views/rnav.php',array());
switch($_REQUEST['action']) {
	case 'ca':
		switch($_POST['type']) {
			case 'generate':
				$sph = (!empty($_POST['savepassphrase']) && $_POST['savepassphrase'] == 'yes') ? true : false;
				$certman->generateCA($_POST['hostname'],$_POST['orgname'],$_POST['passphrase'],$sph);
			break;
			case 'upload':
			break;
			case 'delete':
				$certman->removeCA();
			break;
			default:
			break;
		}
		$caExists = $certman->checkCAexists();
		show_view(__DIR__.'/views/ca.php',array('caExists' => $caExists));
	break;
	case 'new':
		$cas = $certman->getAllManagedCAs();
		if(!empty($cas)) {
			switch($_POST['type']) {
				case 'generate':
					$certman->generateCertificate($_POST['ca'],$_POST['name'],$_POST['certificate']);
				break;
				case 'upload':
				break;
				case 'delete':
				break;
				default:
				break;
			}
			show_view(__DIR__.'/views/new.php',array('cas' => $cas));
		}
	break;
	default:
		show_view(__DIR__.'/views/overview.php',array());
	break;
}
