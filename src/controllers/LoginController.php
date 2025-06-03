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
    public function beforeAction($action): bool
    {
        // Allow anonymous access to auth and callback actions
        if (in_array($action->id, ['auth', 'callback'])) {
            // Skip authentication check for these actions
            return parent::beforeAction($action);
        }
        
        return parent::beforeAction($action);
    }
    
    /**
     * @inheritdoc
     */
    protected function checkAccess($action): void
    {
        // Allow anonymous access to auth and callback actions
        if (in_array($action->id, ['auth', 'callback'])) {
            return;
        }
        
        parent::checkAccess($action);
    }

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
        // Get a handle of the Auth0 service (we don't know if it has an alias)
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $auth0 = new Auth0($auth0Config);
        
        // Try to get the user information
        $profile = $auth0->getUser();
      
        // try {
            
            return $this->registerOrLoginFromProfile($profile);

        // } catch(\Exception $e) {
        //     Craft::error('Couldn't login. '. $e->getTraceAsString(), __METHOD__);
        //     return $this->redirect($this->originUrl);
        // }

   }

   private function registerOrLoginFromProfile(array $profile)
    {
      
        // Upsert new user
        $craftUser = $this->upsertUser($profile);
        if (!$craftUser) {
            throw new \Exception('Craft user could not be created.');
        }


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

        // Registration of an existing user with a matching email
        $user = Craft::$app->users->getUserByUsernameOrEmail($profile['email']);
        
        if ($user) {
            // Check if sub has been set, otherwise set it
            
            if ($user->getSub() !== $profile['sub']) {
                $user->setSub($profile['sub']);
            }
            return $user;
        }

        $newUser = new User();
      
        // Generate username 
        $pattern = " ";
        $firstPart = strstr(strtolower($profile['name']), $pattern, true);
        $firstPart =  $firstPart === '' ? strstr(strtolower($profile['email']), "@", true) : $firstPart;
        $secondPart = substr(strstr(strtolower($profile['name']), $pattern, false), 0,4);
        $nrRand = rand(0, 100);
        $name = trim($firstPart).trim($secondPart);
        $username = $name.trim($nrRand);

        $newUser->setAttributes([
            'email' => $profile['email'],
            'name' => $profile['name'],
            'username' => $username
        ]);
   
        $newUser->setSub($profile['sub']);

        // Save user
        if (!Craft::$app->elements->saveElement($newUser)) {
            Craft::error('There was a problem creating the user:' . print_r($newUser->getErrors(), true), __METHOD__);
            throw new \Exception('Craft user could not be created.');
        }

        return $newUser;
    }

    private function login(User $craftUser): Response
    {

        // Craft::dd(Craft::$app->getUser()->login($craftUser));
        if (!Craft::$app->getUser()->login($craftUser)) {
            return $this->_handleLoginFailure();
        }

        
        $session = Craft::$app->getSession();
        $session->setFlash('login','You have been successfully logged in');
        
        $login_redirect = Craft::$app->config->general->__isset('auth0LoginRedirect') ? Craft::$app->config->general->auth0LoginRedirect : '/';

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