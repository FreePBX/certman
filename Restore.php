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
    foreach($configs['managedCerts'] as $cert){
      try{
	      $this->certman->saveCertificate($cert['caid'], $cert['basename'], $cert['description'], $cert['type'], $cert['additional']);
      }catch(\Exception $e){
 	echo $e->getMessage().PHP_EOL;
      }
    }

    foreach($configs['managedCSRs'] as $csr){
      $this->certman->saveCSR($csr);
    }    

    foreach($configs['dtlsOptions'] as $dtls){
      $this->certman->addDTLSOptions($dtls['id'], $dtls);
    }
  }
}
