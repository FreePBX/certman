<?php
namespace FreePBX\modules\Certman;
use FreePBX\modules\Backup as Base;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$files = $this->getFiles();
		$dirs = $this->getDirs();
		$this->certman = $this->FreePBX->Certman;
		$keyDir = $this->certman->PKCS->getKeysLocation();
		$originalKeyDir = $configs['keyDir'];
		foreach ($dirs as $dir) {
			$dir = str_Replace($originalKeyDir,$keyDir,$dir->getPathTo());
			@mkdir($dir,0755,true);
		}
		foreach ($files as $file) {
			$backupFilename = $file->getPathTo() . '/' . $file->getFilename();
			$dir = str_Replace($originalKeyDir, $keyDir, $file->getPathTo());
			$newFilename = $dir . '/' . $file->getFilename();
			if (file_exists($this->tmpdir.'/files/'.$backupFilename)) {
			 copy($this->tmpdir . '/files/' . $file->getPathTo() . '/' . $file->getFilename(), $newFilename);
			}
		}
		$this->processConfigs($configs);
	}

	public function processConfigs($configs){
		$this->certman = $this->FreePBX->Certman;
		foreach ($configs['managedCerts'] as $cert) {
			try {
				$this->certman->saveCertificate($cert['caid'], $cert['basename'], $cert['description'], $cert['type'], $cert['additional']);
			} catch (\Exception $e) {
				echo $e->getMessage() . PHP_EOL;
			}
		}

		foreach ($configs['managedCSRs'] as $csr) {
			$this->certman->saveCSR($csr);
		}
		foreach ($configs['dtlsOptions'] as $dtls) {
			$dtls['certificate'] = $dtls['cid'];
			$this->certman->addDTLSOptions($dtls['id'], $dtls);
		}
		return $this;
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$this->restoreLegacyAll($pdo);
		$this->certman = $this->FreePBX->Certman;
		$keyDir = $this->certman->PKCS->getKeysLocation();
		$this->log(_("Checking Certificate files on backup /etc/asterisk/keys"));
		if(!file_exists($this->tmpdir.'/etc/asterisk/keys')) {
			$this->log(_("Cerificate files are not found on Legacy backup !"));
			return;
		}
		$finder = new Finder();
		$fileSystem = new Filesystem();
		foreach ($finder->in($this->tmpdir.'/etc/asterisk/keys') as $item) {
			if($item->isDir()) {
				$fileSystem->mkdir($keyDir.'/'.$item->getRelativePathname());
				continue;
			}
			$fileSystem->copy($item->getPathname(), $keyDir.'/'.$item->getRelativePathname(), true);
		}
	}
}