<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

Class Cloudflare Extends Base {

	public $weblink = "https://github.com/Neilpang/acme.sh/blob/master/dnsapi/README.md";

	// public $staging = false;

	public function getRawName() {
		return "cloudflare";
	}

	public function getOptions() {
		return [
			"cf-key" => [ 
				"text" => _("Cloudflare Key"), 
				"default" => "",
				"placeholder" => _("CF Key - Like 0123456789abcdef0123456789abcdef01234"),
				"changeable" => true,
			],
			"cf-email" => [ 
				"text" => _("CloudFlare Email"), 
				"default" => "",
				"placeholder" => _("Email address linked to API key"),
				"changeable" => true,
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

