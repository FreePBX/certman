<?php

namespace FreePBX\modules\Certman;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class Job implements \FreePBX\Job\TaskInterface
{
	public static function run(InputInterface $input, OutputInterface $output)
	{
		$output->writeln("Starting Certman update check...");

		$certman = \FreePBX::Certman();

		//copied straight from the Console class (updateall)
		$messages = $certman->checkUpdateCertificates();
		$hints = array();
		foreach ($messages as $message) {
			if (!empty($message['hints'])) {
				$hints = array_merge($hints, $message['hints']);
			}
			if ($message['type'] == "danger") {
				$danger = true;
			}
		}
		if ($danger) {
			$certman->showhints($certman, $output, array_unique($hints));
		}
		foreach ($messages as $message) {
			$m = $message['message'];
			switch ($message['type']) {
				case "danger":
					$output->writeln("<error>" . $m . "</error>");
					break;
				case "warning":
					$output->writeln("<comment>" . $m . "</comment>");
					break;
				case "success":
					$output->writeln("<info>" . $m . "</info>");
					break;
			}
		}

		$output->writeln("Finished");
		return true;
	}
}
