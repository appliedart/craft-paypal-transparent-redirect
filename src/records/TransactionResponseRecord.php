<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\records;

use appliedart\paypaltransparentredirect\Plugin;

use Craft;
use craft\db\ActiveRecord;

class TransactionResponseRecord extends ActiveRecord {
    public static function tableName() {
        return '{{%paypaltransparentredirect_trxresponse}}';
    }
}
