<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

Interface Acmedotsh {

	public function getRawName();
	public function getOptions();

	public function issueCert($domain, $force);
	public function renewCert($domain, $force);
}


