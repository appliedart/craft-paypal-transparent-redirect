<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221130_000000_gratisItemDescription extends Migration {
	public function safeUp() {
		if (!$this->db->columnExists('{{%paypaltransparentredirect_item}}', 'gratisItem')) {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'gratisItem', $this->smallInteger()->unsigned()->null()->defaultValue(NULL)->after('gratisCount'));
		}

		if (!$this->db->columnExists('{{%paypaltransparentredirect_item}}', 'gratisDescription')) {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'gratisDescription', $this->text()->null()->defaultValue(NULL)->after('gratisItem'));
		}

		return true;
	}

	public function safeDown() {
		echo "m221130_000000_gratisItemDescription cannot be reverted.\n";

		return false;
	}
}
