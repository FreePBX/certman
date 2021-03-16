<?php

namespace FreePBX\modules\Certman\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

/**
 * Certman
 */
class Certman extends Base {
	protected $module = 'certman';
	
	/**
	 * queryCallback
	 *
	 * @return void
	 */
	public function queryCallback() {
		if($this->checkAllReadScope()) {
		return function(){
		 return [
			'fetchCSRFile' => [
				'type' => $this->typeContainer->get('certman')->getObject(),
				'description' => _('Download the CSR Certificate'),
				'args' => Relay::connectionArgs(),
				'resolve' => function($root) {
					$res = $this->downloadCSR();
					if(!$res['status']){
						return ['status' => false , 'message' => $res['message']];
					}else{
						return ['message'=> _("Please find the CSR file contant"), 'status' => true, 'fileContant' => file_get_contents($res['file'])];
					}
				  },
				],
			'deleteCSRFile' => [
				'type' => $this->typeContainer->get('certman')->getObject(),
				'description' => _('Delete the CSR Certificate'),
				'args' => Relay::connectionArgs(),
				'resolve' => function($root) {
					$status = $this->freepbx->certman->removeCSR(true);
					if($status) {
						return array('status' => true, 'message' => _('Successfully deleted the Certificate Signing Request'));
					} else {
						return array('status' => false, 'message' => _('Unable to remove the Certificate Signing Request'));
				}},],
		];};}
	}
	
	/**
	 * mutationCallback
	 *
	 * @return void
	 */
	public function mutationCallback() {

		if($this->checkAllWriteScope()) {
			return function() {
				return [
					'generateCSR' => Relay::mutationWithClientMutationId([
						'name' => _('generateCSR'),
						'description' => _('Generate CSR'),
						'inputFields' => $this->getCSRInputFields(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							$params = $this->resolveNames($input);
							return $this->generateCSR($params);
						}
					]),
					'uploadSSLCertificate' => Relay::mutationWithClientMutationId([
						'name' => _('uploadSSLCertificate'),
						'description' => _('Upload an SSL certificate'),
						'inputFields' => $this->getSSLInputFields(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							return $this->uploadSSL($input);
						}
					])
				];
			};
		}

	}
		
	/**
	 * initializeTypes
	 *
	 * @return void
	 */
	public function initializeTypes() {
		$certman = $this->typeContainer->create('certman');
		$certman->setDescription(_('Generate Certificate'));

		$certman->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});
		
		$certman->addFieldCallback(function() {
			return [
				'id' => Relay::globalIdField('certman', function($row) {
					return "";
				}),	
				'message' =>[
					'type' => Type::string(),
					'description' => _('Message for the request')
				],
				'status' =>[
					'type' => Type::boolean(),
					'description' => _('Status for the request')
				],
				'fileContant' =>[
					'type' => Type::string(),
					'description' => _('')
				]
			];
		});
	}
	
	/**
	 * getCSRInputFields
	 *
	 * @return void
	 */
	private function getCSRInputFields(){
		return [
			'name' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The base name of the certificate, Can only contain alphanumeric characters')
         ],
         'hostName' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('Host name')
			],
         'organizationName' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('Organization Name such as Sangoma Technologies, Inc.')
			],
         'organizationUnit' => [
				'type' => Type::string(),
				'description' => _('Organizational Unit. This can be a doing business as (DBA) name, or the name of a department within the business. This may be left blank.')
			],
         'country' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('Two letter country code, such as "US", "CA", or "AU".')
			],
         'state' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('State or province such as "Queensland" or "Wisconsin" or "Ontario." Do not abbreviate. Enter the full name.')
			],
         'city' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('City name such as "Toronto" or "Brisbane." Do not abbreviate. For example, enter "Saint Louis" not "St. Louis"')
			]
		];
	}
			
	/**
	 * getSSLInputFields
	 *
	 * @return void
	 */
	private function getSSLInputFields(){
		return [
			'name' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The base name of the certificate, Can only contain alphanumeric characters')
         ],
			'description' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The Description of this certificate. Used in the module only')
         ],
			'passPhrase' => [
				'type' => Type::string(),
				'description' => _('The Passphrase of the Private Key. This will be used to decrypt the private key and the certificate. They will be stored unpassworded on the system to prevent service disruptions.')
         ],
			'privateKey' => [
				'type' => Type::string(),
				'description' => _('If you have a separate private key paste it here.')
         ],
			'CSRReference' => [
				'type' => Type::string(),
				'description' => _('Certificate Signing Request to reference. Select "None" to upload your own private key.')
         ],
			'signedCertificate' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('After you have submitted a CSR to a CA, they will sign it, after validation, and return a Signed Certificate. That certificate should be pasted in the box below. If you leave this box blank, the certificate will not be updated.')
         ],
			'trustedChain' => [
				'type' => Type::string(),
				'description' => _('our CA may also require a Trusted Chain to be installed. This will be provided by the CA, and will consist of one, or multiple, certificate files. Paste the contents of all the Chain files, if any, into the box below. This may be left blank, or updated at any time. They can be added in any order.')
         ],
		];
	}

	/**
	 * getOutputFields
	 *
	 * @return void
	 */
	private function getOutputFields(){
		return [
			'status' => [
				'type' => Type::boolean(),
				'description' => _('API status')
			],	
			'message' => [
				'type' => Type::string(),
				'description' => _('API message')
			]		
		];
	}
   
   /**
    * generateCSR
    *
    * @param  mixed $input
    * @return void
    */
   private function generateCSR($params){
      $this->PKCS = $this->freepbx->PKCS;
      $location = $this->PKCS->getKeysLocation();
		$name = basename($params['name']);
		if($this->freepbx->certman->checkCertificateName($name) || $this->freepbx->certman->checkCSRName($name)) {
			return array('status' => false, 'message' => _('Certificate name is already in use'));
		}
		try {
			$this->PKCS->createCSR($name, $params);
			$this->freepbx->certman->saveCSR($name);
		} catch(Exception $e) {
			return array('status' => false, 'message' => sprintf(_('Unable to create CSR: %s'),$e->getMessage()));
		}
		return array('status' => true, 'message' => _('Added new certificate signing request'));
	}
	
	/**
	 * resolveNames
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function resolveNames($input){
		$params['CN'] = $input['hostName']; 
		$params['O'] = isset($input['organizationName']) ? $input['organizationName'] : '' ; 
		$params['OU'] = $input['organizationUnit']; 
		$params['C'] = $input['country']; 
		$params['ST'] = $input['state']; 
		$params['L'] = $input['city']; 
		$params['name'] = $input['name']; 

		return $params;
	}
	
	/**
	 * downloadCSR
	 *
	 * @return void
	 */
	private function downloadCSR(){
		$this->PKCS = $this->freepbx->PKCS;
		$csrs = $this->freepbx->certman->getAllManagedCSRs();
		if(empty($csrs)){
			return ['status'=> false, 'message' => _('Sorry unable to find any CSR file')];
		}
		$file = $this->PKCS->getKeysLocation()."/".$csrs[0]['basename'].".csr";
		if(file_exists($file) && !empty($csrs[0]['basename']) ) {
			return ['status'=> true, 'file' => $file];
		}
	}
	
	/**
	 * uploadSSL
	 *
	 * @param  mixed $input
	 * @return void
	 */
	private function uploadSSL($input){
		$input['passPhrase'] = isset($input['passPhrase']) ? $input['passPhrase'] : '';
		$input['privateKey'] = isset($input['privateKey']) ? $input['privateKey'] : '';
		$input['CSRReference'] = isset($input['CSRReference'] ) ?  $input['CSRReference'] : '';
		$input['trustedChain'] = isset($input['trustedChain'] ) ?  $input['trustedChain'] : '';
	
		return $this->freepbx->PKCS->certObj($this->freepbx)->uploadSSLCertificate($input);
	}
}
