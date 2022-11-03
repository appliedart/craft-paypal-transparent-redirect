<?php

namespace appliedart\paypaltransparentredirect\migrations;

use craft\db\Migration;
use craft\db\Query;

class m221101_000000_identifierLen extends Migration {
	public function safeUp() {
		if ($this->db->columnExists('{{%paypaltransparentredirect_item}}', 'identifier')) {
			$this->fixIdentifiers();
			$this->alterColumn('{{%paypaltransparentredirect_item}}', 'identifier', $this->string(21)->notNull());
		} else {
			$this->addColumn('{{%paypaltransparentredirect_item}}', 'identifier', $this->string(21)->null()->defaultValue(''));
			$this->fixIdentifiers();
			$this->alterColumn('{{%paypaltransparentredirect_item}}', 'identifier', $this->string(21)->notNull());
		}

		return true;
	}

	public function safeDown() {
		echo "m221101_000000_identifierLen cannot be reverted.\n";

		return false;
	}

	protected function fixIdentifiers() {
		$used = [];

		$items = (new Query())
			->select(['*'])
			->from(['{{%paypaltransparentredirect_item}}'])
			->all();

		foreach ($items as $i => $item) {
			$identifier = substr($item['identifier'], 0, 19);
			while (in_array($identifier, $used)) {
				$identifier = substr($identifier, 0, 21) . uniqid();
			}
			$this->update('{{%paypaltransparentredirect_item}}', ['identifier' => $identifier], ['id' => $item['id']], [], false);
			$used[] = $identifier;
		}
	}
}
