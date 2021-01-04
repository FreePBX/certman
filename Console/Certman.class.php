<?php
// vim: set ai ts=4 sw=4 ft=php:

// Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

// Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// Tables
use Symfony\Component\Console\Helper\Table;
// Terminal
use Symfony\Component\Console\Terminal;
// Process
use Symfony\Component\Process\Process;

use Symfony\Component\Console\Command\HelpCommand;

class Certman extends Command {
	protected function configure(){
		$pkcs = \FreePBX::create()->PKCS;
		$loc = $pkcs->getKeysLocation();
		$this->setName('certificates')
			->setDescription(_('Certificate Management'))
			->setDefinition(array(
				new InputOption('list', null, InputOption::VALUE_NONE, _('List Certificates')),
				new InputOption('updateall', null, InputOption::VALUE_NONE, _('Check and Update all Certificates')),
				new InputOption('force', null, InputOption::VALUE_NONE, _('Force update, by pass 30 days expiry ')),
				new InputOption('import', null, InputOption::VALUE_NONE, sprintf(_('Import any unmanaged certificates in %s'),$loc)),

				// cert generation options
				new InputOption('generate', null, InputOption::VALUE_NONE, _('Generate Certificate')),
				new InputOption('type', null, InputOption::VALUE_REQUIRED, _('Certificate generation type - "le" for LetsEncrypt')),
				new InputOption('hostname', null, InputOption::VALUE_REQUIRED, _('Certificate hostname (LetsEncrypt Generation)')),
				new InputOption('country-code', null, InputOption::VALUE_REQUIRED, _('Country Code (LetsEncrypt Generation)')),
				new InputOption('state', null, InputOption::VALUE_REQUIRED, _('State/Provence/Region (LetsEncrypt Generation)')),
				new InputOption('email', null, InputOption::VALUE_REQUIRED, _("Owner's email (LetsEncrypt Generation)")),
				new InputOption('san', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, _("Certificate Subject Alternative Name(s) (LetsEncrypt Generation)")),

				new InputOption('delete', null, InputOption::VALUE_REQUIRED, _('Delete certificate by id or hostname')),
				new InputOption('default', null, InputOption::VALUE_REQUIRED, _('Set default certificate by id or hostname')),
				new InputOption('details', null, InputOption::VALUE_REQUIRED, _('Display certificate details by id or hostname')),
				new InputOption('json', null, InputOption::VALUE_NONE, _('Format output as json')),
			));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$certman = \FreePBX::create()->Certman;
		$pkcs = \FreePBX::create()->PKCS;

		if($input->getOption('generate')) {
			$type = $input->getOption('type');
			switch($type) {
				case 'ss':
					$output->writeln("<error>".sprintf(_("Certificate type %s generation is not supported at this time"),$type)."</error>");
					break;

				case 'letsencrypt';
				case 'le':
					$hostname = strtolower($input->getOption('hostname'));
					$country_code = $input->getOption('country-code');
					$state = $input->getOption('state');
					$email = $input->getOption('email');
					$description = $hostname;
					$san = array_unique(array_filter(array_map(function ($v) {return strtolower(trim($v));}, $input->getOption('san'))));
					$force = $input->getOption('force');
					$cert = $certman->getCertificateDetailsByBasename($hostname);

					if (!($hostname && $country_code && $state && $email)) {
						$output->writeln("<error>"._("Missing required argument(s) - 'hostname', 'country-code', 'state' and 'email' are required")."</error>");
						exit(4);
					}

					if (!empty($san)) {
						if ($key = array_search($hostname, $san)) {
							unset($key);
						}
						sort($san);
						$description .= ", " . implode(", ", $san);
					}

					$additional = array(
						"C" => $country_code,
						"ST" => $state,
						"email" => $email,
					);
					if (!empty($san)) {$additional['san'] = $san;}

					if ($force) {
						$output->writeln("<info>" . _("Forced update enabled !!!") . "</info>");
					} else {
						if (!empty($cert)) {
							if ($additional == $cert['additional']) {
								$output->writeln("<info>" . sprintf(_("Certificate '%s' exists, no changes made, nothing to do"), $hostname) . "</info>");
								exit(4);
							} else {
								$force = true;
							}
						}
					}

					try {
						$settings = array(
							"countryCode" => $country_code,
							"state" => $state,
							"challengetype" => "http", // https will not work.
							"email" => $email,
							"san" => $san
						);

						$le_result = $certman->updateLE($hostname, $settings, false, $force);
						if (!isset($cert['cid'])) {
							$cid = $certman->saveCertificate(
								null,
								$hostname,
								$description,
								'le',
								$additional
							);
						} else {
							$certman->updateCertificate(
								$cert,
								$description,
								$additional
							);
						}
					} catch (\Exception $e) {
						$einfo = json_decode(substr($e->getMessage(), strpos($e->getMessage(), '{')), true);
						if (!empty($einfo['detail'])) {
							$emessage = $einfo['detail'];
							$output->writeln($e->getMessage()); //append raw message to log output
						} else {
							$emessage = $e->getMessage();
						}
						$this->showhints($certman, $output);
						$output->writeln("<error>LetsEncrypt Update Failure:");
						$output->writeln($emessage . "</error>");
						exit(4);
					}

					if ($le_result) {
						$output->writeln(sprintf(_("Successfully installed Let's Encrypt certificate '%s'"), $hostname));
					}

					break;

				case 'up':
					$output->writeln("<error>"._("Use --import instead")."</error>");
					break;

				case 'default':
				default:
					$certs = $certman->getAllManagedCertificates();
					if(empty($certs)) {
						$output->writeln(_("No Certificates exist"));

						if(!$certman->checkCAexists()) {
							$output->write(_("Generating default CA..."));
							$hostname = gethostname();
							$hostname = !empty($hostname) ? $hostname : 'localhost';
							$caid = $certman->generateCA('ca', $hostname, $hostname);
							$output->writeln(_("Done!"));
						} else {
							$dat = $certman->getAllManagedCAs();
							$caid = $dat[0]['uid'];
						}

						$output->write(_("Generating default certificate..."));
						// Do not i18n the NAME of the cert, it is 'default'.
						try {
							$cid = $certman->generateCertificate($caid,"default",_("Default Self-Signed certificate"));
							$certman->makeCertDefault($cid);
							$output->writeln(_("Done!"));
						} catch(\Exception $e) {
							$output->writeln("<error>".sprintf(_("Failed! [%s]"),$e->getMessage())."</error>");
							//return false;
						}
					} else {
						$output->writeln(_("Certificates already exist, no need to generate another one"));
					}
				break;
			}
			return;
		}

		if($input->getOption('delete') !== null) {
			$id = $input->getOption('delete');

			if (is_numeric($id)) {
				$certs = $certman->getAllManagedCertificates();
				$cid = $certs[$id]['cid'];
				$hostname = $certs[$id]['basename'];
			} else {
				$cert = $certman->getCertificateDetailsByBasename($id);
				$cid = $cert['cid'];
				$hostname = $cert['basename'];
			}

			if (!isset($cid)) {
				$output->writeln("<error>".sprintf(_("'%s' is not a valid ID"), $id)."</error>");
				exit(4);
			}

			$certman->removeCertificate($cid);
			$output->writeln(sprintf(_("Deleted certificate '%s'"),$hostname));
			return;
		}

		if($input->getOption('details') !== null) {
			$id = $input->getOption('details');

			if (is_numeric($id)) {
				$certs = $certman->getAllManagedCertificates();
				$cert = $certman->getCertificateDetails($certs[$id]['cid']);
			} else {
				$cert = $certman->getCertificateDetailsByBasename($id);
			}

			if (empty($cert)) {
				$output->writeln("<error>".sprintf(_("'%s' is not a valid ID"), $id)."</error>");
				exit(4);
			}

			print($input->getOption('json') ? json_encode($cert) : print_r($cert, true));
			print("\n");
			return;
		}

		if($input->getOption('updateall')) {
			$force = $input->getOption('force');
			if($force) {
				$output->writeln("<info>" . _("Forced update enabled !!!") . "</info>");
			}
			$messages = $certman->checkUpdateCertificates($force);
			foreach($messages as $message) {
				if ($message['type'] == "danger") {
					$this->showhints($certman, $output);
					$output->writeln("<error>LetsEncrypt Update Failure:</error>");
					break;
				}
			}
			foreach($messages as $message) {
				$m = $message['message'];
				switch($message['type']) {
					case "danger":
						$output->writeln("<error>".$m."</error>");
					break;
					case "warning":
						$output->writeln("<info>".$m."</info>");
					break;
					case "success":
						$output->writeln($m);
					break;
				}
			}
			return;
		}

		if($input->getOption('list')) {
			$certs = $certman->getAllManagedCertificates();
			$rows = array();
			foreach ($certs as $key => $c) {
				$type = '';
				switch($c['type']) {
					case 'ss':
						$type = _("Self Signed");
					break;
					case 'le':
						$type = _("Let's Encrypt");
					break;
					case 'up':
						$type = _('Uploaded');
					break;
				}
				$default = !empty($c['default']) ? 'X' : '';
				if($input->getOption('json')) {
					$rows[] = array($key, $c['basename'], $c['description'], $c['type'], $type, $default, $c['additional']);
				} else {
					$rows[] = array($key, $c['basename'], $c['description'], $type, $default);
				}
			}
			if($input->getOption('json')) {
				print(json_encode($rows));
				print("\n");
			} else {
				$table = new Table($output);
				$table->setHeaders(array("ID", _("Base Name"),_("Description"), _("Type"), _("Default")));
				$table->setRows($rows);
				$table->render($output);
			}
			return;
		}

		if($input->getOption('import')) {
			$list = $certman->importLocalCertificates();
			if(empty($list)) {
				$loc = $pkcs->getKeysLocation();
				$output->writeln(_("<info>".sprintf(_("No Certificates to import. Try placing a certificate (<name>.crt) and its key (<name>.key) into %s"),$loc)."</info>"));
				exit(4);
			}
			$err = false;
			foreach($list as $i) {
				if($i['status']) {
					$output->writeln(_("<info>".sprintf(_("Successfully imported %s"),basename($i['file']))."</info>"));
				} else {
					$err = true;
					$output->writeln("<error>".sprintf(_("There was an error importing %s. The error was: %s"),basename($i['file']),$i['error'])."</error>");
				}
			}
			if($err) {
				exit(4);
			}
			return;
		}

		if($input->getOption('default') !== null) {
			$id = $input->getOption('default');

			if (is_numeric($id)) {
				$certs = $certman->getAllManagedCertificates();
				$cid = $certs[$id]['cid'];
				$hostname = $certs[$id]['basename'];
			} else {
				$cert = $certman->getCertificateDetailsByBasename($id);
				$cid = $cert['cid'];
				$hostname = $cert['basename'];
			}

			if (!isset($cid)) {
				$output->writeln("<error>".sprintf(_("'%s' is not a valid ID"), $id)."</error>");
				exit(4);
			}

			$certman->makeCertDefault($cid);
			$output->writeln(sprintf(_("Successfully set '%s' as the default certificate"),$hostname));
			return;
		}

		$this->outputHelp($input,$output);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 * @throws \Symfony\Component\Console\Exception\ExceptionInterface
	 */
	protected function outputHelp(InputInterface $input, OutputInterface $output) {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}

	private function showhints($certman, OutputInterface $output) {
		$api = $certman->getFirewallAPI();
		$leoptions = $api->getLeOptions();
		$terminal = new Terminal;
		$width = $terminal->getWidth() - 10;
		$rows = array();
		if (!empty($leoptions['hints'])) {
			$bullets = array();
			$output->writeln('');
			foreach($leoptions['hints'] as $hint) {
				$rows[] = array('*', $hint);
				$wrapped = explode("\n",$this->tagwrap($hint, $width));
				$leader = '<comment>   ** ';
				foreach($wrapped as $line) {
					$bullets[] = $leader . $line;
					$leader = '      ';
				}
				$bullets[] = '';
			}
			$output->writeln(implode("\n", $bullets));
		}
	}

	// wrap ignoring tags -  is there an existing library function for this?
	private function tagwrap(&$str, $maxLength){
		$eol = "\n";
		$count = 0;
		$tag = 0;
		$newStr = '';
		$openTag = false;
		$lenstr = strlen($str);
		for($i=0; $i<$lenstr; $i++){
			$newStr .= $str[$i];
			if($str[$i] == '<'){
				$openTag = true;
				$tag++;
				continue;
			}
			if($openTag && $str[$i] == '>'){
				$openTag = false;
				$tag++;
				continue;
			}
			if ($openTag) {
				$tag++;
				continue;
			}
			if(!$openTag){
				if($str[$i] == $eol){
					$count = 0;
					$lastspace = 0;
					continue;
				}
				if($str[$i] == ' '){
					if ($count == 0) {
						$newStr = substr($newStr, 0, -1);
						$tag = 0;
						continue;
					} else {
						$tag = 0;
						$lastspace = $count + 1;
					}
				}
				$count++;
				if($count==$maxLength){
					if ($str[$i+1] != ' ' && $lastspace && ($lastspace < $count)) {
						$tmp = ($count - $lastspace)* -1;
						$newStr = substr($newStr, 0, $tmp - $tag) . $eol . substr($newStr, $tmp - $tag);
						$count = $tmp * -1;
					} else {
						$newStr .= $eol;
						$count = 0;
					}
					$lastspace = 0;
				}
			}
		}
		return $newStr;
	}
}