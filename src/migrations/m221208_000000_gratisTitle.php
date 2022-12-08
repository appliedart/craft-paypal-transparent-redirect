<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221208_000000_gratisTitle extends Migration {
	public function safeUp() {
		if (!$this->db->columnExists('{{%paypaltransparentredirect_item}}', 'gratisTitle')) {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'gratisTitle', $this->string(96)->null()->defaultValue(NULL)->after('gratisItem'));
		}

		return true;
	}

	public function safeDown() {
		echo "m221208_000000_gratisTitle cannot be reverted.\n";

		return false;
	}
}
