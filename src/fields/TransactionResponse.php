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
use appliedart\paypaltransparentredirect\models\TransactionResponse as TransactionResponseModel;
use appliedart\paypaltransparentredirect\assetbundles\paypaltransparentredirectfieldfield\PaypalTransparentRedirectFieldFieldAsset;

use NumberFormatter;
use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\base\Field;
use craft\fields\BaseOptionsField;
use craft\helpers\Db;
use yii\db\Schema;
use craft\helpers\Json;
use craft\fields\data\MultiOptionsFieldData;

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
class TransactionResponse extends BaseOptionsField {

	public $someAttribute;
	public $options;
	protected $multi = true;
	protected $_responses = [];
	protected $_responseIds = [];

	/**
	 * Returns the display name of this class.
	 *
	 * @return string The display name of this class.
	 */
	public static function displayName(): string {
		return Craft::t('paypal-transparent-redirect', 'Transaction Response');
	}

	// Public Methods
	// =========================================================================


	public function init() {
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
	public function rules(): array {
		$rules = parent::rules();
		// $rules = array_merge($rules, []);
		return $rules;
	}

	public function getOptions() {
		if (is_array($this->options) && !empty($this->options)) {
			return $this->options;
		}

		$this->options = [];
		$this->_responses = Plugin::$plugin->transactions->getResponses();
		$this->_responseIds = Plugin::$plugin->transactions->getResponseIds();

		foreach ($this->_responses as $response) {
			$this->options[$response->id] = $response->PNREF . ' [' . $response->dateCreated->format('r') . ']';
		}

		return $this->options;
	}

	public function getElementValidationRules(): array {
		if (!is_array($this->options) || empty($this->options)) {
			$this->getOptions();
		}

		return [
			// ['in', 'range' => $this->_responses, 'allowArray' => $this->multi],
		];
	}

	public function validateOptions() {
		if (!is_array($this->options) || empty($this->options)) {
			$this->getOptions();
		}

		parent::validateOptions();
	}

	public function getContentColumnType(): string {
		return Schema::TYPE_STRING;
	}

	/**
	 * @inheritdoc
	 */
	protected function optionsSettingLabel(): string {
		return Craft::t('app', 'Dropdown Options');
	}

	/* TODO: verify */
	public function serializeValue($value, ElementInterface $element = null) {
		// var_dump($value);echo "serializeValue";exit;
		if ($value instanceof TransactionResponseModel) {
			return [$value->id];
		}

		if (is_string($value)) {
			$value = Json::decodeIfJson($value);
		}

		if (is_array($value)) {
			$serialized = [];
			foreach ($value as $v) {
				if ($v instanceof TransactionResponseModel) {
					$serialized[] = $v->id;
				} else if (is_numeric($v) && $v > 0) {
					$serialized[] = $v;
				}
			}

			return empty($serialized) ? NULL : $serialized;
		}

		if (is_numeric($value) && $value > 0) {
			return [$value];
		}

		return parent::serializeValue($value, $element);
	}

	/* TODO: verify */
	public function normalizeValue($value, ElementInterface $element = null) {
		// var_dump($value);echo "normalizeValue";exit;
		if ($value instanceof TransactionResponseModel) {
			return [$value];
		}

		if (is_string($value)) {
			$value = Json::decodeIfJson($value);
		}

		if (is_array($value)) {
			$_value = [];
			foreach ($value as $v) {
				if ($v instanceof TransactionResponseModel) {
					$_value[] = $v;
				} else if (is_numeric($v) && $v > 0) {
					if ($response = Plugin::$plugin->transactions->getResponseById((int) $v)) {
						$_value[] = $response;
					}
				}
			}

			return empty($_value) ? NULL : $_value;
		}

		if (is_numeric($value) && $value > 0 && $response = Plugin::$plugin->transactions->getResponseById((int) $value)) {
			return [$response];
		}

		return NULL;
	}

	public function isValueEmpty($value, ElementInterface $element): bool {
		/** @var MultiOptionsFieldData|SingleOptionFieldData $value */
		if ($value instanceof MultiOptionsFieldData) {
			var_dump($value);exit;
			return $value->value === null || $value->value === '';
		} else if ($value instanceof TransactionResponseModel) {
			return $value->id === null || $value->id === '' || $value->id === '0' || $value->id === 0;
		}


		return is_countable($value) && count($value) === 0 || is_null($value) || $value === 0;
	}

	public function getSettingsHtml() {
		// Render the settings template
		return Craft::$app->getView()->renderTemplate(
			'paypal-transparent-redirect/_components/fields/TransactionResponseField_settings',
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

		// Render the input template
		return Craft::$app->getView()->renderTemplate(
			'paypal-transparent-redirect/_components/fields/TransactionResponseField_input',
			[
				'name' => $this->handle,
				'transactions' => $value instanceof TransactionResponseModel ? $value->id : $value,
				'field' => $this,
				'id' => $id,
				'namespacedId' => $namespacedId,
			]
		);
	}
}
