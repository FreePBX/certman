<?php
namespace FreePBX\modules\Certman;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore($jobid){
		$configs = $this->getConfigs();
		$configs = reset($configs);
		$files = $this->getFiles();
		$dirs = $this->getDirs();
		$this->certman = $this->FreePBX->Certman;
		$keyDir = $this->certman->PKCS->getKeysLocation();
		$originalKeyDir = $configs['keyDir'];
		foreach ($dirs as $dir) {
			$dir = str_Replace($originalKeyDir,$keyDir,$dir);
			@mkdir($dir,0755,true);
		}
		foreach ($files as $file) {
			$backupFilename = $file['pathto'] . '/' . $file['filename'];
			$dir = str_Replace($originalKeyDir, $keyDir, $file['pathto']);
			$newFilename = $dir . '/' . $file['filename'];
			if (file_exists($this->tmpdir.'/files/'.$backupFilename)) {
			 copy($this->tmpdir . '/files/' . $file['pathto'] . '/' . $file['filename'], $newFilename);
			}
		}
		$this->processConfigs($configs);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables, $tmpfiledir){
		$tables = array_flip($tables + $unknownTables);
		if (!isset($tables['certman_mapping'])) {
			return $this;
		}
		$cm = $this->FreePBX->Certman;
		$cm->setDatabase($pdo);
		$configs = [
			'managedCerts' => $cm->getAllManagedCertificates(),
			'managedCSRs' => $cm->getAllManagedCSRs(),
			'dtlsOptions' => $cm->getAllDTLSOptions(),
			'keyDir' => $cm->PKCS->getKeysLocation()
		];
		$cm->resetDatabase();
		$this->processConfigs($configs);
		foreach (new \DirectoryIterator($tmpfiledir.$configs['keyDir']) as $fileInfo) {
			if ($fileInfo->isDot()){
        continue;
      }
			@copy($fileInfo->getPathname(), $configs['keyDir'].'/'.$fileInfo->getFilename());
		}
		return $this;
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
			$this->certman->addDTLSOptions($dtls['id'], $dtls);
		}
		return $this;
	}
}
