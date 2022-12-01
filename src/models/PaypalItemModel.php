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
 * PaypalItemModel Model
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
class PaypalItemModel extends Model {
    // Public Properties
    // =========================================================================

    public $id;
    public $uid;
    public $dateCreated;
    public $dateUpdated;
    public $name;
    public $identifier;
    public $cost;
    public $gratisCount;
    public $gratisItem;
    public $gratisDescription;
    public $currency = 'USD';
    public $sortOrder;

    // Public Properties
    // =========================================================================

    protected Number $costField;

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules() {
        return [
            [['name', 'gratisDescription'], 'string'],
            [['identifier'], 'string', 'max' => 21],
            [['cost'], 'default', 'value' => 0.00],
            [['cost'], 'filter', 'filter' => [$this, 'sanitizeInput']],
            [['cost', 'gratisCount', 'gratisItem'], 'number'],
            [['name', 'identifier', 'name'], 'required'],
            [['identifier'], 'unique', 'targetClass' => PaypalItemRecord::class, 'filter' => function ($query) {
                if ($this->identifier !== null) {
                    $query->andWhere('`identifier` = :identifier', ['identifier' => $this->identifier]);

                    if ($this->id) {
                        $query->andWhere('`id` != :id', ['id' => $this->id]);
                    }
                }
            }]
        ];
    }

    public function sanitizeInput($value) {
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value);
        $value = preg_replace('/[$,]/', '', $value);

        return $value;
    }
}
