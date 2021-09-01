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
        
        $this->redirectUrl = Craft::$app->getRequest()->getParam('redirect') ?? '/admin';
   
        if ($user = Craft::$app->getUser()->getIdentity()) {    
            $this->redirect($this->redirectUrl);
        }
        $authorize_params = [
            'scope' => 'openid profile email offline_access'
        ];
        $config_params = Craft::$app->config->general->__isset('auth0LoginParams') ? Craft::$app->config->general->auth0LoginParams : false;
        if (is_array($config_params)) {
            $authorize_params = array_merge($authorize_params,$config_params);
        };
        
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
      
        // try {
            
            return $this->registerOrLoginFromProfile($profile);

        // } catch(\Exception $e) {
        //     Craft::error('Couldn’t login. '. $e->getTraceAsString(), __METHOD__);
        //     return $this->redirect($this->originUrl);
        // }

   }

   private function registerOrLoginFromProfile(array $profile)
    {
      
        // Upsert new user
        $craftUser = $this->upsertUser($profile);
        if (!$craftUser) {
            throw new RegistrationException('Craft user couldn’t be created.');
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
            throw new RegistrationException('Craft user couldn’t be created.');
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
        

        return $this->redirect('/admin');
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