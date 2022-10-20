<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirecttests\unit;

use Codeception\Test\Unit;
use UnitTester;
use Craft;
use appliedart\paypaltransparentredirect\PaypalTransparentRedirect;

/**
 * ExampleUnitTest
 *
 *
 * @author    Applied Art & Technology
 * @package   PaypalTransparentRedirect
 * @since     0.1.0
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
            PaypalTransparentRedirect::class,
            PaypalTransparentRedirect::$plugin
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
