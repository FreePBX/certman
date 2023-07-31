<?php

$ampsbin = FreePBX::Config()->get("AMPSBIN");
foreach(FreePBX::Cron()->getAll() as $cron) {
	$str = "fwconsole certificates updateall -q";
	if(preg_match("/".$str."/i",$cron,$matches)) {
		FreePBX::Cron()->remove($cron);
	}
	$str = "fwconsole certificates --updateall -q";
	if(preg_match("/".$str."/i",$cron,$matches)) {
		FreePBX::Cron()->remove($cron);
	}
}
FreePBX::Cron()->add(array(
	"command" => $ampsbin."/fwconsole certificates --updateall -q 2>&1 >/dev/null",
	"hour" => rand(0,3),
	"minute" => rand(0,59),
));

$freepbx_conf = freepbx_conf::create();
$set['value'] = '730';
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'certman';
$set['category'] = 'Certificate Manager';
$set['emptyok'] = 1;
$set['name'] = 'Validity period of the certificate (in days)';
$set['description'] = 'You can change the number of days the certificate is valid.';
$set['type'] = CONF_TYPE_TEXT;
$freepbx_conf->define_conf_setting('CERT_DAYS_VAL',$set,true);

$set['value'] = 30;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 0;
$set['module'] = 'certman';
$set['category'] = 'Certificate Manager';
$set['emptyok'] = false;
$set['name'] = 'Renewel alert threshold (in days)';
$set['description'] = 'Number of days before a certificate expiration for sending an alert by mail.';
$set['type'] = CONF_TYPE_INT;
$freepbx_conf->define_conf_setting('CERT_DAYS_EXPIRATION_ALERT',$set,true);

// Fix Let's Encrypt DST-Root-CA-X3 issue
$m = \module_functions::create();
$distro = $m->_distro_id();

// Only run this on SNG7
if ($distro['pbx_type'] === "freepbxdistro" && FreePBX::Modules()->checkStatus("sysadmin")) {
	if (!file_exists('/etc/pki/ca-trust/source/blacklist/DST-Root-CA-X3.pem')) {
		FreePBX::Certman()->runHook("fix-le-root-ca");
	}
}
