<?php

namespace App\Http\Controllers\Api;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
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

	public function setupBitrix(Request $request)
	{
		log::info('setup bitrix');
		$userfieldtype = $this->call(
			'userfieldtype.list'
		);

		foreach ($userfieldtype['result'] as $key => $value) {
			$this->call(
				'userfieldtype.delete',
				[
					'USER_TYPE_ID' => $value['USER_TYPE_ID'],
				]
			);
		}
		$userfieldtypelead = $this->call(
			'crm.lead.userfield.list',
		);
		foreach ($userfieldtypelead['result'] as $key => $value) {
			$this->call(
				'crm.lead.userfield.delete',
				[
					'ID' => $value['ID'],
				]
			);
		}
		$this->call(
			'userfieldtype.add',
			[
				'USER_TYPE_ID' => 'aibolit_button',
				'HANDLER' => 'https://cnadmindemo.dynamicov.com/bitrix/kaskad/button',
				'TITLE' => 'Aibolit',
				'OPTIONS'=> [
					'height'=> 70,
				],
			]
		);
		$this->call(
			'crm.lead.userfield.add',
			[
				'fields' => [
					"FIELD_NAME" => "Aibolit",
					"USER_TYPE_ID" => "aibolit_button",
				]
			]
		);
	}

    public function updateContactRequest(Request $request) 
    {
		$this->updateContact($request);
    }

	public function updateContact($contact)
	{
		$instanceFields = $this->call(
			'crm.contact.fields',
		);

		$instanceList  = $this->call(
			'crm.contact.list',
			[
				'filter' => [
					'UF_CRM_1717673519088' => $contact['clientId']
				]
			]
		);
		$fields = [
			"NAME"=> $contact['firstName'],
			"LAST_NAME"=> $contact['lastName'],
			"SECOND_NAME"=> $contact['middleName'],
			"BIRTHDATE"=> $contact['birthDate'],
			"PHONE"=> [[ "VALUE"=> $contact['phoneNumber'], "VALUE_TYPE"=> "WORK" ]],	
			"EMAIL"=> [[ "VALUE"=> $contact['e-mail'], "VALUE_TYPE"=> "WORK" ]],	
			"ADDRESS"=> $contact['adress'],	
			'UF_CRM_1717673519088' => $contact['clientId'],
			'UF_CRM_1717074924654' => $contact['citizenship'],
			'UF_CRM_1717075072232' => $contact['passportID'],
			'UF_CRM_1717075164335' => $contact['createDate'],
			'UF_CRM_1717075194966' => $contact['consentPersonalData'],
			'UF_CRM_1717674789996' => $instanceFields['result']['UF_CRM_1717674789996']['items'][$contact['consentPersonalData']]['ID'],
			'UF_CRM_1717075227060' => $contact['consentReklama'],
			'UF_CRM_1717075237278' => $contact['consentSMSVisit'],
			'UF_CRM_1717075246162' => $contact['consentRecommendation'],
			'UF_CRM_1717075258294' => $contact['consentAnalysisE-mail'],
			'UF_CRM_1717075267328' => $contact['consentSMSQuality'],
		];
		if($instanceList['total'] == 0) {
			$res = $this->call(
				'crm.contact.add',
				[
					'FIELDS' => $fields
				]
			);
			$id = $res['result'];
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.contact.update',
				[
					'id'=>$instanceList['result'][0]['ID'],
					'FIELDS' => $fields
				]
			);
			$id = $instanceList['result'][0]['ID'];
		}

		return $id;
	}

	public function example(Request $request) 
	{
		log::info('example');
		log::info($request);
		$headers = [
			'Authorization' => 'Bearer a9322698-4171-4409-a429-0b24012ad25e',
			'Accept' => 'application/json',
		];
		$response = Http::withHeaders($headers)->get('http://127.0.0.1:8001/api/update/visit');
		log::info($response);
	}

    public function updateVisit(Request $request)
    {
		log::info('updateVisit');
		log::info($request);
		// $typeList  = $this->call(
		// 	'crm.type.list',
		// );
		// log::info($typeList);

		// create contact 
		$contactId = $this->updateContact($request);
		// create doctor 
		$doctorId = $this->updateDoctor($request);
		// create suggestedVisits 
		// $sugVisitId = $this->updateSuggestedVisit($request);
		// create suggestedVisits 
		$specialityId = $this->updateSpeciality($request);
		// create suggestedVisits 
		$cabinetId = $this->updateCabinet($request);



		// $instanceFields = $this->call(
		// 	'crm.item.fields',
		// 	["entityTypeId" => 133]
		// );
		// log::info($instanceFields);
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
			'contactId' => $contactId,
			'PARENT_ID_191' => $doctorId,
			// 'PARENT_ID_152' => $sugVisitId,
			'PARENT_ID_159' => $specialityId,
			'PARENT_ID_172' => $cabinetId,
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
			$res = $this->call(
				'crm.item.add',
				[
					"entityTypeId" => 133,
					'fields' => $fields
				]
			);
			$id = $res['result'];
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
			$id = $instanceList['result']['items'][0]['id'];
		}

		// create services connection
		if (isset($request['services'])) {
			$this->updateService($request['services'], $id);
		}
    }


    public function updateSuggestedVisitRequest(Request $request) 
    {

        $this->updateSuggestedVisit($request);
    }
    public function updateSuggestedVisit($request) 
    {

        $instanceList  = $this->call(
			'crm.item.list',
			[
				"entityTypeId" => 152,
				'filter' => [
					'ufCrm6_1717069419772' => $request['visitId']
				]
			]
		);
		$fields = [
			'begindate' => $request['visitCreateDateTime'],
			'closedate' => $request['visitCancelDateTime'],
			'ufCrm6_1717075348543' => $request['paymentType'],
		];
		if($instanceList['total'] == 0) {
			$this->call(
				'crm.item.add',
				[
					"entityTypeId" => 152,
					'fields' => $fields
				]
			);
		}
		if($instanceList['total'] != 0) {
			$this->call(
				'crm.item.update',
				[
					"entityTypeId" => 152,
					'id'=>$instanceList['result']['items'][0]['id'],
					'fields' => $fields
				]
			);
		}
    }
    public function updateDoctorRequest(Request $request) 
    {
        $this->updateDoctor($request);
    }
    public function updateDoctor($request) 
    {
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
			$id = $res['result'];
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
			$id = $instanceList['result']['items'][0]['id'];
		}
		return $id;
    }
    public function updateSpecialityRequest(Request $request) 
    {
		$this->updateSpeciality($request);
    }
    public function updateSpeciality($request) 
    {
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
			$res = $this->call(
				'crm.item.add',
				[
					"entityTypeId" => 159,
					'fields' => $fields
				]
			);
			$id = $res['result'];
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
			$id = $instanceList['result']['items'][0]['id'];
		}
		return $id;
    }
    public function updateCabinetRequest(Request $request) 
    {
		$this->updateCabinet($request);
    }
    public function updateCabinet(Request $request) 
    {
		$instanceFields = $this->call(
			'crm.item.fields',
			["entityTypeId" => 172]
		);
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
			$res = $this->call(
				'crm.item.add',
				[
					"entityTypeId" => 172,
					'fields' => $fields
				]
			);
			$id = $res['result'];
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
			$id = $instanceList['result']['items'][0]['id'];
		}
		return $id;
    }

	public function updateServiceRequest(Request $request) 
    {
        $this->updateService($request, $request['visitId']);
    }

	public function updateService($services, $id)
	{
			foreach ($services as $key => $service) {
				// ?create products in crm form
				$fields['ufCrm6_1718544720772'][$key] = $service['id'];
				$fields['ufCrm6_1718544860140'][$key] = $service['code'];
				$fields['ufCrm6_1718544942772'][$key] = $service['name'];
				$fields['ufCrm6_1718544969756'][$key] = $service['count'];
				$fields['ufCrm6_1718544979441'][$key] = $service['price'];
				$fields['ufCrm6_1718544988309'][$key] = $service['summa'];
				$fields['ufCrm6_1718544998792'][$key] = $service['summaWithDiscount'];
	
				// ?create product
				$productFields  = $this->call(
					'crm.item.productrow.fields',
				);

				$productList  = $this->call(
					'crm.product.list',
					[
						'filter' => [
							'PROPERTY_68' => $service['id'],
						]
					]
				);
				
				$productFields = [
					'NAME' => $service['name'],
					'PROPERTY_68' => $service['id'],
					'PROPERTY_71' => $service['count'],
					'PROPERTY_72' => $service['price'],
					'PROPERTY_73' => $service['summa'],
					'PROPERTY_74' => $service['summaWithDiscount'],
					'PROPERTY_75' => $service['code'],
				];
				if($productList['total'] == 0) {
					$res = $this->call(
						'crm.product.add',
						[
							'fields' => $productFields
						]
					);
					$productId = $productList['result'];
				}
				
				if($productList['total'] != 0) {
					$res = $this->call(
						'crm.product.update',
						[
							'id'=>$productList['result'][0]['ID'],
							'fields' => $productFields
						]
					);
					$productId = $productList['result'][0]['ID'];
				}
				$productRows[] = [
					'productId' => $productId,
					'productName' => $service['name'],
					'price' => $service['price'],
					'quantity' => $service['count'],
				];

				// !create service 
				// $serviceFields  = $this->call(
				// 	'catalog.product.service.getFieldsByFilter',
				// 	[
				// 		'filter'=> ['iblockId' => 14],
				// 	]
				// );
				// $serviceList  = $this->call(
				// 	'catalog.product.service.list',
				// 	[
				// 		'select' => ['id', 'iblockId'],
				// 		'filter' => [
				// 			'iblockId' => 14,
				// 			'property68' => $service['id'],
				// 		]
				// 	]
				// );
				// $serviceFields = [
				// 	'iblockId' => 14,
				// 	'name' => $service['name'],
				// 	'property68' => $service['id'],
				// 	'property69' => $service['code'],
				// 	'property71' => $service['count'],
				// 	'property72' => $service['price'],
				// 	'property73' => $service['summa'],
				// 	'property74' => $service['summaWithDiscount'],
				// ];
				// if($serviceList['total'] == 0) {
				// 	$this->call(
				// 		'catalog.product.service.add',
				// 		[
				// 			'fields' => $serviceFields
				// 		]
				// 	);
				// }
				// if($serviceList['total'] != 0) {
				// 	$this->call(
				// 		'catalog.product.service.update',
				// 		[
				// 			'id'=>$serviceList['result']['services'][0]['id'],
				// 			'fields' => $serviceFields
				// 		]
				// 	);
				// }
			}
			$this->call(
				'crm.item.productrow.set',
				[
					'ownerType' => 'T85',
					'ownerId' => $id,
					'productRows' => $productRows,
				]
			);
	}

}
