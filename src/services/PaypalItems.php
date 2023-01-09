<?php

namespace appliedart\paypaltransparentredirect\services;

use Cake\Utility\Hash;
use Exception;
use Craft;
use craft\base\Component;
use craft\db\Query;
use appliedart\paypaltransparentredirect\models\PaypalItem;
use appliedart\paypaltransparentredirect\records\PaypalItemRecord;
use appliedart\paypaltransparentredirect\events\PaypalItemEvent;
use craft\helpers\Json;

/**
 *
 * @property-read mixed $totalItems
 */
class PaypalItems extends Component {
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private $_overrides = [];

    // Constants
    // =========================================================================

    const EVENT_BEFORE_SAVE_ITEM = 'onBeforeSaveItem';
    const EVENT_AFTER_SAVE_ITEM = 'onAfterSaveItem';


    // Public Methods
    // =========================================================================

    /**
     * @param null $orderBy
     * @return array|\yii\db\ActiveRecord[]
     */
    public function getItems($orderBy = null) {
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
    public function getItemIds($orderBy = null) {
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
    public function getTotalItems()
    {
        return count($this->getItems());
    }

    /**
     * @param $itemId
     * @return PaypalItem|null
     */
    public function getItemById($itemId) {
        $result = $this->_getQuery()
            ->where(['id' => $itemId])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param $identifier
     * @return PaypalItem|null
     */
    public function getItemByIdentifier($identifier) {
        $result = $this->_getQuery()
            ->where(['identifier' => $identifier])
            ->one();

        return $this->_createModelFromRecord($result);
    }

    /**
     * @param PaypalItem $model
     * @param bool $runValidation
     * @return bool
     */
    public function saveItem(PaypalItem $model, bool $runValidation = true): bool {
        $isNewModel = !$model->id;

        // Fire a 'beforeSaveItem' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_ITEM)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_ITEM, new PaypalItemEvent([
                'item' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Item not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewModel) {
            $record = new PaypalItemRecord();
            // $exists = $this->getItemByIdentifier(trim($model->identifier));
        } else {
            $record = PaypalItemRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No item exists with the ID “{id}”', ['id' => $model->id]));
            }
        }

        $record->name = trim($model->name);
        $record->identifier = trim($model->identifier);
        $record->cost = trim($model->cost);
        $record->gratisCount = trim($model->gratisCount);
        $record->gratisItem = trim($model->gratisItem);
        $record->gratisTitle = trim($model->gratisTitle);
        $record->gratisDescription = trim($model->gratisDescription);

        if ($isNewModel) {
            $maxSortOrder = (new Query())
                ->from(['{{%paypaltransparentredirect_item}}'])
                ->max('[[sortOrder]]');

            $record->sortOrder = $maxSortOrder ? $maxSortOrder + 1 : 1;
        }

        $record->save(false);

        if (!$model->id) {
            $model->id = $record->id;
        }

        // Fire an 'afterSaveItem' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_ITEM)) {
            $this->trigger(self::EVENT_AFTER_SAVE_ITEM, new PaypalItemEvent([
                'item' => $model,
                'isNew' => $isNewModel,
            ]));
        }

        return true;
    }

    /**
     * @param $itemId
     * @return int
     * @throws \yii\db\Exception
     */
    public function deleteItemById($itemId)
    {
        return Craft::$app->getDb()->createCommand()
            ->delete('{{%paypaltransparentredirect_item}}', ['id' => $itemId])
            ->execute();
    }

    /**
     * @param array $itemsIds
     * @return bool
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    public function reorderItems(array $itemIds): bool {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            foreach ($itemIds as $itemOrder => $itemId) {
                $itemRecord = $this->_getItemRecordById($itemId);
                $itemRecord->sortOrder = $itemOrder + 1;
                $itemRecord->save();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    /**
     * @return \craft\db\ActiveQuery
     */
    private function _getQuery() {
        return PaypalItemRecord::find()->select([
            'id',
            'name',
            'identifier',
            'cost',
            'gratisCount',
            'gratisItem',
            'gratisTitle',
            'gratisDescription',
            // 'siteId',
            'sortOrder',
            'dateCreated',
            'dateUpdated',
            'uid',
        ])->orderBy([
            'sortOrder' => SORT_ASC,
            'cost' => SORT_ASC,
            'identifier' => SORT_ASC,
            'name' => SORT_ASC,
            'dateCreated' => SORT_DESC,
            'id' => SORT_ASC
        ]);
    }

    /**
     * @param PaypalItemRecord|null $record
     * @return PaypalItem|null
     */
    private function _createModelFromRecord(PaypalItemRecord $record = null): PaypalItem {
        if (!$record) {
            return null;
        }

        $attributes = $record->toArray();

        return new PaypalItem($attributes);
    }

    /**
     * @param int|null $itemId
     * @return PaypalItemRecord
     */
    private function _getItemRecordById(int $itemId = null): PaypalItemRecord {
        if ($itemId !== null) {
            $itemRecord = PaypalItemRecord::findOne(['id' => $itemId]);

            if (!$itemRecord) {
                throw new Exception(Craft::t('paypal-transparent-redirect', 'No item exists with the ID “{id}”.', ['id' => $itemId]));
            }
        } else {
            $itemRecord = new PayPalItemRecord();
        }

        return $itemRecord;
    }
}
