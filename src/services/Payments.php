<?php

namespace appliedart\paypaltransparentredirect\services;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\models\Settings as PaypalSettings;
use appliedart\paypaltransparentredirect\models\PaypalItemModel;
use appliedart\paypaltransparentredirect\models\TransactionResponse;
use appliedart\paypaltransparentredirect\records\PaypalItemRecord;
use appliedart\paypaltransparentredirect\records\TransactionResponseRecord;
use appliedart\paypaltransparentredirect\events\PaypalItemEvent;
use appliedart\paypaltransparentredirect\events\TransactionResponseEvent;

use Cake\Utility\Hash;
use Exception;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;

/**
 *
 * @property-read mixed $totalItems
 */
class Payments extends Component {

	protected $user;
	protected $settings;
	protected $request;
	protected $debug;
	protected $_currentResponse = [];
	protected $_countries = [];
	protected $_countriesByNumericCode = [];

	public function init() {
		parent::init();

		$this->user = Craft::$app->user->identity;
		$this->settings = Plugin::$plugin->settings;
		$this->request = Craft::$app->getRequest();
		$this->debug = boolval(getenv('PAYFLOW_DEBUG'));
	}

	public function getCountryList() {
		if (!$this->_countries || empty($this->_countries)) {
			$countryRepository = new CountryRepository();
			$this->_countries = $countryRepository->getAll();
		}
		return $this->_countries;
	}

	public function getCountryByNumericCode($code) {
		$country = NULL;
		if (!$this->_countriesByNumericCode || empty($this->_countriesByNumericCode)) {
			$this->getCountryList();
			foreach (array_keys($this->_countries) as $countryCode) {
				$i = $countryCode;
				$numericCode = $this->_countries[$i]->getNumericCode() . '';
				$this->_countriesByNumericCode[$numericCode] =& $this->_countries[$i];
			}
		}

		if (isset($this->_countriesByNumericCode[$code])) {
			$country = $this->_countriesByNumericCode[$code];
		}

		return $country;
	}

	public function getPayflowEndpoint($payflowAuto = FALSE) {
		$payflowEndpoint = NULL;
		$payflowEnvironment = $this->settings->getPayflowEnvironment();
		$payflowOption = $payflowAuto ? 'pro' : 'link';

		if ($payflowEnvironment == 'pilot' || $payflowEnvironment == 'sandbox') {
			$payflowEndpoint = 'https://pilot-payflow' . $payflowOption . '.paypal.com';
		} else {
			$payflowEndpoint = 'https://payflow' . $payflowOption . '.paypal.com';
		}

		return $payflowEndpoint;
	}

	public function renderPaymentForm(PaypalItemModel $item, $additionalItems = [], $fieldData = []) {
		$variables = [];
		$params = [];

		$secureTokenId = $this->request->getBodyParam('SECURETOKENID');
		$secureToken = $this->request->getBodyParam('SECURETOKEN');

		if (!$secureTokenId || !$secureToken) {
			$secureTokenId = substr(uniqid($item->identifier . '-') . uniqid(), 0, 36);
			$secureTokenParams = $this->getSecureTokenParams($item, $additionalItems, $fieldData, $secureTokenId);
			$secureToken = $this->getSecureToken($secureTokenParams);
		}

		$variables['debug'] = $this->debug;
		$variables['payflowEndpoint'] = $this->getPayflowEndpoint();
		$variables['hiddenFields'] = $this->getSecureTokenParams($item, $additionalItems, $fieldData, $secureTokenId, $secureToken, FALSE);
		$variables['itemCost'] = $item->cost;
		$variables['secureTokenId'] = $secureTokenId;
		$variables['secureToken'] = $secureToken;
		$variables['paymentInputDefaults'] = $this->getPaymentFormDefaults($this->debug);

		// var_dump($variables);

		return Craft::$app->view->renderTemplate('paypal-transparent-redirect/items/payment', $variables);
	}

	public function getPaymentFormDefaults($debug = FALSE) {
		$payflowEnvironment = $this->settings->getPayflowEnvironment();
		$payflowTestMode = $payflowEnvironment == 'live' ? false : true;
		$payflowMode = $payflowTestMode ? 'TEST' : '';

		$paymentInputDefaults = [
			'BILLTOFIRSTNAME' => $debug ? 'John' : NULL,
			'BILLTOLASTNAME' => $debug ? 'Doe' : NULL,
			'BILLTOSTREET' => $debug ? '123 Test' : NULL,
			'BILLTOSTREET2' => $debug ? '' : NULL,
			'BILLTOCITY' => $debug ? 'Beverly Hills' : NULL,
			'BILLTOSTATE' => $debug ? 'CA' : NULL,
			'BILLTOZIP' => $debug ? '90210' : NULL,
			'BILLTOCOUNTRY' => 'US',
			'BILLTOEMAIL' => NULL,
			'BILLTOPHONENUM' => NULL,
			'ACCT' => $payflowMode === 'TEST' ? getenv('PAYFLOW_TEST_CC_NUM') : NULL,
			'EXPDATE' => $payflowMode === 'TEST' ? getenv('PAYFLOW_TEST_CC_EXP') : NULL,
			'CVV2' => $payflowMode === 'TEST' ? '123' : NULL
		];

		if (isset($this->user->defaultAddress->address1)) $paymentInputDefaults['BILLTOSTREET'] = $this->user->defaultAddress->address1;
		if (isset($this->user->defaultAddress->address2)) $paymentInputDefaults['BILLTOSTREET2'] = $this->user->defaultAddress->address2;
		if (isset($this->user->defaultAddress->locality)) $paymentInputDefaults['BILLTOCITY'] = $this->user->defaultAddress->locality;
		if (isset($this->user->defaultAddress->administrativeAreaCode)) $paymentInputDefaults['BILLTOSTATE'] = $this->user->defaultAddress->administrativeAreaCode;
		if (isset($this->user->defaultAddress->postalCode)) $paymentInputDefaults['BILLTOZIP'] = $this->user->defaultAddress->postalCode;
		if (isset($this->user->defaultAddress->countryCode)) $paymentInputDefaults['BILLTOCOUNTRY'] = $this->user->defaultAddress->countryCode;

		if (isset($this->user->firstName)) $paymentInputDefaults['BILLTOFIRSTNAME'] = $this->user->firstName;
		if (isset($this->user->lastName)) $paymentInputDefaults['BILLTOLASTNAME'] = $this->user->lastName;
		if (isset($this->user->email)) $paymentInputDefaults['BILLTOEMAIL'] = $this->user->email;

		if (isset($this->user->homePhone->phone)) {
			$paymentInputDefaults['BILLTOPHONENUM'] = $this->user->homePhone->phone;
		} else if (isset($this->user->cellPhone->phone)) {
			$paymentInputDefaults['BILLTOPHONENUM'] = $this->user->cellPhone->phone;
		}

		if (empty($this->_currentResponse) && $postVars = $this->request->post()) {
			$this->_currentResponse = $postVars;
		}

		foreach ($this->_currentResponse as $key => $val) {
			if (array_key_exists($key, $paymentInputDefaults)) {
				$paymentInputDefaults[$key] = $val;
			}
		}

		return $paymentInputDefaults;
	}

	public function processResponse() {
		$response = [];

		if (empty($this->_currentResponse) && $postVars = $this->request->post()) {
			$this->_currentResponse = $postVars;
		}

		if (array_key_exists('RESULT', $this->_currentResponse) && array_key_exists( 'RESPMSG', $this->_currentResponse)) {
			$response['post'] = $this->_currentResponse;
			$respMsgTrimmed = preg_replace('/\s*:.*$/', '', $this->_currentResponse['RESPMSG']);

			if ($this->_currentResponse['RESULT'] === '0' && ($this->_currentResponse['RESPMSG'] == 'Approved' || $respMsgTrimmed == 'Approved')) {
				$response['success'] = TRUE;
			} else {
				$response['error'] = TRUE;
			}

			$responseModel = $this->_getResponseModelFromPost();
			if (Plugin::$plugin->transactions->saveResponse($responseModel)) {
				$response['transactionResponse'] = $responseModel;
			}
		}

		return $response;
	}

	public function getSecureTokenParams(PaypalItemModel $item, $additionalItems, $userParams = [], $secureTokenId = NULL, $secureToken = NULL, $private = TRUE) {
		$payflowEnvironment = $this->settings->getPayflowEnvironment();
		$currency = $this->settings->getCurrency();
		$itemCost = number_format(floatval($item->cost), 2, '.', '');

		$payflowTestMode = $payflowEnvironment == 'live' ? false : true;
		$payflowMode = $payflowTestMode ? 'TEST' : '';
		$returnUrl = UrlHelper::siteUrl($this->request->pathinfo);

		$params = !$private || $secureToken ? $userParams : [
			'PARTNER' => $this->settings->getPayflowPartner(),
			'VENDOR' => $this->settings->getPayflowVendor(),
			'USER' => $this->settings->getPayflowUsername(),
			'PWD' => $this->settings->getPayflowPassword(),
			'CREATESECURETOKEN' => 'Y',
			'TENDER' => 'C',
			'RETURNURL' => $returnUrl,
			'CANCELURL' => $returnUrl,
			'ERRORURL' => $returnUrl,
		];

		$totalAmt = $itemCost;

		$params = $params + [
			'SILENTTRAN' => 'TRUE',
			'CURRENCY' => $currency,
			'VERBOSITY' => 'HIGH',
			'MODE' => $payflowMode,
			'TRXTYPE' => 'S',
			'L_NAME0' => $item->name,
			'L_ITEMNUMBER0' => $item->identifier,
			'L_COST0' => $itemCost,
			'L_QTY0' => 1,
		];

		$i = 0;
		foreach ($additionalItems as $additionalItem) {
			$i++;
			$itemTotal = $additionalItem['cost'] * $additionalItem['qty'];
			$totalAmt += $itemTotal;
			$params = $params + [
				('L_NAME' . $i) => $additionalItem['name'],
				('L_ITEMNUMBER' . $i) => $additionalItem['identifier'],
				('L_COST' . $i) => number_format(floatval($additionalItem['cost']), 2, '.', ''),
				('L_QTY' . $i) => $additionalItem['qty'],
			];
		}

		$totalAmt = number_format(floatval($totalAmt), 2, '.', '');

		$params = $params + [
			'SHIPPINGAMT' => '0.00',
			'HANDLINGAMT' => '0.00',
			'FREIGHTAMT' => '0.00',
			'TAXAMT' => '0.00',
			'ITEMAMT' => $totalAmt,
			'AMT' => $totalAmt,
		];

		if ($secureTokenId) {
			$params['SECURETOKENID'] = $secureTokenId;
		}

		if ($secureToken) {
			$params['SECURETOKEN'] = $secureToken;
		}

		return $params;
	}

	public function getSecureToken($params) {
		$paramList = [];
		$secureToken = NULL;

		foreach ($params as $index => $value) {
			$paramList[] = $index . '[' . strlen($value) . ']=' . $value;
		}

		$body = implode('&', $paramList);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->getPayflowEndpoint(TRUE));
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		$result = curl_exec($curl);
		curl_close($curl);

		$response = [];
		foreach (explode('&', $result) as $keyValRaw) {
			$keyValPair = explode('=', $keyValRaw);
			$response[$keyValPair[0]] = urldecode($keyValPair[1]);
		}

		/* $response keys: RESULT, SECURETOKEN, SECURETOKENID, RESPMSG */

		if (array_key_exists('SECURETOKEN', $response) && $response['SECURETOKEN']) {
			$secureToken = $response['SECURETOKEN'];
		}

		return $secureToken;
	}

	/**
	 * @return TransactionResponse
	 * @throws \yii\web\BadRequestHttpException
	 */
	protected function _getResponseModelFromPost() {
		if (!$this->request->getIsPost()) {
			return NULL;
		}

		if (empty($this->_currentResponse) && $postVars = $this->request->post()) {
			$this->_currentResponse = $postVars;
		}

		if (array_key_exists('responseId', $this->_currentResponse)) {
			$item = Plugin::$plugin->transactions->getResponseById($this->_currentResponse['responseId']);
		} else {
			$item = new TransactionResponse();
		}

		$fieldNames = TransactionResponses::getResponseFieldNames();
		$item->fullResponse = json_encode($this->_currentResponse);

		foreach ($fieldNames as $fieldName) {
			if (array_key_exists($fieldName, $this->_currentResponse)) {
				$item->{$fieldName} = substr($this->_currentResponse[$fieldName], 0, 100);
			}
		}

		return $item;
	}
}
