<?php
namespace FreePBX\modules\Certman;
use FreePBX\modules\Backup as Base;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
class Backup Extends Base\BackupBase{
  public $dirs = [];
  public function runBackup($id,$transaction){
    $this->certman = $this->FreePBX->Certman;
    $this->buildFileStructure()
      ->addDirectories($this->dirs);
    $this->addDependency('core');
    $this->addConfigs($this->buildConfigs());
  }

  public function buildConfigs(){
    return [
      'managedCerts' => $this->certman->getAllManagedCertificates(),
      'managedCSRs' => $this->certman->getAllManagedCSRs(),
      'dtlsOptions' => $this->certman->getAllDTLSOptions(),
      'keyDir' => $this->certman->PKCS->getKeysLocation()
    ];
  }
  
  public function buildFileStructure(){
    $keyDir = $this->certman->PKCS->getKeysLocation();
    $this->dirs[] = $keyDir;
    $directory = new RecursiveDirectoryIterator($keyDir);
    $iterator = new RecursiveIteratorIterator($directory);
    foreach ($iterator as $fileObj) {
      if($fileObj->isDir()){
        $this->dirs[] = $fileObj->getPath();
        continue;
      }
      $this->addFile($fileObj->getBasename(), $fileObj->getPath(), '', $fileObj->getExtension());
    }
    $this->dirs = array_unique($this->dirs);
    return $this;
  }
}
