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
}

