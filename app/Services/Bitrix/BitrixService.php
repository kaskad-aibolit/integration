<?php

namespace App\Services\Bitrix;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class BitrixService
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

    public function call($method, $params = [])
    {
        $url = 'https://' . $this->token['baseDomain'] . '/rest/' . $method . '.json';
        $this->checkToken();
        $params['auth'] = $this->token['accessToken'];
        return $this->callCurl($url, $params);
    }

    public function checkToken()
    {
        if ($this->token['expires'] > time() + 100) {
            return true;
        }

        $newToken = $this->callCurl('https://oauth.bitrix.info/oauth/token/', [
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
            if (!empty($e['error'])) {
                if ($e['error'] == 'expired_token') {
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
                if (!empty($arErrorInform[$e['error']])) {
                    $result['error_information'] = $arErrorInform[$e['error']];
                }
            }
            Log::error('Catch Bitrix 24, ' . json_encode($result));
            return $result;
        }

        return $result;
    }
} 