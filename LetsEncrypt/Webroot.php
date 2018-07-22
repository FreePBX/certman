<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

Class Webroot Extends Base Implements Acmesh {

	// public $staging = false;

	public function getRawName() {
		return "webroot";
	}

	public function getOptions() {
		return [
			"webroot" => [ 
				"text" => _("Webroot - Automatically Detected"), 
				"default" => \FreePBX::Config()->get("AMPWEBROOT"),
				"changeable" => false
			],
		];
	}

	public function issueCert($cert, $force = false) {
		$vals = $this->loadOptions();
		$hooks = "--pre-hook '".__DIR__."/cert-pre-hook \\\$domain' --post-hook '".__DIR__."/cert-post-hook \\\$domain' ";
		if ($force) {
			$cmd = "--issue --force -d $cert $hooks -w ".$vals['webroot'];
		} else {
			$cmd = "--issue -d $cert $hooks -w ".$vals['webroot'];
		}
		return $this->run($cmd);
	}

	public function renewCert($cert, $force = false) {
		$vals = $this->loadOptions();
		if ($force) {
			$cmd = "--renew --force -d $cert -w ".$vals['webroot'];
		} else {
			$cmd = "--renew -d $cert -w ".$vals['webroot'];
		}
		return $this->run($cmd);
	}
}
