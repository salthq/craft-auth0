<?php

namespace salt\craftauth0\services;

use Craft;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use salt\craftauth0\helpers\ApiRequest;

class Auth0ApiService
{
    public function __construct()
    {
        $this->token = ApiRequest::getAccessToken();
    }

    /**
     * Fetch the user details from the Auth0 api
     * 
     * Docs - https://auth0.com/docs/api/management/v2#!/Users/get_users_by_id
     * 
     * @param String $user_id the Auth0 user id
     *
     * @return Object $user an object containing all the user deta for the Auth0 user
     */
    public function fetchAuth0UserById(String $user_id): Object
    {

        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');

        $method = "GET";
        $url = $auth0Config["api_audience"] . "users/" . $user_id;

        $response = $this->makeApiRequest($method, $url);
        $user = json_decode($response);

        return $user;
    }

    /**
     * 
     * Fetch the user details from Auth0 associated with a given email address. 
     * NB! Use with caution: in most cases this returns an item with one array, but it might return multiple entries
     * 
     * Docs - https://auth0.com/docs/api/management/v2#!/Users_By_Email/get_users_by_email
     * 
     * @param String $email The email to search for
     * @return Array An array of user details which are associated with the email address
     */
    public function fetchAuth0UserByEmail(String $email): array
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');

        $method = "GET";
        $url = $auth0Config["api_audience"] . "users-by-email?email=" . $email;

        $response = $this->makeApiRequest($method, $url);

        $user_data = json_decode($response);
        return $user_data;
    }

    /**
     * 
     * Find a user from Auth0 associated with a given email address & current connection. 
     * 
     * Docs - https://auth0.com/docs/api/management/v2#!/Users_By_Email/get_users_by_email
     * 
     * @param String $email The email to search for
     * @return Array An array of user details which are associated with the email address
     */
    public function searchAuth0UserByEmail(String $email): array
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');

        // Check email to make sure we are adding to the right connection
        if (strpos($email, '@saltcampus.co') !== false) {
            $connection = 'ZZ-DB-Campus-Admin';
        } else {
            $connection = $auth0Config['db_connection'];
        }

        $method = "GET";
        $url = $auth0Config['api_audience'] . 'users?q=(email:"' . $email . '" AND identities.connection:"' . $connection . '")';

        $response = $this->makeApiRequest($method, $url);

        $user_data = json_decode($response);
        return $user_data;
    }

    /**
     * Creates a new user on auth0 using the given name and email address
     * 
     * Docs - https://auth0.com/docs/api/management/v2#!/Users/post_users
     *
     * @param String $email Email address of the user to be created 
     * @param String $name Full name of the user to be created
     * @param String|null $password the initial password to set for the user (optional)
     * @return Object $auth0_user The data for the newly created Auth0 user
     */
    public function createAuth0User(String $email, String $name, ?String $password = null): Object
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');

        $method = "POST";
        $url = $auth0Config['api_audience'] . "users";

        // If a password was not passed to this method, generate a dummy one
        if (!$password) {
            /**
             * It doesn't really matter what this password is, as it won't be used to login. 
             * It should however be hashed so that someone isn't able to login as the user 
             * before they have had a chance to change the password. 
             */
            $password = password_hash("TestPassword123", PASSWORD_DEFAULT);
        }

        // Check email to make sure we are adding to the right connection
        if (strpos($email, '@saltcampus.co') !== false) {
            $connection = 'ZZ-DB-Campus-Admin';
        } else {
            $connection = $auth0Config['db_connection'];
        }

        $body = [
            'email' => $email,
            'name' => $name,
            'connection' => $connection,
            'password' => $password,
            "email_verified" => true,

        ];

        $response = $this->makeApiRequest($method, $url, $body);
        $auth0_user = json_decode($response);

        return $auth0_user;
    }

    /**
     * Updates the user's email address and name on Auth0
     * 
     * Docs - https://auth0.com/docs/api/management/v2#!/Users/patch_users_by_id
     *
     * @param String $user_id the Auth0 ID
     * @param String $email The email address to be updated
     * @param String $name The name to be updated
     * 
     * @return Object The updated Auth0 user data
     */
    public function updateAuth0User(String $user_id, String $email, String $name): Object
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $method = "PATCH";
        $url = $auth0Config['api_audience'] . "users/" . $user_id;
        $body = [
            'email' => $email,
            'name' => $name,
            "email_verified" => true,
        ];

        $response = $this->makeApiRequest($method, $url, $body);

        $auth0_user = json_decode($response);
        return $auth0_user;
    }


    /**
     * Generates a password reset link for the given Auth0 user
     *
     * Docs - https://auth0.com/docs/api/management/v2#!/Tickets/post_password_change
     * 
     * @param String $user_id the Auth0 ID for the user
     * @return String $response
     */
    public function generatePasswordResetLink(String $user_id): String
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $method = "POST";
        $url = $auth0Config['api_audience'] . "tickets/password-change";

        $body = [
            'user_id' => $user_id,
            'result_url' => config('app.url'),
            'ttl_sec' => 2592000
        ];

        $response = $this->makeApiRequest($method, $url, $body);
        return $response;
    }

    /**
     * Given a user's email address and a connection, Auth0 will send a change password email.
     *
     * Docs - /dbconnections/change_password
     * 
     * @param String $user_id the Auth0 ID for the user
     * @return String $response
     */
    public function sendPasswordResetEmail(String $email): String
    {
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $method = "POST";
        $url = "https://" . $auth0Config['api_domain'] . "/dbconnections/change_password";

        $body = [
            'client_id' => $auth0Config['api_client_id'],
            'email' => $email,
            'connection' => $auth0Config['db_connection']
        ];

        $response = $this->makeApiRequest($method, $url, $body);
        return $response;
    }



    /**
     * Makes an API request to the given URL with the given method
     *
     * @param String $method
     * @param String $url
     * @param array $body
     * @return string
     */
    private function makeApiRequest(String $method, String $url, array $body = null): string
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer " . $this->token
        ];
        $client = new Client(['headers' => $headers]);
   
       
            $response = $client->request($method, $url, ['body' => json_encode($body)]);
     

        return $response->getBody();
    }


    /**
     * Returns a message depending on the status code returned
     *
     * @param \Illuminate\Http\Client\Response $response
     * @return string
     */
    private function getErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        return         [
            400 => __('notifications.auth0.400'),
            401 => __('notifications.auth0.401'),
            403 => __('notifications.auth0.403'),
            405 => __('notifications.auth0.405'),
            429 => __('notifications.auth0.429'),
            500 => __('notifications.auth0.500'),
            501 => __('notifications.auth0.501'),
            503 => __('notifications.auth0.503'),
        ][$response->status()];
    }
}
