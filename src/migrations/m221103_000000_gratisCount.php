<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221103_000000_gratisCount extends Migration {
	public function safeUp() {
		if (!$this->db->columnExists('{{%paypaltransparentredirect_item}}', 'gratisCount')) {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'gratisCount', $this->smallInteger()->unsigned()->notNull()->defaultValue(0)->after('cost'));
		}

		return true;
	}

	public function safeDown() {
		echo "m221103_000000_gratisCount cannot be reverted.\n";

		return false;
	}
}
