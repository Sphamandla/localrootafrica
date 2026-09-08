<?php

namespace modules\localroots\migrations;

use craft\db\Migration;

class m250908_150000_create_transaction_logs_table extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%transaction_logs}}')) {
            return true;
        }

        $this->createTable('{{%transaction_logs}}', [
            'id' => $this->primaryKey(),
            'orderId' => $this->integer()->notNull(),
            'gateway' => $this->string(50)->notNull(),
            'transactionType' => $this->string(50)->notNull(),
            'amount' => $this->decimal(10, 2)->notNull()->defaultValue(0),
            'currency' => $this->string(3)->notNull()->defaultValue('ZAR'),
            'status' => $this->string(50)->notNull(),
            'gatewayReference' => $this->string(255),
            'requestData' => $this->text(),
            'responseData' => $this->text(),
            'errorMessage' => $this->text(),
            'ipAddress' => $this->string(45),
            'userAgent' => $this->string(255),
            'dateCreated' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(null, '{{%transaction_logs}}', ['orderId']);
        $this->createIndex(null, '{{%transaction_logs}}', ['gateway']);
        $this->createIndex(null, '{{%transaction_logs}}', ['status']);

        if ($this->db->tableExists('{{%commerce_orders}}')) {
            $this->addForeignKey(
                null,
                '{{%transaction_logs}}',
                'orderId',
                '{{%commerce_orders}}',
                'id',
                'CASCADE',
                null
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%transaction_logs}}');
        return true;
    }
}
