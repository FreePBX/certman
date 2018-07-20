<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

Class Webroot Extends Base Implements Acmesh {

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
		if ($force) {
			$cmd = "--issue --force -d $cert -w ".$vals['webroot'];
		} else {
			$cmd = "--issue -d $cert -w ".$vals['webroot'];
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
