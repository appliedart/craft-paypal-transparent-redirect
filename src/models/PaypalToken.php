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
use appliedart\paypaltransparentredirect\records\PaypalItemRecord;

use Craft;
use craft\base\Model;
use craft\fields\Number;

/**
 * PaypalToken Model
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
class PaypalToken extends Model {
    // Public Properties
    // =========================================================================

    public $id;
    public $uid;
    public $dateCreated;
    public $dateUpdated;
    public $secureToken;
    public $secureTokenId;
    public $lastTransactionId;
    public $itemId;
    public $userId;
    public $userEmail;
    public $userData;

    public function rules() {
        return [];
    }

    public function setProperties($properties) {
        foreach ($properties as $key=>$value) {
            $this->{$key} = $value;
        }
    }
}
