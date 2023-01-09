<?php

namespace appliedart\paypaltransparentredirect\services;

use Cake\Utility\Hash;
use Exception;
use Craft;
use craft\base\Component;
use craft\db\Query;
use appliedart\paypaltransparentredirect\models\PaypalToken;
use appliedart\paypaltransparentredirect\records\PaypalTokenRecord;
use appliedart\paypaltransparentredirect\events\PaypalTokenEvent;
use craft\helpers\Json;

/**
 *
 * @property-read mixed $totalTokens
 */
class PaypalTokens extends Component {
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private $_overrides = [];

    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_TOKEN = 'onBeforeSaveToken';
    const EVENT_AFTER_SAVE_TOKEN = 'onAfterSaveToken';


    // Public Methods
    // =========================================================================

    /**
     * @param null $orderBy
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getTokens($orderBy = null) {
        $query = $this->_getQuery();

        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        $results = $query->all();

        foreach ($results as $key => $result) {
            $results[$key] = $this->_createModelFromRecord($result);
        }

        return $results;
    }

    /**
     * @param null $orderBy
     * @return array
     */
    public function getTokenIds($orderBy = null) {
        $query = $this->_getQuery();

        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        $results = $query->all();

        foreach ($results as $key => $result) {
            $results[$key] = intval($result->id);
        }

        return $results;
    }

    /**
     * @return int
     */
    public function getTotalTokens()
    {
        return count($this->getTokens());
    }

    /**
     * @param $tokenId
     * @return PaypalToken|null
     */
    public function getTokenById($tokenId) {
        $result = $this->_getQuery()
            ->where(['id' => $tokenId])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param $secureToken
     * @param $secureTokenId
     * @return PaypalToken|null
     */
    public function getTokenBySecureToken($secureToken, $secureTokenId)
    {
        $query = $this->_getQuery()->orderBy(['dateCreated' => SORT_DESC]);
        $where = [];

        if ($secureToken) {
            $where['secureToken'] = $secureToken;
        }

        if ($secureTokenId) {
            $where['secureTokenId'] = $secureTokenId;
        }

        if (empty($where)) {
            return NULL;
        }

        $query->where($where);
        $result = $query->one();

        if ($result) {
            return $this->_createModelFromRecord($result);
        }

        return NULL;
    }

    /**
     * @param PaypalToken $model
     * @param bool $runValidation
     * @return bool
     */
    public function saveToken(PaypalToken &$model, bool $runValidation = true): bool {
        if (!$model->id && $record = $this->getTokenBySecureToken($model->secureToken, $model->secureTokenId)) {
            $model->id = $record->id;
        }

        $isNewModel = !$model->id;

        // Fire a 'beforeSaveToken' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_TOKEN)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_TOKEN, new PaypalTokenEvent([
                'token' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Token not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewModel) {
            $record = new PaypalTokenRecord();
        } else {
            $record = PaypalTokenRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No token exists with the ID “{id}”', ['id' => $model->id]));
            }
        }

        $record->secureToken = trim($model->secureToken);
        $record->secureTokenId = trim($model->secureTokenId);
        $record->lastTransactionId = $model->lastTransactionId;
        $record->itemId = $model->itemId;
        $record->userId = $model->userId;
        $record->userEmail = trim($model->userEmail);
        $record->userData = trim($model->userData);

        $record->save(false);

        if (!$model->id) {
            $model->id = $record->id;
        }

        // Fire an 'afterSaveToken' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_TOKEN)) {
            $this->trigger(self::EVENT_AFTER_SAVE_TOKEN, new PaypalTokenEvent([
                'token' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        return true;
    }

    /**
     * @param $tokenId
     * @return int
     * @throws \yii\db\Exception
     */
    public function deleteTokenById($tokenId)
    {
        return Craft::$app->getDb()->createCommand()
            ->delete('{{%paypaltransparentredirect_token}}', ['id' => $tokenId])
            ->execute();
    }

    // Private Methods
    // =========================================================================

    /**
     * @return \craft\db\ActiveQuery
     */
    private function _getQuery() {
        return PaypalTokenRecord::find()->select([
            'id',
            'secureToken',
            'secureTokenId',
            'lastTransactionId',
            'itemId',
            'userId',
            'userEmail',
            'userData',
            // 'siteId',
            'dateCreated',
            'dateUpdated',
            'uid',
        ])->orderBy([
            'dateCreated' => SORT_DESC,
            'id' => SORT_ASC
        ]);
    }

    /**
     * @param PaypalTokenRecord|null $record
     * @return PaypalToken|null
     */
    private function _createModelFromRecord(PaypalTokenRecord $record = null): PaypalToken {
        if (!$record) {
            return null;
        }

        $attributes = $record->toArray();

        return new PaypalToken($attributes);
    }

    /**
     * @param int|null $tokenId
     * @return PaypalTokenRecord
     */
    private function _getTokenRecordById(int $tokenId = null): PaypalTokenRecord {
        if ($tokenId !== null) {
            $tokenRecord = PaypalTokenRecord::findOne(['id' => $tokenId]);

            if (!$tokenRecord) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No token exists with the ID “{id}”.', ['id' => $tokenId]));
            }
        } else {
            $tokenRecord = new PaypalTokenRecord();
        }

        return $tokenRecord;
    }
}
