<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;

class TransactionTracker extends Component
{
    public function log(
        Order $order,
        string $gateway,
        string $transactionType,
        float $amount,
        string $currency,
        string $status,
        ?string $gatewayReference = null,
        ?array $requestData = null,
        ?array $responseData = null,
        ?string $errorMessage = null
    ): int {
        $this->ensureTable();

        $request = Craft::$app->getRequest();

        return (int)Craft::$app->getDb()->createCommand()
            ->insert('{{%transaction_logs}}', [
                'orderId' => $order->id,
                'gateway' => $gateway,
                'transactionType' => $transactionType,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'gatewayReference' => $gatewayReference,
                'requestData' => $requestData ? Json::encode($requestData) : null,
                'responseData' => $responseData ? Json::encode($responseData) : null,
                'errorMessage' => $errorMessage,
                'ipAddress' => $request->getUserIP(),
                'userAgent' => substr((string)$request->getUserAgent(), 0, 255),
                'dateCreated' => Db::prepareDateForDb(new \DateTime()),
            ])
            ->execute();
    }

    public function trackIncompleteSubmission(Order $order, array $formData, string $step): void
    {
        $this->log(
            $order,
            'form',
            'incomplete_' . $step,
            0,
            $order->currency ?? 'ZAR',
            'incomplete',
            null,
            $formData
        );
    }

    public function getOrderTransactions(int $orderId): array
    {
        $this->ensureTable();

        return (new Query())
            ->select('*')
            ->from('{{%transaction_logs}}')
            ->where(['orderId' => $orderId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
    }

    public function getAbandonedCarts(int $hours = 24): array
    {
        return (new Query())
            ->select('*')
            ->from('{{%commerce_orders}}')
            ->where([
                'isCompleted' => false,
                'datePaid' => null,
            ])
            ->andWhere(['<', 'dateUpdated', Db::prepareDateForDb(new \DateTime("-{$hours} hours"))])
            ->all();
    }

    private function ensureTable(): void
    {
        if (Craft::$app->getDb()->tableExists('{{%transaction_logs}}')) {
            return;
        }

        $migration = new \modules\localroots\migrations\m250908_150000_create_transaction_logs_table();
        $migration->safeUp();
    }
}
