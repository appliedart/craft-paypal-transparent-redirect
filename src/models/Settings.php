<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\models;

use appliedart\paypaltransparentredirect\Plugin;

use Craft;
use craft\helpers\App;
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * PaypalTransparentRedirect Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Applied Art & Technology
 * @package   PaypalTransparentRedirect
 * @since     0.1.0
 */
class Settings extends Model {
    // Public Properties
    // =========================================================================

    public static $payflowEnvironmentOptions = [
        'sandbox' => 'Sandbox',
        'pilot' => 'Pilot',
        'live' => 'Live'
    ];


    /**
     * Some field model attribute
     *
     * @var string
     */
    public $currency;
    public $payflowEnvironment;
    public $payflowPartner;
    public $payflowVendor;
    public $payflowUsername;
    public $payflowPassword;

    public function getCurrency(): string {
        return App::parseEnv($this->currency);
    }

    public function getPayflowEnvironment(): string {
        return App::parseEnv($this->payflowEnvironment);
    }

    public function getPayflowPartner(): string {
        return App::parseEnv($this->payflowPartner);
    }

    public function getPayflowVendor(): string {
        return App::parseEnv($this->payflowVendor);
    }

    public function getPayflowUsername(): string {
        return App::parseEnv($this->payflowUsername);
    }

    public function getPayflowPassword(): string {
        return App::parseEnv($this->payflowPassword);
    }

    public function behaviors() {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'currency',
                    'payflowEnvironment',
                    'payflowPartner',
                    'payflowVendor',
                    'payflowUsername',
                    'payflowPassword'
                ],
            ];
        return $behaviors;
    }


    public function rules() {
        return [
            [['payflowEnvironment', 'payflowPartner', 'payflowVendor', 'payflowUsername', 'payflowPassword', 'currency'], 'string'],
            [['payflowEnvironment', 'payflowPartner', 'payflowVendor', 'payflowUsername', 'payflowPassword'], 'default', 'value' => NULL],
            [['currency'], 'default', 'value' => 'USD'],
            [['payflowEnvironment'], 'in', 'range' => array_keys(self::$payflowEnvironmentOptions)],
        ];
    }
}
