<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\elements\Entry;

class OrderSyncService extends Component
{
    public function syncOrder(Order $order): void
    {
        $section = Craft::$app->entries->getSectionByHandle('orders');
        if (!$section) {
            return;
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            return;
        }

        $existing = Entry::find()
            ->section('orders')
            ->orderNumber($order->reference ?? (string)$order->id)
            ->one();

        $entry = $existing ?? new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $entryType->id;
        $entry->title = 'Order ' . ($order->reference ?? $order->id);
        $entry->enabled = true;

        if ($entry->getFieldLayout()->getFieldByHandle('orderNumber')) {
            $entry->setFieldValue('orderNumber', $order->reference ?? (string)$order->id);
        }
        if ($entry->getFieldLayout()->getFieldByHandle('customerInfo')) {
            $entry->setFieldValue('customerInfo', json_encode([
                'email' => $order->email,
                'name' => trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? '')),
            ], JSON_PRETTY_PRINT));
        }
        if ($entry->getFieldLayout()->getFieldByHandle('lineItems')) {
            $items = [];
            foreach ($order->getLineItems() as $lineItem) {
                $items[] = [
                    'sku' => $lineItem->sku,
                    'description' => $lineItem->description,
                    'qty' => $lineItem->qty,
                    'price' => $lineItem->price,
                    'total' => $lineItem->total,
                ];
            }
            $entry->setFieldValue('lineItems', json_encode($items, JSON_PRETTY_PRINT));
        }
        if ($entry->getFieldLayout()->getFieldByHandle('totalPrice')) {
            $entry->setFieldValue('totalPrice', (float)$order->totalPrice);
        }
        if ($entry->getFieldLayout()->getFieldByHandle('paymentStatus')) {
            $entry->setFieldValue('paymentStatus', $order->isPaid ? 'paid' : 'unpaid');
        }
        if ($entry->getFieldLayout()->getFieldByHandle('orderStatus')) {
            $status = 'incomplete';
            if ($order->isCompleted) {
                $status = 'complete';
            } elseif ($order->isPaid) {
                $status = 'paid';
            } elseif ($order->getTotalQty() > 0) {
                $status = 'cart';
            }
            $entry->setFieldValue('orderStatus', $status);
        }

        Craft::$app->getElements()->saveElement($entry, false);
    }
}
