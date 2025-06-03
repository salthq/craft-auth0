<?php
/**
 * User: cwbmuller
 */

namespace salt\craftauth0\controllers;

use Craft;
use Auth0\SDK\Auth0;
use craft\web\Controller;
use craft\elements\User;
use craft\db\Command;
use yii\web\Response;
use craft\helpers\Db;
use craft\helpers\UrlHelper;

class LoginController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = ['auth', 'callback'];

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
        // Basic debugging to see if controller is called
        error_log('DEBUG: LoginController::actionAuth() called - controller is working!');
        
        // Add debugging to help diagnose the issue
        Craft::info('Auth0 LoginController::actionAuth() called from: ' . Craft::$app->getRequest()->getUrl(), __METHOD__);

        $this->originUrl = Craft::$app->getRequest()->referrer;
        
        $this->redirectUrl = Craft::$app->getRequest()->getParam('redirect') ?? '/admin';
   
        if ($user = Craft::$app->getUser()->getIdentity()) {
            error_log('DEBUG: User already logged in, redirecting to: ' . $this->redirectUrl);
            Craft::info('User already logged in, redirecting to: ' . $this->redirectUrl, __METHOD__);
            return $this->redirect($this->redirectUrl);
        }
        
        error_log('DEBUG: User not logged in, starting Auth0 flow');
        Craft::info('User not logged in, starting Auth0 flow', __METHOD__);
        
        $authorize_params = [
            'scope' => 'openid profile email offline_access'
        ];
        $config_params = Craft::$app->config->general->__isset('auth0LoginParams') ? Craft::$app->config->general->auth0LoginParams : false;
        if (is_array($config_params)) {
            $authorize_params = array_merge($authorize_params,$config_params);
        };
        
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        if (!$auth0Config) {
            error_log('DEBUG: Auth0 config not found!');
            Craft::error('Auth0 configuration not found. Please check your craft-auth0.php config file.', __METHOD__);
            throw new \Exception('Auth0 configuration not found. Please check your craft-auth0.php config file.');
        }
        
        error_log('DEBUG: Auth0 config found, creating Auth0 instance');
        Craft::info('Creating Auth0 instance and starting login', __METHOD__);
        $auth0 = new Auth0($auth0Config);
      
        error_log('DEBUG: Calling Auth0 login method');
        // The Auth0 login() method should redirect to Auth0
        return $auth0->login(null, null, $authorize_params,'code');
   }

   public function actionCallback() : Response {
        error_log('DEBUG: LoginController::actionCallback() called');
        
        // Get a handle of the Auth0 service (we don't know if it has an alias)
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $auth0 = new Auth0($auth0Config);
        
        error_log('DEBUG: Getting user profile from Auth0');
        // Try to get the user information
        $profile = $auth0->getUser();
        
        error_log('DEBUG: Auth0 profile: ' . print_r($profile, true));
      
        // try {
            
            return $this->registerOrLoginFromProfile($profile);

        // } catch(\Exception $e) {
        //     Craft::error('Couldn't login. '. $e->getTraceAsString(), __METHOD__);
        //     return $this->redirect($this->originUrl);
        // }

   }

   private function registerOrLoginFromProfile(array $profile)
    {
        error_log('DEBUG: registerOrLoginFromProfile called');
      
        // Upsert new user
        $craftUser = $this->upsertUser($profile);
        if (!$craftUser) {
            error_log('DEBUG: Failed to create/get craft user');
            throw new \Exception('Craft user could not be created.');
        }

        error_log('DEBUG: Craft user created/found: ' . $craftUser->email);

        // Login
        return $this->login($craftUser);
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
        error_log('DEBUG: upsertUser called with email: ' . $profile['email']);

        // Registration of an existing user with a matching email
        $user = Craft::$app->users->getUserByUsernameOrEmail($profile['email']);
        
        if ($user) {
            error_log('DEBUG: Found existing user: ' . $user->email);
            // Check if sub has been set, otherwise set it
            
            if ($user->getSub() !== $profile['sub']) {
                $user->setSub($profile['sub']);
                // Save the user to persist the sub
                if (!Craft::$app->elements->saveElement($user)) {
                    error_log('DEBUG: Failed to save existing user with sub');
                }
            }
            return $user;
        }

        error_log('DEBUG: Creating new user');
        $newUser = new User();
      
        // Generate username 
        $pattern = " ";
        $firstPart = strstr(strtolower($profile['name']), $pattern, true);
        $firstPart =  $firstPart === '' ? strstr(strtolower($profile['email']), "@", true) : $firstPart;
        $secondPart = substr(strstr(strtolower($profile['name']), $pattern, false), 0,4);
        $nrRand = rand(0, 100);
        $name = trim($firstPart).trim($secondPart);
        $username = $name.trim($nrRand);

        error_log('DEBUG: Setting user attributes - email: ' . $profile['email'] . ', name: ' . $profile['name'] . ', username: ' . $username);

        $newUser->setAttributes([
            'email' => $profile['email'],
            'name' => $profile['name'],
            'username' => $username
        ]);
   
        $newUser->setSub($profile['sub']);

        error_log('DEBUG: Before save - user email: ' . $newUser->email);

        // Save user
        if (!Craft::$app->elements->saveElement($newUser)) {
            error_log('DEBUG: Failed to save new user: ' . print_r($newUser->getErrors(), true));
            Craft::error('There was a problem creating the user:' . print_r($newUser->getErrors(), true), __METHOD__);
            throw new \Exception('Craft user could not be created.');
        }

        error_log('DEBUG: After save - user email: ' . $newUser->email);
        return $newUser;
    }

    private function login(User $craftUser): Response
    {
        error_log('DEBUG: Attempting to login user: ' . $craftUser->email);

        // Craft::dd(Craft::$app->getUser()->login($craftUser));
        if (!Craft::$app->getUser()->login($craftUser)) {
            error_log('DEBUG: Craft login failed');
            return $this->_handleLoginFailure();
        }

        error_log('DEBUG: Craft login successful');
        
        $session = Craft::$app->getSession();
        $session->setFlash('login','You have been successfully logged in');
        
        // Check if auth0LoginRedirect is set in the general config
        $login_redirect = '/admin'; // Default to admin panel
        if (isset(Craft::$app->config->general->auth0LoginRedirect)) {
            $login_redirect = Craft::$app->config->general->auth0LoginRedirect;
        }

        error_log('DEBUG: Redirecting to: ' . $login_redirect);
        return $this->redirect($login_redirect);
    }

    /**
     * Handles a failed login attempt.
     *
     * @return Response
     * @throws \craft\errors\MissingComponentException
     */
    private function _handleLoginFailure(): Response
    {
        $this->setError(Craft::t('social', 'Could not authenticate.'));

        return $this->redirect($this->originUrl);
    }
}