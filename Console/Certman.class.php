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
use Symfony\Component\Console\Helper\TableHelper;
// Process
use Symfony\Component\Process\Process;

class Certman extends Command {
	protected function configure(){
		$this->setName('certificates')
			->setDescription(_('Certificate Management'))
			->setDefinition(array(
				new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$args = $input->getArgument('args');
		$command = isset($args[0])?$args[0]:'';
		$certman = \FreePBX::create()->Certman;
		$pkcs = \FreePBX::create()->PKCS;
		switch ($command) {
			case "updateall":
				$messages = $certman->checkUpdateCertificates();
				foreach($messages as $message) {
					$m = $message['message'];
					switch($message['type']) {
						case "danger":
							$output->writeln(_("<error>".$m."</error>"));
						break;
						case "warning":
							$output->writeln(_("<info>".$m."</info>"));
						break;
						case "success":
							$output->writeln($m);
						break;
					}
				}
			break;
			case "list":
				$certs = $certman->getAllManagedCertificates();
				$rows = array();
				foreach ($certs as $key => $c) {
					$type = '';
					switch($c['type']) {
						case 'ss':
							$type = "Self Signed";
						break;
						case 'le':
							$type = "Let's Encrypt";
						break;
						case 'up':
							$type = 'Uploaded';
						break;
					}
					$default = !empty($c['default']) ? 'X' : '';
					$rows[] = array($key, $c['basename'], $c['description'], $type, $default);
				}
				$table = $this->getHelper('table');
				$table->setHeaders(array("ID", _("Base Name"),_("Description"), _("Type"), _("Default")));
				$table->setRows($rows);
				$table->render($output);
				break;
			case "import":
				$list = $certman->importLocalCertificates();
				if(empty($list)) {
					$loc = $pkcs->getKeysLocation();
					$output->writeln(_("<info>".sprintf(_("No Certificates to import. Try placing a certificate (<name>.crt) and its key (<name>.crt) into %s"),$loc)."</info>"));
					exit(4);
					break;
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
				break;
			case "default":
				$certs = $certman->getAllManagedCertificates();
				if(!isset($args[1])) {
					$output->writeln("<error>"._("The command provided is not valid")."</error>");
					exit(4);
				}
				$id = $args[1];
				if(!isset($certs[$id])) {
					$output->writeln("<error>"._("That is not a valid ID")."</error>");
					exit(4);
				}
				$certman->makeCertDefault($certs[$id]['cid']);
				$output->writeln(sprintf(_("Successfully set %s as the default"),$certs[$id]['basename']));
				break;
			default:
				$loc = $pkcs->getKeysLocation();
				$output->writeln("<error>The command provided is not valid.</error>");
				$output->writeln("Available commands are:");
				$output->writeln("<info>list</info> - List all Certificates");
				$output->writeln("<info>updateall</info> - Check and Update all Certificates");
				$output->writeln("<info>import</info> - Import any certificates in ".$loc);
				$output->writeln("<info>default <id></info> - Set Certificate ID as the system default");
				exit(4);
				break;
		}
	}
}
