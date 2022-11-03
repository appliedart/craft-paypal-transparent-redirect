<?php

namespace appliedart\paypaltransparentredirect\events;

use yii\base\Event;

class TransactionResponseEvent extends Event {
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $response;

    /**
     * @var bool
     */
    public $isNew = false;
}
