<?php

namespace FreePBX\modules\Certman\utests;

require __DIR__ . '/../vendor/autoload.php';

class CertmanClassTest extends \PHPUnit_Framework_TestCase
{
	protected static $freepbx;
	protected static $faker;	
	protected static $app;

	public static function setUpBeforeClass()
	{
		self::$freepbx = \FreePBX::create();

		self::$faker = \Faker\Factory::create();
		self::$app = self::$freepbx->Certman;
	}

	public static function tearDownAfterClass()
	{
		while (!empty(self::$extensions)) {
			$exten = array_pop(self::$extensions);
			\FreePBX::Core()->delUser($exten);
			\FreePBX::Core()->delDevice($exten);
		}

		while (!empty(self::$userIds)) {
			$userId = array_pop(self::$userIds);
			self::$freepbx->userman->deleteUserByID($userId);
		}
	}

	public function testCertman_doDialplanHook_whenAddingPJSipExension_shouldSetupPJSipConfig() {
		$fname = self::$faker->firstName;
		$lname = self::$faker->lastName;
		$testExtension = array(
			"username" => 'certman-'.self::$faker->userName,
			"password" => self::$faker->password,
			"fname" => $fname,
			"lname" => $lname,
			"displayname" => $fname." ".$lname,
			"extension" => '969898'
		);

		\FreePBX::Core()->delUser($testExtension['extension']);
		\FreePBX::Core()->delDevice($testExtension['extension']);

		$settings = \FreePBX::Core()->generateDefaultDeviceSettings(
			'pjsip', 
			$testExtension['extension'],
			'certman test'
		);
		\FreePBX::Core()->addDevice($testExtension['extension'], "pjsip", $settings);
		$dev = \FreePBX::Core()->getDevice($testExtension['extension']);
		if(empty($dev)) {
			throw new \Exception("Device not created!");
			die();
		}

		$defaultCertificate = \FreePBX::Certman()->getDefaultCertDetails();
		\FreePBX::Certman()->addDTLSOptions($testExtension['extension'], [
			'enable' => 'yes',
			'certificate' => $defaultCertificate['cid'],
			'verify' => 'fingerprint',
			'setup' => 'actpass',
			'rekey' => 0,
		]);

		$fakeExt = [];
		self::$app->doDialplanHook($fakeExt, null, null);

		$config = self::$freepbx->PJSip->genConfig();		
		$this->assertEquals('#include pjsip.endpoint_custom.conf', 
			$config['pjsip.endpoint.conf'][0]);
		
		$numberOfSetOptions = 38;
		$this->assertEquals(
			count($config['pjsip.endpoint.conf'][$testExtension['extension']]), 
			$numberOfSetOptions
		);

		$this->assertTrue(in_array('type=endpoint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('media_encryption=dtls', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_verify=fingerprint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_cert_file={$defaultCertificate['files']['crt']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_private_key={$defaultCertificate['files']['key']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_setup=actpass', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_rekey=0', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
	}

	public function testCertman_doDialplanHook_whenNotAddingPJSipExension_shouldNotSetupPJSipConfig() {
		$fname = self::$faker->firstName;
		$lname = self::$faker->lastName;
		$testExtension = array(
			"username" => 'certman-'.self::$faker->userName,
			"password" => self::$faker->password,
			"fname" => $fname,
			"lname" => $lname,
			"displayname" => $fname." ".$lname,
			"extension" => '969898'
		);

		\FreePBX::Core()->delUser($testExtension['extension']);
		\FreePBX::Core()->delDevice($testExtension['extension']);

		$settings = \FreePBX::Core()->generateDefaultDeviceSettings(
			'pjsip', 
			$testExtension['extension'],
			'certman test'
		);
		\FreePBX::Core()->addDevice($testExtension['extension'], 'nothing', $settings);
		$dev = \FreePBX::Core()->getDevice($testExtension['extension']);
		if(empty($dev)) {
			throw new \Exception("Device not created!");
			die();
		}

		$defaultCertificate = \FreePBX::Certman()->getDefaultCertDetails();
		\FreePBX::Certman()->addDTLSOptions($testExtension['extension'], [
			'enable' => 'yes',
			'certificate' => $defaultCertificate['cid'],
			'verify' => 'fingerprint',
			'setup' => 'actpass',
			'rekey' => 0,
		]);

		$fakeExt = [];
		self::$app->doDialplanHook($fakeExt, null, null);

		$config = self::$freepbx->PJSip->genConfig();
		$this->assertEquals([
			0 => '#include pjsip.endpoint_custom.conf',
		], $config['pjsip.endpoint.conf']);
	}
}