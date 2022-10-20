<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\variables;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\models\Settings as PaypalSettings;

use Craft;
use yii\di\ServiceLocator;

class PaypalTransparentRedirectVariable extends ServiceLocator {
    public $config;

    public function __construct($config = []) {
        $config['components'] = Plugin::$plugin->getComponents();

        parent::__construct($config);
    }

    public function getPluginName() {
        return Plugin::$plugin->getPluginName();
    }

    public function payflowEnvironmentOptions() {
        return PaypalSettings::$payflowEnvironmentOptions;
    }

    public function getSettings() {
        return Plugin::$plugin->getSettings();
    }
}
