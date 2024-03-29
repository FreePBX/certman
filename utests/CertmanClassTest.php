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

	public function testCertman_doDialplanHook_whenAddingPJSipExtension_shouldSetupPJSipConfig() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
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
		
		$this->assertTrue(in_array('type=endpoint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('media_encryption=dtls', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_verify=fingerprint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_cert_file={$defaultCertificate['files']['crt']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_private_key={$defaultCertificate['files']['key']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_setup=actpass', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_rekey=0', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
	}

	public function testCertman_doDialplanHook_whenNotAddingPJSipExtension_shouldNotSetupPJSipConfig() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
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
		$filterTestExtensions  = [0, $testExtension['extension']];
		$filteredConfig = array_filter(
			$config['pjsip.endpoint.conf'],
			function ($key) use ($filterTestExtensions) {
				return in_array($key, $filterTestExtensions);
			},
			ARRAY_FILTER_USE_KEY
		);
		$this->assertEquals([
			0 => '#include pjsip.endpoint_custom.conf',
		], $filteredConfig);
	}

	public function testCertman_doDialplanHook_whenAddingPJSipExtension_withAutoGenerateCertEnabled_shouldSetupDtlsConfigWithoutCerts() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
		\FreePBX::Core()->delUser($testExtension['extension']);
		\FreePBX::Core()->delDevice($testExtension['extension']);

		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get', 'get_conf_setting'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.2.0');
		$stubConfig->method('get_conf_setting')
			->willReturn('chan_pjsip');
		self::$freepbx->Config = $stubConfig;

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

		\FreePBX::Certman()->addDTLSOptions($testExtension['extension'], [
			'enable' => 'yes',
			'verify' => 'fingerprint',
			'setup' => 'actpass',
			'rekey' => 0,
			'auto_generate_cert' => 1,
		]);

		$fakeExt = [];
		self::$app->doDialplanHook($fakeExt, null, null);

		$config = self::$freepbx->PJSip->genConfig();		
		$this->assertEquals('#include pjsip.endpoint_custom.conf', 
			$config['pjsip.endpoint.conf'][0]);

		$defaultCertificate = \FreePBX::Certman()->getDefaultCertDetails();
		$this->assertTrue(in_array('type=endpoint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('media_encryption=dtls', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_verify=fingerprint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_setup=actpass', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_rekey=0', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_auto_generate_cert=yes', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertFalse(in_array("dtls_cert_file={$defaultCertificate['files']['crt']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertFalse(in_array("dtls_private_key={$defaultCertificate['files']['key']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
	}

	public function testCertman_doDialplanHook_whenAddingPJSipExtension_withAutoGenerateCertDisabled_shouldSetupDtlsConfigWithCerts() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
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
			'auto_generate_cert' => 0,
		]);

		$fakeExt = [];
		self::$app->doDialplanHook($fakeExt, null, null);

		$config = self::$freepbx->PJSip->genConfig();		
		$this->assertEquals('#include pjsip.endpoint_custom.conf', 
			$config['pjsip.endpoint.conf'][0]);

		$this->assertTrue(in_array('type=endpoint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('media_encryption=dtls', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_verify=fingerprint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_setup=actpass', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_rekey=0', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertFalse(in_array('dtls_auto_generate_cert=yes', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_cert_file={$defaultCertificate['files']['crt']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_private_key={$defaultCertificate['files']['key']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
	}

	public function testCertman_doDialplanHook_whenAddingPJSipExtension_withAutoGenerateCertPreviouslyEnabled_butAsteriskHasBeenDowngradedTo15_1_shouldSetupDtlsConfigWithDefaultCerts() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
		\FreePBX::Core()->delUser($testExtension['extension']);
		\FreePBX::Core()->delDevice($testExtension['extension']);

		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get', 'get_conf_setting'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.1.0');
		$stubConfig->method('get_conf_setting')
			->willReturn('chan_pjsip');
		self::$freepbx->Config = $stubConfig;

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

		$dtlsOptions = [
			'id' => $testExtension['extension'],
			'enable' => 'yes',
			'certificate' => null,
			'verify' => 'fingerprint',
			'setup' => 'actpass',
			'rekey' => 0,
			'auto_generate_cert' => 1,
		];
		$sql = "INSERT INTO certman_mapping (
			id,	cid, verify, setup, rekey, auto_generate_cert
		) VALUES (?, ?, ?, ?, ?, ?)";
		$sth = self::$freepbx->Database->prepare($sql);
		$sth->execute([
			$dtlsOptions['id'],
			$dtlsOptions['certificate'],
			$dtlsOptions['verify'],
			$dtlsOptions['setup'],
			$dtlsOptions['rekey'],
			$dtlsOptions['auto_generate_cert']
		]);

		$fakeExt = [];
		self::$app->doDialplanHook($fakeExt, null, null);

		$config = self::$freepbx->PJSip->genConfig();		
		$this->assertEquals('#include pjsip.endpoint_custom.conf', 
			$config['pjsip.endpoint.conf'][0]);

		$defaultCertificate = \FreePBX::Certman()->getDefaultCertDetails();
		$this->assertTrue(in_array('type=endpoint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('media_encryption=dtls', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_verify=fingerprint', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_setup=actpass', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array('dtls_rekey=0', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertFalse(in_array('dtls_auto_generate_cert=yes', $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_cert_file={$defaultCertificate['files']['crt']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
		$this->assertTrue(in_array("dtls_private_key={$defaultCertificate['files']['key']}", $config['pjsip.endpoint.conf'][$testExtension['extension']]));
	}

	public function testCertman_addDTLSOptions_whenAddingPJSipExtension_withAutoGenerateCertDisabledAndNoCertificatesSpecified_shoudThrowAnException() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
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

		try {
			\FreePBX::Certman()->addDTLSOptions($testExtension['extension'], [
				'enable' => 'yes',
				'verify' => 'fingerprint',
				'setup' => 'actpass',
				'rekey' => 0,
			]);	

			$this->fail("Expected exception not thrown");
		} catch(\Exception $e) { 
			$this->assertEquals("DTLS certificate file not specified", $e->getMessage());
		}
	}

	public function testCertman_addDTLSOptions_whenAddingPJSipExtension_withAutoGenerateCertEnabledAndAsteriskVersionLessThan15_2_shoudThrowAnException() {
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

		// Clearning up any previous tests
		\FreePBX::Certman()->removeDTLSOptions($testExtension['extension']);
		\FreePBX::Core()->delUser($testExtension['extension']);
		\FreePBX::Core()->delDevice($testExtension['extension']);

		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.1.0');
		self::$freepbx->Config = $stubConfig;

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

		try {
			\FreePBX::Certman()->addDTLSOptions($testExtension['extension'], [
				'enable' => 'yes',
				'verify' => 'fingerprint',
				'setup' => 'actpass',
				'rekey' => 0,
				'auto_generate_cert' => 1,
			]);	

			$this->fail("Expected exception not thrown");
		} catch(\Exception $e) { 
			$this->assertEquals("DTLS autogenerate certificate option not available", $e->getMessage());
		}
	}

	public function testCertman_pjsipDTLSAutoGenerateCertSupported_whenAsteriskVersionLessThan15_2_shoudReturnFalse() {
		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.1.0');
		self::$freepbx->Config = $stubConfig;

		$this->assertFalse(\FreePBX::Certman()->pjsipDTLSAutoGenerateCertSupported());
	}

	public function testCertman_pjsipDTLSAutoGenerateCertSupported_whenAsteriskVersionEquals15_2_shoudReturnTrue() {
		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.2.0');
		self::$freepbx->Config = $stubConfig;

		$this->assertTrue(\FreePBX::Certman()->pjsipDTLSAutoGenerateCertSupported());
	}

	public function testCertman_pjsipDTLSAutoGenerateCertSupported_whenAsteriskVersionGreaterThan15_2_shoudReturnTrue() {
		$stubConfig = $this->getMockBuilder(\FreePBX\Config::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->setMethods(array('get'))
			->getMock();
		$stubConfig->method('get')
			->willReturn('15.2.1');
		self::$freepbx->Config = $stubConfig;

		$this->assertTrue(\FreePBX::Certman()->pjsipDTLSAutoGenerateCertSupported());
	}
}
