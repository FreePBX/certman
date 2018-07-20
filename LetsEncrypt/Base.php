<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

class Base {

	public $staging = true;

	public function run($cmd) {
		if ($this->staging) {
			$cmd = "~/.acme.sh/acme.sh --staging $cmd 2>&1";
		} else {
			$cmd = "~/.acme.sh/acme.sh $cmd 2>&1";
		}

		if (posix_getuid() == 0) {
			$user = \FreePBX::Config()->get("AMPASTERISKUSER");
			$cmd = "runuser $user -s /bin/bash -c \"$cmd\"";
		}
		exec($cmd, $output, $ret);
		return [ "cmd" => $cmd, "output" => $output, "ret" => $ret ];
	}

	public function loadOptions() {
		$mod = $this->getRawName();

		$o = $this->getOptions();
		$retarr = [];
		foreach ($o as $k => $vars) {
			if (isset($vars['changeable']) && !$vars['changeable']) {
				$retarr[$k] = $vars['default'];
			} else {
				$retarr[$k] = \FreePBX::Certman()->getConfig($k, "le-$mod");
			}
		}
		return $retarr;
	}

	public function saveOptions($opts) {
		$mod = $this->getRawName();
		$o = $this->getOptions();
		foreach ($o as $k => $vars) {
			if (!isset($vars['changeable']) || $vars['changeable']) {
				if (isset($opts[$k])) {
					\FreePBX::Certman()->setConfig($k, $opts[$k], "le-$mod");
				}
			}
		}
	}

	public function getAccountEmail() {
		$pw = posix_getpwnam(\FreePBX::Config()->get("AMPASTERISKUSER"));
		if (empty($pw['dir'])) {
			throw new \Exception("Unable to find home dir of ".\FreePBX::Config()->get("AMPASTERISKUSER"));
		}
		if (!file_exists($pw['dir']."/.acme.sh/account.conf")) {
			return "";
		}
		$conf = file($pw['dir']."/.acme.sh/account.conf", \FILE_IGNORE_NEW_LINES|\FILE_SKIP_EMPTY_LINES);
		foreach ($conf as $l) {
			if (preg_match("/^ACCOUNT_EMAIL='(.+)'$/", $l, $out)) {
				return $out[1];
			}
		}
		return "";
	}

	public function updateAccountEmail($newemail) {
		// TODO: Better sanity checking?
		if (strlen($newemail) < 4 || strpos($newemail, "@") === false || strpos($newemail, "'") !== false || strpos($newemail, '\\') !== false) {
			throw new \Exception("Invalid email $newemail");
		}
		return $this->run("--updateaccount --accountemail '$newemail'");
	}

	// Note - this ONLY EVER NEEDS TO BE RUN ONCE. This configures
	// acme.sh with where to put the output, and what files to run,
	// after the cert has been renewed successfully. install-cert should
	// then restart httpd etc.
	public function activateCertificate($certname) {
		$location = \FreePBX::PKCS()->getKeysLocation();
		$key   = "--key-file $location/$certname.key ";
		$cert  = "--cert-file $location/$certname.crt ";
		$chain = "--fullchain-file $location/$certname-ca-bundle.crt ";
		$hook  = "--reloadcmd '".__DIR__."/cert-install \\\$domain' ";
		$cmd   = "--install-cert -d $certname $hook $key $cert $chain";

		// We can't really do anything if this fails, I guess?
		var_dump($this->run($cmd));

		// This needs to be moved to install-cert
		//
		// Create complete pem for Nodejs/nginx etc
            	$key = file_get_contents("$location/$certname.key");
            	$cert = file_get_contents("$location/$certname.crt");
            	$chain = file_get_contents("$location/$certname-ca-bundle.crt");

            	file_put_contents($location."/".$certname.".pem",$key."\n".$cert."\n".$chain."\n");
            	chmod("$location/$certname.crt",0600);
            	chmod("$location/$certname.key",0600);
            	chmod("$location/$certname.pem",0600);
		chmod("$location/$certname-ca-bundle.crt",0600);
	}

}

