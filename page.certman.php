<?php
show_view(__DIR__.'/views/rnav.php',array());
$certman = FreePBX::Certman();
$pkcs = FreePBX::PKCS();
switch($_REQUEST['action']) {
	case 'ca':
		switch($_POST['type']) {
			case 'generate':
				$pkcs->createConfig($_POST['hostname'],$_POST['orgname']);
				$pkcs->createCA($_POST['passphrase']);
			break;
			case 'upload':
			break;
			case 'delete':
				$files = $pkcs->getAllAuthorityFiles();
				dbug($files);
			break;
			default:
			break;
		}
		$caExists = $certman->checkCAexists();
		show_view(__DIR__.'/views/ca.php',array('caExists' => $caExists));
	break;
	case 'new':
		show_view(__DIR__.'/views/new.php',array());
	break;
	default:
		show_view(__DIR__.'/views/overview.php',array());
	break;
}
