<?php

namespace appliedart\paypaltransparentredirect\events;

use yii\base\Event;

class PaypalItemEvent extends Event {
    // Properties
    // =========================================================================

    /**
     * @var
     */
    public $item;

    /**
     * @var bool
     */
    public $isNew = false;
}
