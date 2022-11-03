<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221101_000001_trxresponse extends Migration {
	public function safeUp() {
		return	Install::createTrxTable($this);
	}

	public function safeDown() {
		echo "m221101_000001_trxresponse cannot be reverted.\n";

		return false;
	}
}
