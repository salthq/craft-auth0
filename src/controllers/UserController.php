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

        error_log('DEBUG: UserController::update called for user: ' . ($user->email ?? 'no email') . ', sub: ' . ($user->sub ?? 'no sub'));
        
        $user_data = null;
        // If user sub exists then just fetch them from Auth0
        if ($user->sub) {
            error_log('DEBUG: User has sub, trying to fetch from Auth0: ' . $user->sub);
            try {
                $user_data = (new Auth0ApiService)->fetchAuth0UserById($user->sub);
                error_log('DEBUG: Successfully fetched user from Auth0');
            }

            catch (Exception $e) {
                error_log('DEBUG: Failed to fetch user from Auth0: ' . $e->getMessage());
            }
        } else {
            error_log('DEBUG: User has no sub field');
        }
        
        if (!$user_data) {
            error_log('DEBUG: No user_data, checking if email is available');
           
            // Only proceed if email is available
            if ($user->email) {
                error_log('DEBUG: Email available, searching Auth0 for: ' . $user->email);
                // Try create them
                $user_data = (new Auth0ApiService)->searchAuth0UserByEmail($user->email);
               
                if (empty($user_data)) {
                    error_log('DEBUG: User not found in Auth0, trying to create them');
                    $auth0_user = (new Auth0ApiService)->createAuth0User($user->email, $user->name);
                    $user->setSub($auth0_user->user_id);
                  
                    
                } else {
                    error_log('DEBUG: User found in Auth0, setting sub: ' . $user_data[0]->user_id);
                    $user->setSub($user_data[0]->user_id);
                }
            } else {
                error_log('DEBUG: No email available, skipping Auth0 operations');
            }

        } else {
            error_log('DEBUG: user_data found, skipping Auth0 creation');
        }
        
        // If reset password wansn't set but admin specifically checked it, then send
        if ($user->firstSave || $user->passwordResetRequired) {
            error_log('DEBUG: Sending password reset email');
            // Send Password reset email - but only if email is available
            if ($user->email) {
                (new Auth0ApiService)->sendPasswordResetEmail($user->email);
            }
        }

        return $user;
   }
}