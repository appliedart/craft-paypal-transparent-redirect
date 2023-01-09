<?php

namespace appliedart\paypaltransparentredirect\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Json;

class m230105_000000_paypalTokens extends Migration {
	public function safeUp() {
		if (!$this->db->columnExists('{{%paypaltransparentredirect_trxresponse}}', 'isComplete')) {
			$this->addColumn('{{%paypaltransparentredirect_trxresponse}}', 'isComplete', $this->boolean()->notNull()->defaultValue(FALSE)->after('uid'));
		}

		$result = Install::createTokenTable($this);

		if ($result) {
			$this->createTokenRecords();
		}

		return $result;
	}

	public function safeDown() {
		echo "m230105_000000_paypalTokens cannot be reverted.\n";

		return false;
	}

	protected function createTokenRecords() {
		$transactions = (new Query())->select(['*'])->from(['{{%paypaltransparentredirect_trxresponse}}'])->all();

		foreach ($transactions as $transaction) {
			/** @var User $guest */
			$guest = NULL;
			$guestData = NULL;

			$item = (new Query())->select(['*'])->from(['{{%paypaltransparentredirect_item}}'])->where(['cost' => $transaction['AMT']])->one();

			$responseField = Craft::$app->getFields()->getFieldByHandle('paypalResponses');
			$responseColumnName = ElementHelper::fieldColumn($responseField->columnPrefix, 'paypalResponses', $responseField->columnSuffix);
			$userQuery = User::find()->anyStatus()->with(['associatedMembers']);
			$userQuery->andWhere(['REGEXP', $responseColumnName, '\\b' . $transaction['id'] . '\\b']);
			// echo $userQuery->rawSql . PHP_EOL . PHP_EOL;
			$user = $userQuery->one();

			// var_dump($user->associatedMembers);

			if (isset($user->associatedMembers[0]->linkedRole) && $user->associatedMembers[0]->linkedRole == 'guest') {
				$guest = $user->associatedMembers[0]->linkedUser ? $user->associatedMembers[0]->linkedUser->one() : NULL;
				$guestData = $guest ? $guest->getFieldValue('guestData') : NULL;
				$guestData = Json::decodeIfJson($guestData);
				// var_dump($guestData);
			}

			$recordData = [
				'secureToken' => $transaction['SECURETOKEN'],
				'secureTokenId' => $transaction['SECURETOKENID'],
				'lastTransactionId' => $transaction['id'],
				'itemId' => $item ? $item['id'] : NULL,
				'userId' => $user ? $user->id : NULL,
				'userEmail' => $user ? $user->email : NULL,
				'userData' => $user ? json_encode([
					'guestMemberData' => isset($guestData[0]) ? [$guestData[0]] : NULL,
					'membershipDonationData' => isset($user->donationData) && !empty($user->donationData) ? $user->donationData[array_key_last($user->donationData)] : NULL,
					'acceptThankYouGift' => NULL,
					'doGratisMembership' => isset($guestData[0]) ? 'YES' : 'NO',
				]) : NULL
			];

			if (!$user && $transaction['id'] == 3 && $transaction['TXID'] == '06E285999V311154B') {
				$tmpUser = User::find()->id(35776)->one();
				$recordData['userId'] = $tmpUser ? $tmpUser->id : NULL;
				$recordData['userEmail'] = $tmpUser ? $tmpUser->email : NULL;
			}

			Db::insert('{{%paypaltransparentredirect_token}}', $recordData);

			if ($user) {
				$this->update('{{%paypaltransparentredirect_trxresponse}}', ['isComplete' => TRUE], ['id' => $transaction['id']], [], false);
			}
		}
	}
}
