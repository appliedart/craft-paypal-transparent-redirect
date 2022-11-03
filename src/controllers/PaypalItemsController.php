<?php

namespace appliedart\paypaltransparentredirect\controllers;

use Cake\Utility\Hash;
use Craft;
use appliedart\paypaltransparentredirect\models\PaypalItemModel;
use appliedart\paypaltransparentredirect\Plugin;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\helpers\StringHelper;
use craft\web\Controller;

/**
 * @property Plugin $module
 */
class PaypalItemsController extends Controller {
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
    public function actionItemsIndex() {
        $variables['items'] = Plugin::$plugin->items->getItems();

        return $this->renderTemplate('paypal-transparent-redirect/items/index', $variables);
    }

    /**
     * @param null $itemId
     * @param null $item
     * @return \yii\web\Response
     */
    public function actionEditItem($itemId = null, $item = null) {
        $variables = [];

        if (!$item) {
            if ($itemId) {
                $variables['item'] = Plugin::$plugin->items->getItemById($itemId);
            } else {
                $variables['item'] = new PayPalItemModel();
            }
        } else {
            $variables['item'] = $item;
        }

        return $this->renderTemplate('paypal-transparent-redirect/items/edit', $variables);
    }

    /**
     * @return \yii\web\Response|null
     */
    public function actionSaveItem() {
        $item = $this->_getModelFromPost();

        return $this->_saveAndRedirect($item, 'paypal-transparent-redirect/items/', FALSE);
    }

    /**
     * @return \yii\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionDeleteItem() {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $itemId = $request->getRequiredBodyParam('id');

        Plugin::$plugin->items->deleteItemById($itemId);

        return $this->asJson(['success' => true]);
    }

    /**
     * @return \yii\web\Response
     * @throws \Throwable
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionReorderItems()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $itemIds = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));
        $itemIds = array_filter($itemIds);
        Plugin::$plugin->items->reorderItems($itemIds);

        return $this->asJson(['success' => true]);
    }


    // Private Methods
    // =========================================================================

    /**
     * @param $item
     * @param $redirect
     * @param false $withId
     * @return \yii\web\Response|null
     * @throws \craft\errors\MissingComponentException
     */
    private function _saveAndRedirect($item, $redirect, $withId = false)
    {
        if (!Plugin::$plugin->items->saveItem($item)) {
            Craft::$app->getSession()->setError(Craft::t('paypal-transparent-redirect', 'Unable to save item.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'item' => $item,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('paypal-transparent-redirect', 'Item saved.'));

        if ($withId) {
            $redirect .= $item->id;
        }

        return $this->redirect($redirect);
    }

    /**
     * @return PaypalItemModel
     * @throws \yii\web\BadRequestHttpException
     */
    protected function _getModelFromPost() {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        if ($request->getBodyParam('itemId')) {
            $item = Plugin::$plugin->items->getItemById($request->getBodyParam('itemId'));
        } else {
            $item = new PaypalItemModel();
        }

        $item->name = $request->getBodyParam('name', $item->name);
        $item->identifier = $request->getBodyParam('identifier', $item->identifier);
        $item->cost = $request->getBodyParam('cost', $item->cost);
        $item->gratisCount = $request->getBodyParam('gratisCount', $item->gratisCount);
        // $item->siteId = $request->getBodyParam('siteId', $item->siteId);

        return $item;
    }
}
