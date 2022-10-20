<?php

namespace appliedart\paypaltransparentredirect\services;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\models\Settings as PaypalSettings;
use appliedart\paypaltransparentredirect\models\PaypalItemModel;
use appliedart\paypaltransparentredirect\records\PaypalItemRecord;
use appliedart\paypaltransparentredirect\events\PaypalItemEvent;

use Cake\Utility\Hash;
use Exception;
use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\UrlHelper;

/**
 *
 * @property-read mixed $totalItems
 */
class Payments extends Component {

	protected $user;
	protected $settings;
	protected $request;
	protected $debug;

	public function init() {
		parent::init();

		$this->user = Craft::$app->user->identity;
		$this->settings = Plugin::$plugin->settings;
		$this->request = Craft::$app->getRequest();
		$this->debug = boolval(getenv('PAYFLOW_DEBUG'));
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

	public function renderPaymentForm(PaypalItemModel $item) {
		$variables = [];
		$params = [];

		$secureTokenId = $this->request->getBodyParam('SECURETOKENID');
		$secureToken = $this->request->getBodyParam('SECURETOKEN');

		if (!$secureTokenId || !$secureToken) {
			$secureTokenId = substr(uniqid($item->identifier . '-') . uniqid(), 0, 36);
			$secureTokenParams = $this->getSecureTokenParams($item, $secureTokenId);
			$secureToken = $this->getSecureToken($secureTokenParams);
		}

		$variables['debug'] = $this->debug;
		$variables['payflowEndpoint'] = $this->getPayflowEndpoint();
		$variables['hiddenFields'] = $this->getSecureTokenParams($item, $secureTokenId, $secureToken, FALSE);
		$variables['itemCost'] = $item->cost;
		$variables['secureTokenId'] = $secureTokenId;
		$variables['secureToken'] = $secureToken;
		$variables['paymentInputDefaults'] = $this->getPaymentFormDefaults($this->debug);

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

		if (isset($this->user->firstName)) $paymentInputDefaults['BILLTOFIRSTNAME'] = $this->user->firstName;
		if (isset($this->user->lastName)) $paymentInputDefaults['BILLTOLASTNAME'] = $this->user->lastName;
		if (isset($this->user->email)) $paymentInputDefaults['BILLTOEMAIL'] = $this->user->email;

		if (isset($this->user->homePhone->phone)) {
			$paymentInputDefaults['BILLTOPHONENUM'] = $this->user->homePhone->phone;
		} else if (isset($this->user->cellPhone->phone)) {
			$paymentInputDefaults['BILLTOPHONENUM'] = $this->user->cellPhone->phone;
		}

		return $paymentInputDefaults;
	}

	public function processResponse() {
		$response = [];
		$postVars = $this->request->getIsPost() ? $this->request->post() : [];

		if (array_key_exists('RESULT', $postVars) && array_key_exists( 'RESPMSG', $postVars)) {
			$response['post'] = $postVars;

			if ($postVars['RESULT'] === '0' && $postVars['RESPMSG'] == 'Approved') {
				$response['success'] = TRUE;
			} else {
				$response['error'] = TRUE;
			}
		}

		return $response;

		/* response keys
			STATE
			SECURETOKEN
			AVSDATA
			BILLTOCITY
			AMT
			ACCT
			BILLTOSTREET
			CORRELATIONID
			AUTHCODE
			FIRSTNAME
			RESULT
			ZIP
			IAVS
			BILLTOSTATE
			BILLTOLASTNAME
			BILLTOCOUNTRY
			EXPDATE
			BILLTOFIRSTNAME
			RESPMSG
			CARDTYPE
			PROCCVV2
			PROCAVS
			NAME
			BILLTOZIP
			COUNTRY
			AVSZIP
			ADDRESS
			CVV2MATCH
			TXID
			BILLTONAME
			PNREF
			PPREF
			TRXTYPE
			AVSADDR
			SECURETOKENID
			CITY
			TRANSTIME
			LASTNAME
		*/
	}

	public function getSecureTokenParams(PaypalItemModel $item, $secureTokenId = NULL, $secureToken = NULL, $private = TRUE) {
		$payflowEnvironment = $this->settings->getPayflowEnvironment();
		$currency = $this->settings->getCurrency();
		$itemCost = number_format(floatval($item->cost), 2, '.', '');

		$payflowTestMode = $payflowEnvironment == 'live' ? false : true;
		$payflowMode = $payflowTestMode ? 'TEST' : '';
		$returnUrl = UrlHelper::siteUrl($this->request->pathinfo);

		$params = !$private || $secureToken ? [] : [
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
			'SHIPPINGAMT' => '0.00',
			'HANDLINGAMT' => '0.00',
			'FREIGHTAMT' => '0.00',
			'TAXAMT' => '0.00',
			'ITEMAMT' => $itemCost,
			'AMT' => $itemCost,
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
}
