<?php

namespace appliedart\paypaltransparentredirect\services;

use Cake\Utility\Hash;
use Exception;
use Craft;
use craft\base\Component;
use craft\db\Query;
use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\models\TransactionResponse;
use appliedart\paypaltransparentredirect\records\TransactionResponseRecord;
use appliedart\paypaltransparentredirect\events\TransactionResponseEvent;
use craft\helpers\Json;

/**
 *
 * @property-read mixed $totalResponses
 */
class TransactionResponses extends Component {
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private $_overrides = [];

    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_RESPONSE = 'onBeforeSaveResponse';
    const EVENT_AFTER_SAVE_RESPONSE = 'onAfterSaveResponse';

    public static function getResponseFieldNames() {
        return [
            'PNREF',
            'PPREF',
            'RESULT',
            'CVV2MATCH',
            'RESPMSG',

            'AUTHCODE',
            'AVSADDR',
            'AVSZIP',
            'IAVS',
            'PROCAVS',
            'PROCCVV2',

            'HOSTCODE',
            'RESPTEXT',
            'PROCCARDSECURE',
            'ADDLMSGS',
            'PAYMENTTYPE',
            'CORRELATIONID',
            'AMEXID',
            'AMEXPOSDATA',
            'CCTRANSID',
            'CCTRANS_POSDATA',
            'AMT',
            'ORIGAMT',
            'CARDTYPE',
            'EMAILMATCH',
            'PHONEMATCH',
            'EXTRSPMSG',

            'TRANSTIME',
            'DUPLICATE',
            'DATE_TO_SETTLE',
            'PAYMENTADVICECODE',
            'TRANSSTATE',
            'TENDER',
            'RECURRING',
            'ORDERID',
            'COMMENT1',
            'COMMENT2',
            'CURRENCY',
            'CUSTCODE',
            'CUSTREF',
            'EMAIL',
            'INVNUM',
            'PONUM',

            'SECURETOKEN',
            'SECURETOKENID',
            'TXID',
            'TRXTYPE',
            'ACCT',
            'EXPDATE',
            'AVSDATA',

            'NAME',
            'FIRSTNAME',
            'MIDDLENAME',
            'LASTNAME',
            'COMPANY',
            'ADDRESS',
            'ADDRESS2',
            'STREET',
            'STREET2',
            'CITY',
            'STATE',
            'ZIP',
            'COUNTRY',

            'BILLTONAME',
            'BILLTOFIRSTNAME',
            'BILLTOMIDDLENAME',
            'BILLTOLASTNAME',
            'BILLTOCOMPANY',
            'BILLTOSTREET',
            'BILLTOSTREET2',
            'BILLTOCITY',
            'BILLTOSTATE',
            'BILLTOZIP',
            'BILLTOCOUNTRY',
            'BILLTOEMAIL',
            'BILLTOPHONENUM',

            //'SHIPTONAME',
            //'SHIPTOFIRSTNAME',
            //'SHIPTOMIDDLENAME',
            //'SHIPTOLASTNAME',
            //'SHIPTOCOMPANY',
            //'SHIPTOSTREET',
            //'SHIPTOSTREET2',
            //'SHIPTOCITY',
            //'SHIPTOSTATE',
            //'SHIPTOZIP',
            //'SHIPTOCOUNTRY',

            'USER1',
            'USER2',
            'USER3',
            'USER4',
            'USER5',
            'USER6',
            'USER7',
            'USER8',
            'USER9',
            'USER10',

        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * @param null $orderBy
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getResponses($orderBy = null) {
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
    public function getResponseIds($orderBy = null) {
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
    public function getTotalResponses()
    {
        return count($this->getResponses());
    }

    /**
     * @param $responseId
     * @return TransactionResponse|null
     */
    public function getResponseById($responseId) {
        $result = $this->_getQuery()
            ->where(['id' => $responseId])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param TransactionResponse $model
     * @param bool $runValidation
     * @return bool
     */
    public function saveResponse(TransactionResponse $model, bool $runValidation = true): bool {
        $isNewModel = !$model->id;

        // Fire a 'beforeSaveResponse' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_RESPONSE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_RESPONSE, new TransactionResponseEvent([
                'response' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Response not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewModel) {
            $record = new TransactionResponseRecord();
        } else {
            $record = TransactionResponseRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No response exists with the ID “{id}”', ['id' => $model->id]));
            }

        }

        $fieldNames = self::getResponseFieldNames();

        $record->isComplete = $model->isComplete;
        $record->fullResponse = $model->fullResponse;

        foreach ($fieldNames as $fieldName) {
            $record->{$fieldName} = trim($model->{$fieldName});
        }

        $record->save(false);

        if (!$model->id) {
            $model->id = $record->id;
        }

        // Fire an 'afterSaveResponse' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_RESPONSE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_RESPONSE, new TransactionResponseEvent([
                'response' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        return true;
    }

    /**
     * @param $responseId
     * @return int
     * @throws \yii\db\Exception
     */
    public function deleteResponseById($responseId)
    {
        return Craft::$app->getDb()->createCommand()
            ->delete('{{%paypaltransparentredirect_trxresponse}}', ['id' => $responseId])
            ->execute();
    }


    // Private Methods
    // =========================================================================

    /**
     * @return \craft\db\ActiveQuery
     */
    private function _getQuery() {
        $fieldNames = array_merge([
            'id',
            'dateCreated',
            'dateUpdated',
            'uid',
            'isComplete',
            'fullResponse',
        ], self::getResponseFieldNames());

        return TransactionResponseRecord::find()
            ->select($fieldNames)
            ->orderBy(['dateCreated' => SORT_DESC,'dateUpdated' => SORT_DESC, 'id' => SORT_DESC]);
    }

    /**
     * @param TransactionResponseRecord|null $record
     * @return TransactionResponse|null
     */
    private function _createModelFromRecord(TransactionResponseRecord $record = null): TransactionResponse {
        if (!$record) {
            return null;
        }

        $attributes = $record->toArray();

        return new TransactionResponse($attributes);
    }

    /**
     * @param int|null $responseId
     * @return TransactionResponseRecord
     */
    private function _getResponseRecordById(int $responseId = null): TransactionResponseRecord {
        if ($responseId !== null) {
            $responseRecord = TransactionResponseRecord::findOne(['id' => $responseId]);

            if (!$responseRecord) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No response exists with the ID “{id}”.', ['id' => $responseId]));
            }
        } else {
            $responseRecord = new TransactionResponseRecord();
        }

        return $responseRecord;
    }

}
