<?php

namespace FreePBX\modules\Certman\LetsEncrypt;

Interface Acmesh {

	public function getRawName();
	public function getOptions();

	public function issueCert($domain);
	public function renewCert($domain);
}


