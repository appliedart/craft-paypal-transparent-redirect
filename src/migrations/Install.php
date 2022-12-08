<?php
/**
 * PayPal Transparent Redirect plugin for Craft CMS 3.x
 *
 * Basic support for PayPal/Payflow Pro Transparent Redirect (SILENTTRAN).
 *
 * @link      https://www.appliedart.com
 * @copyright Copyright (c) 2022 Applied Art & Technology
 */

namespace appliedart\paypaltransparentredirect\migrations;

use appliedart\paypaltransparentredirect\Plugin;
use appliedart\paypaltransparentredirect\services\TransactionResponses;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * PayPal Transparent Redirect Install Migration
 *
 * If your plugin needs to create any custom database tables when it gets installed,
 * create a migrations/ folder within your plugin folder, and save an Install.php file
 * within it using the following template:
 *
 * If you need to perform any additional actions on install/uninstall, override the
 * safeUp() and safeDown() methods.
 *
 * @author    Applied Art & Technology
 * @package   PaypalTransparentRedirect
 * @since     0.1.0
 */
class Install extends Migration {
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * This method contains the logic to be executed when applying this migration.
     * This method differs from [[up()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[up()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeUp() {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            // $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
            $this->insertDefaultData();
        }

        return true;
    }

    /**
     * This method contains the logic to be executed when removing this migration.
     * This method differs from [[down()]] in that the DB logic implemented here will
     * be enclosed within a DB transaction.
     * Child classes may implement this method instead of [[down()]] if the DB logic
     * needs to be within a transaction.
     *
     * @return boolean return a false value to indicate the migration fails
     * and should not proceed further. All other return values mean the migration succeeds.
     */
    public function safeDown() {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected function createTables() {
        // $this->removeTables();

        $tablesCreated = (self::createItemsTable($this) && self::createTrxTable($this));

        return $tablesCreated;
    }

    protected static function getTrxFields($self) {
        $fieldNames = TransactionResponses::getResponseFieldNames();

        $fields = [];

        foreach($fieldNames as $fieldName) {
            $fields[$fieldName] = $self->string(112)->null();
        }

        return $fields;
    }

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    public static function createTrxTable($self) {
        $tableCreated = false;

        // paypaltransparentredirect_item table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%paypaltransparentredirect_trxresponse}}');
        if ($tableSchema === null) {
            $self->createTable(
                '{{%paypaltransparentredirect_trxresponse}}',
                array_merge([
                    'id' => $self->primaryKey(),
                    'dateCreated' => $self->dateTime()->notNull(),
                    'dateUpdated' => $self->dateTime()->notNull(),
                    'uid' => $self->uid(),
                    // Custom columns in the table
                    'fullResponse' => $self->json(),
                ], self::getTrxFields($self))
            );

            $tableCreated = true;
        }

        return $tableCreated;
    }

    /**
     * Creates the tables needed for the Records used by the plugin
     *
     * @return bool
     */
    protected static function createItemsTable($self) {
        $tableCreated = false;

        // paypaltransparentredirect_item table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%paypaltransparentredirect_item}}');
        if ($tableSchema === null) {
            $self->createTable(
                '{{%paypaltransparentredirect_item}}',
                [
                    'id' => $self->primaryKey(),
                    'dateCreated' => $self->dateTime()->notNull(),
                    'dateUpdated' => $self->dateTime()->notNull(),
                    'uid' => $self->uid(),
                    // Custom columns in the table
                    'name' => $self->string(255)->notNull(),
                    'identifier' => $self->string(21)->notNull(),
                    'cost' => $self->decimal(16, 2),
                    'gratisCount' => $self->smallInteger()->unsigned()->notNull()->defaultValue(0),
                    'gratisItem' => $self->smallInteger()->unsigned()->null()->defaultValue(NULL),
                    'gratisTitle' => $self->string(96)->null()->defaultValue(NULL),
                    'gratisDescription' => $self->text()->null()->defaultValue(NULL),
                ]
            );

            $tableCreated = true;
        }

        return $tableCreated;
    }

    /**
     * Creates the indexes needed for the Records used by the plugin
     *
     * @return void
     */
    protected function createIndexes() {
        // paypaltransparentredirect_item table
        $this->createIndex(
            $this->db->getIndexName(
                '{{%paypaltransparentredirect_item}}',
                'identifier',
                true
            ),
            '{{%paypaltransparentredirect_item}}',
            'identifier',
            true
        );
        // Additional commands depending on the db driver
        switch ($this->driver) {
            case DbConfig::DRIVER_MYSQL:
                break;
            case DbConfig::DRIVER_PGSQL:
                break;
        }
    }


    /**
     * Creates the foreign keys needed for the Records used by the plugin
     *
     * @return void
     */
    /* protected function addForeignKeys() {
        // paypaltransparentredirect_item table
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%paypaltransparentredirect_item}}', 'siteId'),
            '{{%paypaltransparentredirect_item}}',
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    } */

    /**
     * Populates the DB with the default data.
     *
     * @return void
     */
    protected function insertDefaultData() {
    }

    /**
     * Removes the tables needed for the Records used by the plugin
     *
     * @return void
     */
    protected function removeTables() {
        // paypaltransparentredirect_item table
        $this->dropTableIfExists('{{%paypaltransparentredirect_item}}');
        $this->dropTableIfExists('{{%paypaltransparentredirect_paypaltransparentredirectrecord}}');
        $this->dropTableIfExists('{{%paypaltransparentredirect_trxresponse}}');
    }
}
