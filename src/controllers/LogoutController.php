<?php
/**
 * User: cwbmuller
 */

namespace salt\craftauth0\controllers;

use Craft;
use Auth0\SDK\Auth0;
use yii\web\Response;
use craft\web\Session;
use craft\elements\User;
use craft\web\Controller;
use craft\helpers\UrlHelper;

class LogoutController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['logout'];
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Allow anonymous access to logout action
        if ($action->id === 'logout') {
            // Skip authentication check for this action
            return parent::beforeAction($action);
        }
        
        return parent::beforeAction($action);
    }
    
    /**
     * @inheritdoc
     */
    protected function checkAccess($action): void
    {
        // Allow anonymous access to logout action
        if ($action->id === 'logout') {
            return;
        }
        
        parent::checkAccess($action);
    }

    public function actionLogout() {
       
        // Double check user is logged out
        if (Craft::$app->getUser()) {
            Craft::$app->getUser()->logout();
        }
        $auth0Config = Craft::$app->config->getConfigFromFile('craft-auth0');
        $auth0 = new Auth0($auth0Config);

        $logout = $auth0->logout();
        $auth0_logout_url = sprintf(
            'https://%s/v2/logout?client_id=%s&returnTo=%s',
            $auth0Config['domain'],
            $auth0Config['client_id'],
            UrlHelper::url('logout-confirm')
        );

        $session = Craft::$app->getSession();
        $session->setFlash('logout','You have been successfully logged out');
        
        $this->redirect($auth0_logout_url);
        
   }

}