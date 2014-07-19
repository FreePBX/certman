<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

function certman_configpageinit($pagename) {
	global $currentcomponent;
	global $amp_conf;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;
	$supported_hardware = array('sip','pjsip');

    // We only want to hook the 'extensions' pages.
	$th = !empty($_REQUEST['tech_hardware']) ? $_REQUEST['tech_hardware'] : '';
	if ($pagename != 'extensions' && in_array(str_replace('_generic','',$th),$supported_hardware))  {
		return true;
	}

	if ($tech_hardware != null || $extdisplay != '' || $action == 'add') {
		$currentcomponent->addguifunc("certman_{$pagename}_configpageload");
		if (!empty($action)) {
			$currentcomponent->addprocessfunc("certman_{$pagename}_configprocess");
		}
	}
}

function certman_devices_configpageload() {
	certman_configpageload('devices');
}

function certman_extensions_configpageload() {
	certman_configpageload('extension');
}

function certman_configpageload($mode) {
	global $amp_conf;
	global $currentcomponent;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$currentcomponent->setoptlistopts('dtls_enable', 'sort', false);

	$settings = FreePBX::Certman()->getDTLSOptions($extdisplay);
	dbug($settings);
	$currentcomponent->addguielem('DTLS', new gui_selectbox(
		'dtls_enable',
		array(
			array("text" => "No", "value" => "no"),
			array("text" => "Yes","value" => "yes")
		),
		$settings['enable'],
		_('Enable DTLS'),
		_('Enable or disable DTLS-SRTP support'),
		false)
	);
	$certs = array();
	foreach(FreePBX::Certman()->getAllManagedCertificates() as $cert) {
		$certs[] = array(
			"text" => $cert['basename'],
			"value" => $cert['cid']
		);
	}
	$currentcomponent->addguielem('DTLS', new gui_selectbox(
		'dtls_certificate',
		$certs,
		'actpass',
		_('Use Certificate'),
		_("The Certificate to use from Certificate Manager"),
		false)
	);
	$currentcomponent->addguielem('DTLS', new gui_selectbox(
		'dtls_verify',
		array(
			array("text" => "Yes","value" => "yes"),
			array("text" => "No", "value" => "no"),
			array("text" => "Fingerprint","value" => "fingerprint"),
			array("text" => "Certificate","value" => "certificate")
		),
		$settings['verify'],
		_('DTLS Verify'),
		_("Verify that provided peer certificate and fingerprint are valid
		<ul>
			<li>A value of 'yes' will perform both certificate and fingerprint verification</li>
			<li>A value of 'no' will perform no certificate or fingerprint verification</li>
			<li>A value of 'fingerprint' will perform ONLY fingerprint verification</li>
			<li>A value of 'certificate' will perform ONLY certficiate verification</li>
			</ul>"),
		false)
	);
	$currentcomponent->addguielem('DTLS', new gui_selectbox(
		'dtls_setup',
		array(
			array("text" => "Act/Pass","value" => "actpass"),
			array("text" => "Active", "value" => "active"),
			array("text" => "Passive","value" => "passive")
		),
		$settings['setup'],
		_('DTLS Setup'),
		_("Whether we are willing to accept connections, connect to the other party, or both.
		This value will be used in the outgoing SDP when offering and for incoming SDP offers when the remote party sends actpass
		<ul>
			<li>active (we want to connect to the other party)</li>
			<li>passive (we want to accept connections only)</li>
			<li>actpass (we will do both)</li>
			</ul>"),
		false)
	);
	//
	$currentcomponent->addguielem('DTLS', new gui_textbox(
		'dtls_rekey',
		$settings['rekey'],
		_('DTLS Rekey Interval'),
		_('Interval at which to renegotiate the TLS session and rekey the SRTP session. If this is not set or the value provided is 0 rekeying will be disabled'),
		'',
		'',
		false
	));
}

function certman_extensions_configprocess() {
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extension = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$tech = isset($_POST['tech'])?$_POST['tech']:null;
	switch ($action) {
		case 'add':
			$extension = isset($_REQUEST['extension']) ? $_REQUEST['extension'] : null;
		case 'edit':
			if($_POST['dtls_enable'] == 'yes') {
				$settings = array();
				foreach($_POST as $key => $value) {
					if(preg_match('/^dtls_(.*)/',$key,$matches)) {
						$settings[$matches[1]] = $value;
					}
				}
				FreePBX::Certman()->addDTLSOptions($extension,$settings);
			} else {
				FreePBX::Certman()->removeDTLSOptions($extension);
			}
		break;
		case 'del':
			FreePBX::Certman()->removeDTLSOptions($extension);
		break;
	}
}

function certman_users_configprocess() {

}
