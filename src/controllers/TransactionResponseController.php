<?php

namespace appliedart\paypaltransparentredirect\controllers;

use Cake\Utility\Hash;
use Craft;
use appliedart\paypaltransparentredirect\models\TransactionResponse;
use appliedart\paypaltransparentredirect\Plugin;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\helpers\StringHelper;
use craft\web\Controller;

/**
 * @property Plugin $module
 */
class TransactionResponseController extends Controller {
    // Properties
    // =========================================================================

    /**
     * @var string[]
     */
    protected $allowAnonymous = [];


    // Public Methods
    // =========================================================================

    /**
     * @return \yii\web\Response
     */
    public function actionResponseIndex() {
        $variables['responses'] = Plugin::$plugin->transactions->getResponses();

        return $this->renderTemplate('paypal-transparent-redirect/responses/index', $variables);
    }

    /**
     * @param null $itemId
     * @param null $item
     * @return \yii\web\Response
     */
    public function actionViewResponse($responseId = null, $response = null) {
        $variables = [];

        if (!$response) {
            if ($responseId) {
                $variables['response'] = Plugin::$plugin->transactions->getResponseById($responseId);
            } else {
                $variables['response'] = new TransactionResponse();
            }
        } else {
            $variables['response'] = $response;
        }

        return $this->renderTemplate('paypal-transparent-redirect/responses/view', $variables);
    }

}
