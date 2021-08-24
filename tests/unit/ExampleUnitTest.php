<?php
/**
 * craft-auth0-login plugin for Craft CMS 3.x
 *
 * login with auth0
 *
 * @link      SaltEdu.co
 * @copyright Copyright (c) 2021 Salt
 */

namespace salt\craftauth0logintests\unit;

use Codeception\Test\Unit;
use UnitTester;
use Craft;
use salt\craftauth0login\Craftauth0login;

/**
 * ExampleUnitTest
 *
 *
 * @author    Salt
 * @package   Craftauth0login
 * @since     1
 */
class ExampleUnitTest extends Unit
{
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    // Public methods
    // =========================================================================

    // Tests
    // =========================================================================

    /**
     *
     */
    public function testPluginInstance()
    {
        $this->assertInstanceOf(
            Craftauth0login::class,
            Craftauth0login::$plugin
        );
    }

    /**
     *
     */
    public function testCraftEdition()
    {
        Craft::$app->setEdition(Craft::Pro);

        $this->assertSame(
            Craft::Pro,
            Craft::$app->getEdition()
        );
    }
}
