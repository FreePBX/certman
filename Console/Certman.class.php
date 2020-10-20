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
				new InputOption('state', null, InputOption::VALUE_REQUIRED, _('State/Provence/Region  (LetsEncrypt Generation)')),
				new InputOption('email', null, InputOption::VALUE_REQUIRED, _("Owner's email (LetsEncrypt Generation)")),
				//new InputOption('description', null, InputOption::VALUE_REQUIRED, _('Certificate Description (Self-Signed Generation)')),

				new InputOption('delete', null, InputOption::VALUE_REQUIRED, _('Delete certificate by id or hostname')),
				new InputOption('default', null, InputOption::VALUE_REQUIRED, _('Set default certificate by id or hostname'))));
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
					$hostname = $input->getOption('hostname');
					$country_code = $input->getOption('country-code');
					$state = $input->getOption('state');
					$email = $input->getOption('email');
					$force = $input->getOption('force');
					$cert = $certman->getCertificateDetailsByBasename($hostname);

					if (!($hostname && $country_code && $state && $email)) {
						$output->writeln("<error>"._("Missing required argument(s) - 'hostname', 'country-code', 'state' and 'email' are required")."</error>");
						exit(4);
					}

					$settings = [
						"countryCode" => $country_code,
						"state" => $state,
						"challengetype" => "http", // https will not work.
						"email" => $email,
					];

					if (!$force && isset($cert['cid'])) {
						$output->writeln("<error>" . sprintf(_("Certificate for '%s' already exists!"), $hostname) . "</error>");
						exit(4);
					}

					try {
						if($force) {
	        					$output->writeln("<info>"._("force update enabled !!!")."</info>");
						}
						$le_result = $certman->updateLE($hostname, $settings, false, $force);
						if (!isset($cert['cid'])) {
							$certificate_id = $certman->saveCertificate(
								null,
								$hostname,
								$hostname,
								'le',
								["C" => $country_code, "ST" => $state, "email" => $email]
							);
						} else {
							$certman->updateCertificate(
								$cert,
								$hostname,
								["C" => $country_code, "ST" => $state, "email" => $email]
							);
						}
					} catch (\Exception $e) {
						$output->writeln("<error>" . $e->getMessage() . "</error>");
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

		if($input->getOption('updateall')) {
			$force = $input->getOption('force');
			if($force) {
        			$output->writeln("force update enabled !!!");
			}
			$messages = $certman->checkUpdateCertificates($force);
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
				$rows[] = array($key, $c['basename'], $c['description'], $type, $default);
			}
			$table = new Table($output);
			$table->setHeaders(array("ID", _("Base Name"),_("Description"), _("Type"), _("Default")));
			$table->setRows($rows);
			$table->render($output);
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
	protected function outputHelp(InputInterface $input, OutputInterface $output)	 {
		$help = new HelpCommand();
		$help->setCommand($this);
		return $help->run($input, $output);
	}
}
