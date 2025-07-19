<?php

namespace App\Http\Controllers\Api;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\ClientException;
use App\Services\PriceService;
use Illuminate\Support\Facades\Log;

class KaskadController extends Controller
{

    protected $token = [
        "expires"=> 1717596552,
        // "baseDomain"=> "10.3.4.2",
        "baseDomain"=> "bitrix.494.by",
        "accessToken"=> "88716066006e74b40068c0eb000004720000073d421badce02def73dd2ec3bcf7516b7",
        "refreshToken"=> "78f08766006e74b40068c0eb00000472000007ced39b10ec61326dfb0595b03072ecee",
    ];
	protected $settings = [
        "crmType"=> "lead",
        "clientId"=> "local.6660467aa43a10.98257669",
        "clientSecret"=> "q4ir2xu0dxZ622CkJ8hkd8Poa1JoWjVdoICnQe0974GGP933UF",
    ];

    protected $priceService;

    public function __construct(PriceService $priceService)
    {
        $this->priceService = $priceService;
    }

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

	    $handlerUrl = config('app.url') . '/api/bitrix/price-update';
        $this->priceService->registerPriceUpdateHandler($handlerUrl);


	}

    public function updateContactRequest(Request $request) 
    {
		try {
			$res = $this->updateContact($request);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
		
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
		$headers = [
			'Authorization' => 'Bearer a9322698-4171-4409-a429-0b24012ad25e',
			'Accept' => 'application/json',
		];
		$response = Http::withHeaders($headers)->get(config('app.url') . '/api/update/visit');
	}

    public function updateVisit(Request $request)
    {
        try {
            if (!isset($request['visitId'])) {
                throw new \Exception("visitId is required");
            }
            
            // Create a copy of the request data to use for background processing
            $requestData = $request->all();
            
            // Store $this for use in the closure
            $self = $this;
            
            // Return response immediately
            $response = response()->json(['result' => 'Processing started', 'visitId' => $request['visitId']], 200);
            
            // Process in background after response is sent
            register_shutdown_function(function() use ($requestData, $self) {
                try {
                    $start = microtime(true);
                    $request = new Request($requestData);
                    
                    $contactId = $self->updateContact($request);
                    $end = microtime(true) - $start;
                    log::info('update contact time: ' . $end);
                    
                    $start = microtime(true);
                    $doctorId = $self->updateDoctor($request);
                    $end = microtime(true) - $start;
                    log::info('update doctor time: ' . $end);
                    
                    $start = microtime(true);
                    $specialityId = $self->updateSpeciality($request);
                    $end = microtime(true) - $start;
                    log::info('update speciality time: ' . $end);
                    
                    $start = microtime(true);
                    $cabinetId = $self->updateCabinet($request);
                    $end = microtime(true) - $start;
                    log::info('update cabinet time: ' . $end);
                    
                    $instanceList = $self->call(
                        'crm.item.list',
                        [
                            "entityTypeId" => 133,
                            'filter' => [
                                'ufCrm6_1717069419772' => $request['visitId']
                            ]
                        ]
                    );
                    $end = microtime(true);
                    log::info('get list time: ' . ($end - $start));
                    $start = microtime(true);
                    
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
                        'begindate' => $request['visitCreateDateTime'],
                        'closedate' => $request['visitCancelDateTime'],
                        'ufCrm6_1717756905956' => $request['branchId'],
                        'ufCrm6_1717756914479' => $request['branch'],
                        'ufCrm6_1740738765251' => $request['userNameCreaterVisit'],
                        'ufCrm6_1740738932368' => $request['userTypeCreateVisit'],
                    ];
                    
                    if(isset($request['checks'][0])) {
                        $fields['ufCrm6_1717075348543'] = $policies[$request['checks'][0]['paymentType']];
                        $fields['ufCrm6_1717756581518'] = $request['checks'][0]['policyId'];
                        $fields['ufCrm6_1717757774625'] = $request['checks'][0]['policyNumber'];
                        $fields['ufCrm6_1717756615624'] = $request['checks'][0]['policyStartDate'];
                        $fields['ufCrm6_1717756629307'] = $request['checks'][0]['policyEndDate'];
                        $fields['ufCrm6_1717756761823'] = $request['checks'][0]['companyId'];
                        $fields['ufCrm6_1717756772642'] = $request['checks'][0]['company'];
                        $fields['ufCrm6_1717756837171'] = $request['checks'][0]['contractId'];
                        $fields['ufCrm6_1717756869464'] = $request['checks'][0]['contractNumber'];
                        $fields['ufCrm6_1717756884255'] = $request['checks'][0]['contractStartDate'];
                        $fields['ufCrm6_1717756900527'] = $request['checks'][0]['contractEndDate'];
                        $fields['ufCrm6_1730923494796'] = $request['checks'][0]['summa'];
                        $fields['ufCrm6_1730923506131'] = $request['checks'][0]['summaWithDiscont'];
                        $fields['ufCrm6_1717757159930'] = $request['checks'][0]['userIdPaidVisit'];
                    }
                    
                    if(isset($request['checks']) && count($request['checks']) > 1) {
                        $fields['ufCrm6_1730922040802'] = $policies[$request['checks'][1]['paymentType']];
                        $fields['ufCrm6_1730922063553'] = $request['checks'][1]['policyId'];
                        $fields['ufCrm6_1730922076813'] = $request['checks'][1]['policyNumber'];
                        $fields['ufCrm6_1730922101664'] = $request['checks'][1]['policyStartDate'];
                        $fields['ufCrm6_1730922111930'] = $request['checks'][1]['policyEndDate'];
                        $fields['ufCrm6_1730922125590'] = $request['checks'][1]['companyId'];
                        $fields['ufCrm6_1730922133793'] = $request['checks'][1]['company'];
                        $fields['ufCrm6_1730922160390'] = $request['checks'][1]['contractId'];
                        $fields['ufCrm6_1730922175357'] = $request['checks'][1]['contractNumber'];
                        $fields['ufCrm6_1730922199444'] = $request['checks'][1]['contractStartDate'];
                        $fields['ufCrm6_1730922210555'] = $request['checks'][1]['contractEndDate'];
                        $fields['ufCrm6_1730922292531'] = $request['checks'][1]['summa'];
                        $fields['ufCrm6_1718544988309'] = $request['checks'][1]['summaWithDiscont'];
                        $fields['ufCrm6_1730922233670'] = $request['checks'][1]['userIdPaidVisit'];
                    }
                    
                    if(isset($request['services']) && count($request['services']) > 0) {
                        // Initialize arrays for each field using lowercase prefix to match other fields
                        $fields['ufCrm6_1718544860140'] = [];
                        $fields['ufCrm6_1718544942772'] = [];
                        $fields['ufCrm6_1740684976081'] = [];
                        $fields['ufCrm6_1718544969756'] = [];
                        $fields['ufCrm6_1740685101111'] = [];
                        $fields['ufCrm6_1740685117182'] = [];

                        foreach ($request['services'] as $key => $service) {
                            $fields['ufCrm6_1718544860140'][$key] = $service['code'] ?? null;
                            $fields['ufCrm6_1718544942772'][$key] = $service['name'] ?? null;
                            $fields['ufCrm6_1740684976081'][$key] = $service['price'] ?? null;
                            $fields['ufCrm6_1718544969756'][$key] = $service['count'] ?? null;
                            $fields['ufCrm6_1740685101111'][$key] = $service['summa'] ?? null;
                            $fields['ufCrm6_1740685117182'][$key] = $service['summaWithDiscount'] ?? null;
                        }
                    }
                    
                    if($instanceList['total'] == 0) {
                        $res = $self->call(
                            'crm.item.add',
                            [
                                "entityTypeId" => 133,
                                'fields' => $fields
                            ]
                        );
                        $id = $res['result'];
                    }
                    
                    if($instanceList['total'] != 0) {
                        $self->call(
                            'crm.item.update',
                            [
                                "entityTypeId" => 133,
                                'id'=>$instanceList['result']['items'][0]['id'],
                                'fields' => $fields
                            ]
                        );
                        $id = $instanceList['result']['items'][0]['id'];
                    }
                    
                    $end = microtime(true);
                    log::info('update visit time: ' . ($end - $start));
            
                    // create services connection
                    if (isset($request['services'])) {
                        $start = microtime(true);
                        $self->updateService($request['services'], $id);
                        $end = microtime(true);
                        log::info('update service time: ' . ($end - $start));
                    }
                    
                    log::info('Background processing completed for visitId: ' . $request['visitId']);
                } catch (\Throwable $th) {
                    log::error('Background processing error: ' . $th->getMessage());
                }
            });
            
            return $response;
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }


    public function updateSuggestedVisitRequest(Request $request) 
    {
		try {
			$res = $this->updateSuggestedVisit($request);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
    }
    public function updateSuggestedVisit($request) 
    {
		if (!isset($request['visitId'])) {
			throw new \Exception("visitId is required");
		}
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
			$res = $this->call(
				'crm.item.add',
				[
					"entityTypeId" => 152,
					'fields' => $fields
				]
			);
			$id = $res['result'];
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
			$id = $instanceList['result']['items'][0]['id'];
		}

		return $id;
    }
    public function updateDoctorRequest(Request $request) 
    {
		try {
			$res = $this->updateDoctor($request);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
    }
    public function updateDoctor($request) 
    {
		if (!isset($request['doctorId'])) {
			throw new \Exception("doctorId is required");
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
		// $instanceFields = $this->call(
		// 	'crm.item.fields',
		// 	["entityTypeId" => 191]
		// );
		$fields = [
			'ufCrm3_1717070215888' => $request['doctorId'],
			'title' => $request['fullName'],
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
		try {
			$res = $this->updateSpeciality($request);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
    }
    public function updateSpeciality($request) 
    {
		if (!isset($request['specialityId'])) {
			throw new \Exception("specialityId is required");
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
		try {
			$res = $this->updateCabinet($request);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
    }
    public function updateCabinet(Request $request) 
    {
		if (!isset($request['cabinetID'])) {
			throw new \Exception("cabinetID is required");
		}
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
		try {
			$res = $this->updateService($request, $request['visitId']);
			return response()->json(['result' => $res], 200);
		} catch (\Throwable $th) {
			return response()->json(['message' => $th->getMessage()], 500);
		}
    }

	public function updateService($services, $id)
	{
		foreach ($services as $key => $service) {
			$productFields  = $this->call(
				'crm.item.productrow.fields',
			);

			$productList  = $this->call(
				'crm.product.list',
				[
					'filter' => [
						'PROPERTY_68' => $service['id'] ?? null,
					]
				]
			);
			
			$productFields = [
				'NAME' => $service['name'] ?? null,
				'PROPERTY_68' => $service['id'] ?? null,
				'PROPERTY_71' => $service['count'] ?? null,
				'PROPERTY_72' => $service['price'] ?? null,
				'PROPERTY_73' => $service['summa'] ?? null,
				'PROPERTY_74' => $service['summaWithDiscount'] ?? null,
				'PROPERTY_75' => $service['code'] ?? null,
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
				'productName' => $service['name'] ?? null,
				'price' => $service['price'] ?? null,
				'quantity' => $service['count'] ?? null,
			];
		}
		$this->call(
			'crm.item.productrow.set',
			[
				'ownerType' => 'T85',
				'ownerId' => $id,
				'productRows' => $productRows,
			]
		);
		return $productId;
	}

	public function storePreiskurants(Request $request)
	{
		$file = $request->file('file');
	}

	public function addHandler(){

	}
}
