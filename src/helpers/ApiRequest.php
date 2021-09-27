<?php

namespace salt\craftauth0\helpers;

use Exception;
use Craft;
use GuzzleHttp\Client as Guzzle;

class ApiRequest
{
    /**
     * Create a new ApiRequest instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    public static function makeApiRequest($method, $url, $body = null)
    {
        $access_token = ApiRequest::getAccessToken();
        if ($access_token) {
            $data = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer " . $access_token
                ],
                'json' => $body
            ];

            $client = new Guzzle();
            try {
                $response = $client->request(
                    $method,
                    $url,
                    $data
                );

                return $response;
            } catch (Exception $e) {
                logger($e->getMessage());
                return response()->json(['error' => $e->getMessage()], 500);
            }
        }
    }

    public static function getAccessToken()
    {
        // Get config file vars
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://" . $auth0Config['api_domain'] . "/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials&client_id=" . $auth0Config['api_client_id'] . "&client_secret=" . $auth0Config['api_client_secret'] . "&audience=" . $auth0Config['api_audience'],
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            logger($err);
        }

        curl_close($curl);

        $response = json_decode($response);

        return isset($response->access_token) ? $response->access_token : false;
    }
}
