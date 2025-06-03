<?php
/**
 * craft-auth0 plugin for Craft CMS 4.x
 *
 * login with auth0
 *
 * @link      SaltEdu.co
 * @copyright Copyright (c) 2021 Salt
 */

namespace salt\craftauth0;


use Craft;
use craft\web\View;
use yii\base\Event;
use craft\base\Plugin;
use craft\web\Request;
use yii\web\UserEvent;
use craft\base\Element;
use craft\elements\User;
use craft\web\Controller;
use craft\web\UrlManager;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\User as WebUser;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterTemplateRootsEvent;
use salt\craftauth0\controllers\UserController;
use salt\craftauth0\behaviors\Auth0UserBehavior;
use salt\craftauth0\controllers\LoginController;
use salt\craftauth0\controllers\LogoutController;
use craft\events\RegisterElementTableAttributesEvent;

/**
 * Craft plugins are very much like little applications in and of themselves. We've made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we're going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://docs.craftcms.com/v4/extend/
 *
 * @author    Salt
 * @package   Craftauth0
 * @since     1
 *
 */
class Auth0 extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Auth0login::$plugin
     *
     * @var Auth0login
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin's migrations, you'll need to increase its schema version.
     *
     * @var string
     */
    public string $schemaVersion = '1';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public bool $hasCpSection = false;

    /** @var array */
    public $controllerMap = [
        'login' => LoginController::class,
        'logout' => LogoutController::class,
        'user' => UserController::class,
    ];

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Craftauth0login::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;
        // Define a custom alias named after the namespace
        Craft::setAlias('@auth0', __DIR__);
        // Set the controllerNamespace 
       
        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['_auth0'] = __DIR__ . '/templates';
            }
        );


        // Do something after we're installed
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                    // We were just installed
                }
            }
        );

        Event::on(User::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, function (RegisterElementTableAttributesEvent $event) {
            $event->tableAttributes['sub'] = [
                    'label' => "Auth0 Sub"
                ];
        });

     

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['login'] = 'craft-auth0/login/auth';
                $event->rules['auth'] = 'craft-auth0/login/auth';
                $event->rules['auth0/callback'] = 'craft-auth0/login/callback';
                $event->rules['logout'] = 'craft-auth0/logout/logout';
                $event->rules['logout-confirm'] = ['template' => '_auth0/logout_confirm'];
            }
        );
       
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // Override all possible login/logout routes
                $event->rules['login'] = 'craft-auth0/login/auth';
                $event->rules['logout'] = 'craft-auth0/logout/logout';
                $event->rules['admin/login'] = 'craft-auth0/login/auth';
                $event->rules['admin/logout'] = 'craft-auth0/logout/logout';
                // Catch any login route variations
                $event->rules['<path:.*>/login'] = 'craft-auth0/login/auth';
               
            }
        );

        Event::on(
            WebUser::class,
            WebUser::EVENT_AFTER_LOGOUT,
            function (UserEvent $event) {
                header('Location: /logout');
                exit;
            }
        );

        Event::on(
            User::class,
            User::EVENT_DEFINE_BEHAVIORS,
            function(DefineBehaviorsEvent $event) {
                $event->sender->attachBehavior(
                    'Auth0 user behavior',
                    Auth0UserBehavior::class
                );
            }
        );

        Event::on(\craft\services\Elements::class, \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT, function(Event $event) {
            if ($event->element instanceof User) {
                UserController::update($event->element);
            }
        });

        /**
         * Logging in Craft involves using one of the following methods:
         *
         * Craft::trace(): record a message to trace how a piece of code runs. This is mainly for development use.
         * Craft::info(): record a message that conveys some useful information.
         * Craft::warning(): record a warning message that indicates something unexpected has happened.
         * Craft::error(): record a fatal error that should be investigated as soon as possible.
         *
         * Unless `devMode` is on, only Craft::warning() & Craft::error() will log to `craft/storage/logs/web.log`
         *
         * It's recommended that you pass in the magic constant `__METHOD__` as the second parameter, which sets
         * the category to the method (prefixed with the fully qualified class name) where the constant appears.
         *
         * To enable the Yii debug toolbar, go to your user account in the AdminCP and check the
         * [] Show the debug toolbar on the front end & [] Show the debug toolbar on the Control Panel
         *
         * http://www.yiiframework.com/doc-2.0/guide-runtime-logging.html
         */
        Craft::info(
            Craft::t(
                'craft-auth0',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
        Craft::warning('plugin loaded',__METHOD__);
    }

   
}
