<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221019_000000_sortOrder extends Migration {
	public function safeUp() {
		if (!$this->db->columnExists('{{%paypaltransparentredirect_item}}', 'sortOrder')) {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'sortOrder', $this->smallInteger()->unsigned()->after('cost'));

			$items = (new Query())
				->select(['*'])
				->from(['{{%paypaltransparentredirect_item}}'])
				->all();

			foreach ($items as $i => $item) {
				$this->update('{{%paypaltransparentredirect_item}}', ['sortOrder' => $i + 1], ['id' => $item['id']], [], false);
			}
		}

		return true;
	}

	public function safeDown() {
		echo "m221019_000000_sortOrder cannot be reverted.\n";

		return false;
	}
}
