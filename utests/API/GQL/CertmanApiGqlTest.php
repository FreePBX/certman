<?php 

namespace FreepPBX\certman\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\certman;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

/**
 * CertmanGqlApiTest
 */
class CertmanGqlApiTest extends ApiBaseTestCase {
    protected static $certman;
        
    /**
     * setUpBeforeClass
     *
     * @return void
     */
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$certman = self::$freepbx->Certman;
    }
        
    /**
     * tearDownAfterClass
     *
     * @return void
     */
    public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
    }
     
   /**
    * test_generate_CSR_when_parameters_required_not_sent_should_return_false
    *
    * @return void
    */
   public function test_generate_CSR_when_parameters_required_not_sent_should_return_false(){
      $response = $this->request("mutation {
       generateCSR(input: { name: \"test\" 
         hostName: \"test\" 
         organizationName: \"test\" 
         organizationUnit: \"test\" 
         country: \"US\" 
         state: \"california\" 
         }) {
          message status 
         }
      }");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Field generateCSRInput.city of required type String! was not provided.","status":false}]}',$json);
      $this->assertEquals(400, $response->getStatusCode());
   }
   
   /**
    * test_generate_CSR_when_parameters_required_sent_should_return_true
    *
    * @return void
    */
   public function test_generate_CSR_when_parameters_required_sent_should_return_true(){
      $response = $this->request("mutation {
       generateCSR(input: { name: \"test\" 
         hostName: \"test\" 
         organizationName: \"test\" 
         organizationUnit: \"test\" 
         country: \"US\" 
         state: \"california\" 
         city: \"san diego\"
         }) {
          message status 
         }
      }");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"generateCSR":{"message":"Added new certificate signing request","status":true}}}',$json);
      
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_generate_CSR_when_certificate_already_exists_should_return_false
    *
    * @return void
    */
   public function test_generate_CSR_when_certificate_already_exists_should_return_false(){
      $response = $this->request("mutation {
       generateCSR(input: { name: \"test\" 
         hostName: \"test\" 
         organizationName: \"test\" 
         organizationUnit: \"test\" 
         country: \"US\" 
         state: \"california\" 
         city: \"san diego\"
         }) {
          message status 
         }
      }");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"errors":[{"message":"Certificate name is already in use","status":false}]}',$json);
      
      $this->assertEquals(400, $response->getStatusCode());
   }
   
   /**
    * test_fetch_CSR_when_certificate_exists_should_return_true
    *
    * @return void
    */
   public function test_fetch_CSR_when_certificate_exists_should_return_true(){
      $response = $this->request("{fetchCSRFile{ 
         status 
         message 
         fileContant
      }}");
      
      $json = (string)$response->getBody();
      $data = json_decode($json)->data;
      $data = $data->fetchCSRFile;

      $this->assertNotEmpty($data->fileContant);
      $this->assertEquals(true,$data->status);
      $this->assertEquals("Please find the CSR file contant",$data->message);
      $this->assertEquals(200, $response->getStatusCode());
   }
   
   /**
    * test_delete_CSR_when_certificate_exists_should_return_true
    *
    * @return void
    */
   public function test_delete_CSR_when_certificate_exists_should_return_true(){
      $response = $this->request("mutation{
         deleteCSRFile(input: {}){
            status
            message
         }}");
      
      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"deleteCSRFile":{"status":true,"message":"Successfully deleted the Certificate Signing Request"}}}',$json);

      $this->assertEquals(200, $response->getStatusCode());
   }
 
 /**
  * test_uploadSSLCertificate_certificate_already_exists_should_return_false
  *
  * @return void
  */
 public function test_uploadSSLCertificate_certificate_already_exists_should_return_false(){
   $mockcertman = $this->getMockBuilder(\FreePBX\modules\certman\Certman::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('uploadSSLCertificate'))
      ->getMock();
      
	$mockcertman->method('uploadSSLCertificate')
		->willReturn(array('status' => false, 'message' => ('Certificate name is already in use')));
    
   self::$freepbx->PKCS->setcertObj($mockcertman); 

   $response = $this->request("mutation {
       uploadSSLCertificate(input: { name: \"test\" 
         description: \"test\" 
         passPhrase: \"test\" 
         privateKey: \"test\" 
         CSRReference: \"test\" 
         signedCertificate: \"testcertificate\" 
         trustedChain: \"\"
         }) {
          message status 
         }
      }");

   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Certificate name is already in use","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
   }
 
 /**
  * test_uploadSSLCertificate_no_private_key_should_return_false
  *
  * @return void
  */
 public function test_uploadSSLCertificate_no_private_key_should_return_false(){
   $mockcertman = $this->getMockBuilder(\FreePBX\modules\certman\Certman::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('uploadSSLCertificate'))
      ->getMock();
      
	$mockcertman->method('uploadSSLCertificate')
		->willReturn(array('status' => false, 'message' => ('No Private key to reference. Try generating a CSR first.')));
    
   self::$freepbx->PKCS->setcertObj($mockcertman); 

   $response = $this->request("mutation {
       uploadSSLCertificate(input: { name: \"test\" 
         description: \"test\" 
         passPhrase: \"test\" 
         privateKey: \"test\" 
         CSRReference: \"test\" 
         signedCertificate: \"testcertificate\" 
         trustedChain: \"\"
         }) {
          message status 
         }
      }");

   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"No Private key to reference. Try generating a CSR first.","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
   }
 
 /**
  * test_uploadSSLCertificate_all_good_should_return_false
  *
  * @return void
  */
 public function test_uploadSSLCertificate_all_good_should_return_false(){
   $mockcertman = $this->getMockBuilder(\FreePBX\modules\certman\Certman::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('uploadSSLCertificate'))
      ->getMock();
      
	$mockcertman->method('uploadSSLCertificate')
		->willReturn(array('status' => true, 'message' => ('Added new certificate.')));
    
   self::$freepbx->PKCS->setcertObj($mockcertman); 

   $response = $this->request("mutation {
      uploadSSLCertificate(input: { name: \"test\" 
      description: \"test\" 
      passPhrase: \"test\" 
      privateKey: \"test\" 
      CSRReference: \"test\" 
      signedCertificate: \"testcertificate\" 
      trustedChain: \"\"
      }) {
         message status 
      }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"uploadSSLCertificate":{"message":"Added new certificate.","status":true}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
   }

   /**
    * test_deleteCertificate_all_good_should_return_true
    *
    * @return void
    */
   public function test_deleteCertificate_all_good_should_return_true(){
      $mockcertman = $this->getMockBuilder(\FreePBX\modules\certman\Certman::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('removeCertificate'))
      ->getMock();
      
	$mockcertman->method('removeCertificate')
		->willReturn(true);
    
   self::$freepbx->PKCS->setcertObj($mockcertman); 

   $response = $this->request("mutation{
      deleteCertificate(input: {cid : \"2\"}){
         message
         status
      }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"data":{"deleteCertificate":{"message":"Successfully deleted the SSL certificate","status":true}}}',$json);
      
   $this->assertEquals(200, $response->getStatusCode());
   }

   public function test_deleteCertificate_when_false_should_return_false(){
      $mockcertman = $this->getMockBuilder(\FreePBX\modules\certman\Certman::class)
		->disableOriginalConstructor()
		->disableOriginalClone()
		->setMethods(array('removeCertificate'))
      ->getMock();
      
	 $mockcertman->method('removeCertificate')
		->willReturn(false);
    
   self::$freepbx->PKCS->setcertObj($mockcertman); 

   $response = $this->request("mutation{
      deleteCertificate(input: {cid : \"2\"}){
         message
         status
      }}");

   $json = (string)$response->getBody();
   $this->assertEquals('{"errors":[{"message":"Unable to delete the SSL certificate","status":false}]}',$json);
      
   $this->assertEquals(400, $response->getStatusCode());
   }
}