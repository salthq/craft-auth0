<?php
/**
 * User: cwbmuller
 */

namespace salt\craftauth0\controllers;

use Craft;
use Auth0\SDK\Auth0;
use craft\web\Controller;
use craft\elements\User;
use yii\web\Response;

class LoginController extends Controller
{

    protected $allowAnonymous = ['auth', 'callback'];

     /**
     * URL to redirect to after login.
     *
     * @var string
     */
    private $redirectUrl;

    /**
     * URL where the login was initiated from.
     *
     * @var string
     */
    private $originUrl;
   
    public function actionAuth() {

        $this->originUrl = Craft::$app->getRequest()->referrer;

        $this->redirectUrl = Craft::$app->getRequest()->getParam('redirect');
        
        if ($user = Craft::$app->getUser()->getIdentity()) {
            $this->redirect($this->redirectUrl);
        }
        $authorize_params = [
            'scope' => 'openid profile email offline_access',
            'appName' => 'SALTY',
        ];
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $auth0 = new Auth0($auth0Config);
        return $auth0->login(null, null, $authorize_params,'code');
   }

   public function actionCallback() : Response {
        // Get a handle of the Auth0 service (we don't know if it has an alias)
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $auth0 = new Auth0($auth0Config);
      
        // Try to get the user information
        $profile = $auth0->getUser();
        try {
            return $this->registerOrLoginFromProfile($profile);

        } catch(\Exception $e) {
        
            Craft::error('Couldn’t login. '. $e->getTraceAsString(), __METHOD__);
            return $this->redirect($this->originUrl);
        }

   }

   private function registerOrLoginFromProfile(array $profile)
    {
        // Existing user
        $user = Craft::$app->users->getUserByUsernameOrEmail($profile['email']);

                if ($user) {
                    $craftUser = Craft::$app->users->getUserById($account->userId);

                    if (!$craftUser) {
                        throw new LoginException('Social account exists but Craft user doesn’t.');
                    }

                    // Save existing login account
                    Craft::$app->elements->saveElement($account);

                    // Login
                    return $this->login($craftUser, $account, $token);
                }

                // Register new user
                $craftUser = $this->upsertUser($profile);

                if (!$craftUser) {
                    throw new RegistrationException('Craft user couldn’t be created.');
                }


                // Login
                return $this->login($craftUser, $account, $token, true);
            }


     /**
     * Get an existing user or create a new one
     *
     * @param array $profile - Auth0 profile
     *
     * @return User
     */
    protected function upsertUser(array $profile): User
    {

        // Registration of an existing user with a matching email
        $user = Craft::$app->users->getUserByUsernameOrEmail($profile['email']);

        if ($user) {

            // Check if sub has been set, otherwise set it
            if (!$user->sub) {
                $user->sub = $profile['sub'];
                // Save user
                if (!Craft::$app->elements->saveElement($user)) {
                    Craft::error('There was a problem creating the user:' . print_r($user->getErrors(), true), __METHOD__);
                    throw new RegistrationException('Craft user couldn’t be created.');
                }
            }
            return $user;
        }

        $newUser = new User();

        $newUser->email = $profile['email'];
        $newUser->sub = $profile['sub'];
        $newUser->name = $profile['name'];

        // Generate username 
        $pattern = " ";
        $firstPart = strstr(strtolower($profile['name']), $pattern, true);
        $firstPart =  $firstPart === '' ? strstr(strtolower($profile['email']), "@", true) : $firstPart;
        $secondPart = substr(strstr(strtolower($profile['name']), $pattern, false), 0,4);
        $nrRand = rand(0, 100);
        $name = trim($firstPart).trim($secondPart);
        $username = $name.trim($nrRand);

        $newUser->username = $username;

        // Save user
        if (!Craft::$app->elements->saveElement($newUser)) {
            Craft::error('There was a problem creating the user:' . print_r($newUser->getErrors(), true), __METHOD__);
            throw new RegistrationException('Craft user couldn’t be created.');
        }

        return $newUser;
    }

    private function login(User $craftUser): Response
    {

        if (!Craft::$app->getUser()->login($craftUser)) {
            return $this->_handleLoginFailure();
        }

        
        $this->setNotice('Logged in.');
        

        return $this->redirect($this->redirectUrl);
    }

    /**
     * Handles a failed login attempt.
     *
     * @return Response
     * @throws \craft\errors\MissingComponentException
     */
    private function _handleLoginFailure(): Response
    {
        $this->setError(Craft::t('social', 'Couldn’t authenticate.'));

        return $this->redirect($this->originUrl);
    }
}