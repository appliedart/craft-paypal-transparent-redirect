<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\fields;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\models\PaypalItemModel;
use appliedart\paypaltransparentredirect\assetbundles\paypaltransparentredirectfieldfield\PaypalTransparentRedirectFieldFieldAsset;

use NumberFormatter;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\fields\BaseOptionsField;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;
use craft\fields\data\SingleOptionFieldData;

/**
 * PaypalTransparentRedirectField Field
 *
 * Whenever someone creates a new field in Craft, they must specify what
 * type of field it is. The system comes with a handful of field types baked in,
 * and we’ve made it extremely easy for plugins to add new ones.
 *
 * https://craftcms.com/docs/plugins/field-types
 *
 * @author    Applied Art & Technology
 * @package   PaypalTransparentRedirect
 * @since     0.1.0
 *
 * @property-read array $options
 * @method array getOptions()
 */
class PaypalItem extends BaseOptionsField {

    public $someAttribute;
    public $options;
    protected $formatter;
    protected $multi = false;
    protected $_items = [];
    protected $_itemIds = [];

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string {
        return Craft::t('paypal-transparent-redirect', 'Paypal Item');
    }

    // Public Methods
    // =========================================================================


    public function init() {
        $this->formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);

        parent::init();
    }

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
        $rules = parent::rules();
        $rules = array_merge($rules, [
            //['someAttribute', 'string'],
            //['someAttribute', 'default', 'value' => 'Some Default'],
        ]);
        return $rules;
    }

    public function getOptions() {
        if (is_array($this->options) && !empty($this->options)) {
            return $this->options;
        }

        $this->options = [];
        $this->_items = Plugin::$plugin->items->getItems();
        $this->_itemIds = Plugin::$plugin->items->getItemIds();

        $currency = Plugin::$plugin->settings->getCurrency();

        foreach ($this->_items as $item) {
            $this->options[$item->id] = $item->name . ' [' . $item->identifier . ']';

            if ($currency && $item->cost && $item->cost > 0) {
                $this->options[$item->id] .= ' - ' . $this->formatter->formatCurrency($item->cost, $currency);
            }
        }

        return $this->options;
    }

    public function getElementValidationRules(): array {
        if (!is_array($this->options) || empty($this->options)) {
            $this->getOptions();
        }

        return [
            ['in', 'range' => $this->_items, 'allowArray' => $this->multi],
        ];
    }

    public function validateOptions() {
        if (!is_array($this->options) || empty($this->options)) {
            $this->getOptions();
        }

        parent::validateOptions();
    }

    public function getContentColumnType(): string {
        return Schema::TYPE_INTEGER;
    }

    /**
     * @inheritdoc
     */
    protected function optionsSettingLabel(): string {
        return Craft::t('app', 'Dropdown Options');
    }

    public function serializeValue($value, ElementInterface $element = null) {
        if ($value instanceof PaypalItemModel) {
            return $value->id;
        }

        return parent::serializeValue($value, $element);
    }

    public function normalizeValue($value, ElementInterface $element = null) {
        if ($value instanceof PaypalItemModel) {
            return $value;
        }

        $item = is_numeric($value) && $value > 0 ? Plugin::$plugin->items->getItemById((int) $value) : NULL;

        if ($item) {
            return $item;
        }

        return NULL;
    }

    public function isValueEmpty($value, ElementInterface $element): bool {
        /** @var MultiOptionsFieldData|SingleOptionFieldData $value */
        if ($value instanceof SingleOptionFieldData) {
            var_dump($value);exit;
            return $value->value === null || $value->value === '';
        } else if ($value instanceof PaypalItemModel) {
            return $value->id === null || $value->id === '' || $value->id === '0' || $value->id === 0;
        }


        return is_countable($value) && count($value) === 0 || is_null($value) || $value === 0;
    }

    public function getSettingsHtml() {
        // Render the settings template
        return Craft::$app->getView()->renderTemplate(
            'paypal-transparent-redirect/_components/fields/PaypalItemField_settings',
            [
                'field' => $this,
            ]
        );
    }


    public function getInputHtml($value, ElementInterface $element = null): string {
        $paypal = Plugin::getInstance();

        // Register our asset bundle
        Craft::$app->getView()->registerAssetBundle(PaypalTransparentRedirectFieldFieldAsset::class);

        // Get our id and namespace
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        // Variables to pass down to our field JavaScript to let it namespace properly
        $jsonVars = [
            'id' => $id,
            'name' => $this->handle,
            'namespace' => $namespacedId,
            'prefix' => Craft::$app->getView()->namespaceInputId(''),
            ];
        $jsonVars = Json::encode($jsonVars);
        Craft::$app->getView()->registerJs("$('#{$namespacedId}-field').PaypalTransparentRedirectPaypalTransparentRedirectField(" . $jsonVars . ");");

        $itemOptions = array_replace(['' => Craft::t('paypal-transparent-redirect', 'Select an Item')], $this->getOptions());

        // Render the input template
        return Craft::$app->getView()->renderTemplate(
            'paypal-transparent-redirect/_components/fields/PaypalItemField_input',
            [
                'name' => $this->handle,
                'itemOptions' => $itemOptions,
                'selectedItem' => $value instanceof PaypalItemModel ? $value->id : $value,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId,
            ]
        );
    }
}
