<?php

namespace appliedart\paypaltransparentredirect\events;

use yii\base\Event;

class PaypalTokenEvent extends Event {
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $token;

    /**
     * @var bool
     */
    public $isNew = false;
}
