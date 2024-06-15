<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\CRM\Bitrix24Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\ClientException;
use Log;

class KaskadController extends Controller
{

    protected $token = [
        "expires"=> 1717596552,
        "baseDomain"=> "bitrix.494.by",
        "accessToken"=> "88716066006e74b40068c0eb000004720000073d421badce02def73dd2ec3bcf7516b7",
        "refreshToken"=> "78f08766006e74b40068c0eb00000472000007ced39b10ec61326dfb0595b03072ecee",
    ];
	protected $settings = [
        "crmType"=> "lead",
        "clientId"=> "local.6660467aa43a10.98257669",
        "clientSecret"=> "q4ir2xu0dxZ622CkJ8hkd8Poa1JoWjVdoICnQe0974GGP933UF",
    ];

    public function call($method, $params = [])
	{

		$url = 'https://'.$this->token['baseDomain'].'/rest/'.$method.'.json';
		$this->checkToken();
		$params['auth'] = $this->token['accessToken'];
		return $this->callCurl($url, $params);

	}

    public function checkToken()
	{
		if($this->token['expires'] > time() + 100){
			return true;
		}

		$newToken = $this->callCurl('https://oauth.bitrix.info/oauth/token/', 
			[
				'client_id'     => $this->settings['clientId'],
				'grant_type'    => 'refresh_token',
				'client_secret' => $this->settings['clientSecret'],
				'refresh_token' => $this->token['refreshToken'],
		]);

		$newTokenData = $this->token;
		$newTokenData['accessToken'] = $newToken['access_token'];
		$newTokenData['expires'] = time() + intval($newToken['expires_in']);
		$newTokenData['refreshToken'] = $newToken['refresh_token'];

		$this->token = $newTokenData;

	}

    protected function callCurl($url, $params)
	{
		$client = new Client();

		$options = [
		    'allow_redirects' => [
		        'max' => 10
		    ],
		    'headers' => [
		        'User-Agent' => 'Bitrix24 CRest PHP 1.0'
		    ],
		    'curl'=> [
		    	CURLOPT_POSTREDIR => 3
		    ]
		];


		if (!empty($params)) {
		    $options['form_params'] = $params;
		}

		$result = null;

		try {
			$response = $client->request('POST', $url, $options);
			
			
			$result = json_decode($response->getBody(), true);

		} catch (ClientException $e) {
			Log::error($e);
			$e = json_decode($e, true);
    		if(!empty($e[ 'error' ])){
				if($e[ 'error' ] == 'expired_token')
				{
					Log::error('expired_token');
					$this->checkToken();
					$params['auth'] = $this->token['accessToken'];
					return $this->callCurl($url, $params);
				}
				
				$arErrorInform = [
					'expired_token'          => 'expired token, cant get new auth? Check access oauth server.',
					'invalid_token'          => 'invalid token, need reinstall application',
					'invalid_grant'          => 'invalid grant, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
					'invalid_client'         => 'invalid client, check out define C_REST_CLIENT_SECRET or C_REST_CLIENT_ID',
					'QUERY_LIMIT_EXCEEDED'   => 'Too many requests, maximum 2 query by second',
					'ERROR_METHOD_NOT_FOUND' => 'Method not found! You can see the permissions of the application: CRest::call(\'scope\')',
					'NO_AUTH_FOUND'          => 'Some setup error b24, check in table "b_module_to_module" event "OnRestCheckAuth"',
					'INTERNAL_SERVER_ERROR'  => 'Server down, try later'
				];
				if(!empty($arErrorInform[ $e[ 'error' ] ]))
				{
					$result[ 'error_information' ] = $arErrorInform[ $e[ 'error' ] ];
				}
				
			}

			Log::error('Catch Bitrix 24, '. json_encode($result));
			return $result;
    		

		} 

		return $result;

	}

    public function updateContact(Request $request) 
    {
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
		$instanceFields = $this->call(
			'crm.contact.fields',
		);
        $instanceList  = $this->call(
			'crm.contact.list',
			[
				'filter' => [
					'UF_CRM_1717673519088' => $request['clientId']
				]
			]
		);
		$fields = [
			"NAME"=> $request['firstName'],
			"LAST_NAME"=> $request['lastName'],
			"SECOND_NAME"=> $request['middleName'],
			"BIRTHDATE"=> $request['birthDate'],
			"PHONE"=> [[ "VALUE"=> $request['phoneNumber'], "VALUE_TYPE"=> "WORK" ]],	
			"EMAIL"=> [[ "VALUE"=> $request['e-mail'], "VALUE_TYPE"=> "WORK" ]],	
			"ADDRESS"=> $request['adress'],	
			'UF_CRM_1717673519088' => $request['clientId'],
			'UF_CRM_1717074924654' => $request['citizenship'],
			'UF_CRM_1717075072232' => $request['passportID'],
			'UF_CRM_1717075164335' => $request['createDate'],
			'UF_CRM_1717075194966' => $request['consentPersonalData'],
			'UF_CRM_1717674789996' => $instanceFields['result']['UF_CRM_1717674789996']['items'][$request['consentPersonalData']]['ID'],
			'UF_CRM_1717075227060' => $request['consentReklama'],
			'UF_CRM_1717075237278' => $request['consentSMSVisit'],
			'UF_CRM_1717075246162' => $request['consentRecommendation'],
			'UF_CRM_1717075258294' => $request['consentAnalysisE-mail'],
			'UF_CRM_1717075267328' => $request['consentSMSQuality'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.contact.add',
				[
					'FIELDS' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.contact.update',
				[
					'id'=>$instanceList['result'][0]['ID'],
					'FIELDS' => $fields
				]
			);
		}
    }
    public function updateRegistration(Request $request) 
    {
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 133,
				'filter' => [
					'ufCrm6_1717069419772' => $request['visitId']
				]
			]
		);
		$statuses = [
			'reserved' => 'DT133_10:NEW',
			'completed' => 'DT133_10:SUCCESS',
			'cancelled' => 'DT133_10:FAIL',
		];
		$policies = [
			'policy' => '58',
			'contract' => '59',
			'cash' => '60',
		];
		$fields = [
			'stageId' => $statuses[$request['status']],
			'ufCrm6_1717069419772' => $request['visitId'],
			'ufCrm6_1717069427922' => $request['timeslotId'],
			'ufCrm6_1717069989709' => $request['startDateTime'],
			'ufCrm6_1717070013542' => $request['endDateTime'],
			'ufCrm6_1717069911400' => $request['userIdCreaterVisit'],
			'ufCrm6_1717757159930' => $request['userIdPaidVisit'],
			'begindate' => $request['visitCreateDateTime'],
			'closedate' => $request['visitCancelDateTime'],
			'ufCrm6_1717075348543' => $policies[$request['paymentType']],
			'ufCrm6_1717756581518' => $request['policyId'],
			'ufCrm6_1717757774625' => $request['policyNumber'],
			'ufCrm6_1717756615624' => $request['policyStartDate'],
			'ufCrm6_1717756629307' => $request['policyEndDate'],
			'ufCrm6_1717756761823' => $request['companyId'],
			'ufCrm6_1717756772642' => $request['company'],
			'ufCrm6_1717756837171' => $request['contractId'],
			'ufCrm6_1717756869464' => $request['contractNumber'],
			'ufCrm6_1717756884255' => $request['contractStartDate'],
			'ufCrm6_1717756900527' => $request['contractEndDate'],
			'ufCrm6_1717756905956' => $request['branchId'],
			'ufCrm6_1717756914479' => $request['branch'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.item.add',
				[
					"entityTypeId" => 133,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.item.update',
				[
					"entityTypeId" => 133,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }
    public function updateSuggestedRegistration(Request $request) 
    {
		$typeList  = $this->call(
			'crm.type.list',
		);
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 133,
				'filter' => [
					'ufCrm6_1717069419772' => $request['visitId']
				]
			]
		);
		$statuses = [
			'reserved' => 'DT133_10:NEW',
			'completed' => 'DT133_10:SUCCESS',
			'cancelled' => 'DT133_10:FAIL',
		];
		$policies = [
			'policy' => '58',
			'contract' => '59',
			'cash' => '60',
		];
		$fields = [
			'stageId' => $statuses[$request['status']],
			'begindate' => $request['visitCreateDateTime'],
			'closedate' => $request['visitCancelDateTime'],
			'ufCrm6_1717075348543' => $policies[$request['paymentType']],
			'ufCrm6_1717756581518' => $request['policyId'],
			'ufCrm6_1717757774625' => $request['policyNumber'],
			'ufCrm6_1717756615624' => $request['policyStartDate'],
			'ufCrm6_1717756629307' => $request['policyEndDate'],
			'ufCrm6_1717756761823' => $request['companyId'],
			'ufCrm6_1717756772642' => $request['company'],
			'ufCrm6_1717756837171' => $request['contractId'],
			'ufCrm6_1717756869464' => $request['contractNumber'],
			'ufCrm6_1717756884255' => $request['contractStartDate'],
			'ufCrm6_1717756900527' => $request['contractEndDate'],
			'ufCrm6_1717756905956' => $request['branchId'],
			'ufCrm6_1717756914479' => $request['branch'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.item.add',
				[
					"entityTypeId" => 133,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.item.update',
				[
					"entityTypeId" => 133,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }
    public function updateDoctors(Request $request) 
    {
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 191,
				'filter' => [
					'ufCrm3_1717070215888' => $request['doctorId']
				]
			]
		);
		$fields = [
			'ufCrm3_1717070215888' => $request['doctorId'],
			'ufCrm3_1704440965' => $request['fullName'],
		];
		if($instanceList['total'] == 0) {
			$res = $this->call(
				'crm.item.add',
				[
					"entityTypeId" => 191,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$res = $this->call(
				'crm.item.update',
				[
					"entityTypeId" => 191,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }
    public function updateSpeciality(Request $request) 
    {
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 159,
				'filter' => [
					'ufCrm9_1717702796460' => $request['specialityId']
				]
			]
		);
		$fields = [
			'ufCrm9_1717702796460' => $request['specialityId'],
			'title' => $request['specialty'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.item.add',
				[
					"entityTypeId" => 159,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.item.update',
				[
					"entityTypeId" => 159,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }
    public function updateCabinet(Request $request) 
    {
		log::info($request);
		if ($request['token'] !== '$2y$10$5HbZBDfXnRwE6L6TdJqnnuAGCZfnvf7xFCbgBeyV0mpnCMPYHM58O') {
			return;
		}
		$instanceFields = $this->call(
			'crm.item.fields',
			["entityTypeId" => 172]
		);
		log::info($instanceFields);
        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 172,
				'filter' => [
					'ufCrm10_1717702537668' => $request['cabinetID']
				]
			]
		);
		$fields = [
			'ufCrm10_1717702537668' => $request['cabinetID'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.item.add',
				[
					"entityTypeId" => 172,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.item.update',
				[
					"entityTypeId" => 172,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }

	// protected function callKaskadForm()
	// {
	// 	$params = [
	// 		'FIELDS' => [
	// 			'IS_NEW' => 'Y',
	// 			'TITLE' => 'Форма обратной связи ',
	// 			'NAME' => $name,
	// 			'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
	// 			'EMAIL' => [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']],
	// 			'COMMENTS' => $comment,
	// 			'FIELDS[SOURCE_ID]' => 'WEB',
	// 			'SOURCE_DESCRIPTION' => $source_link,
	// 		]
	// 	];
	// 	$client = new Client();

	// 	$options = [
	// 	    'allow_redirects' => [
	// 	        'max' => 10
	// 	    ],
	// 	    'headers' => [
	// 	        'User-Agent' => 'Bitrix24 CRest PHP 1.0'
	// 	    ],
	// 	    'curl'=> [
	// 	    	CURLOPT_POSTREDIR => 3
	// 	    ]
	// 	];


	// 	if (!empty($params)) {
	// 	    $options['form_params'] = $params;
	// 	}
	// 	try {
	// 		$response = $client->request('POST', 'https://bitrix.494.by/rest/1138/jup396oui2fn2zcf/crm.lead.add.json', $options);
			
	// 		$result = json_decode($response->getBody(), true);
	// 		log::info($result);
	// 	} catch (ClientException $e) {
	// 		log::info($e->getMessage());
	// 	}
	// }
}
