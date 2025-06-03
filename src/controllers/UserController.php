<?php
/**
 * User: cwbmuller
 */

namespace salt\craftauth0\controllers;

use Craft;
use Exception;
use Auth0\SDK\Auth0;
use craft\db\Command;
use craft\helpers\Db;
use yii\web\Response;
use craft\elements\User;
use craft\web\Controller;
use craft\helpers\UrlHelper;
use salt\craftauth0\services\Auth0ApiService;

class UserController extends Controller
{
   
    public static function update($user) {

        
        $user_data = null;
        // If user sub exists then just fetch them from Auth0
        if ($user->sub) {
            try {
                $user_data = (new Auth0ApiService)->fetchAuth0UserById($user->sub);
            }

            catch (Exception $e) {

            }
        } 
        if (!$user_data) {
           
            // Only proceed if email is available
            if ($user->email) {
                // Try create them
                $user_data = (new Auth0ApiService)->searchAuth0UserByEmail($user->email);
               
                if (empty($user_data)) {
                    $auth0_user = (new Auth0ApiService)->createAuth0User($user->email, $user->name);
                    $user->setSub($auth0_user->user_id);
                  
                    
                } else {
                    $user->setSub($user_data[0]->user_id);
                }
            }

        } 
        // If reset password wansn't set but admin specifically checked it, then send
        if ($user->firstSave || $user->passwordResetRequired) {
            // Send Password reset email - but only if email is available
            if ($user->email) {
                (new Auth0ApiService)->sendPasswordResetEmail($user->email);
            }
        }

        return $user;
   }
}