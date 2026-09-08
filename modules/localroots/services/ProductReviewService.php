<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\elements\Entry;
use craft\helpers\UrlHelper;

class ProductReviewService extends Component
{
    private const TOKEN_TTL = 7776000; // 90 days

    public function hasPurchased(int $productId, ?int $userId = null, ?string $email = null): bool
    {
        if (!$userId && !$email) {
            return false;
        }

        $query = Order::find()->isCompleted(true);

        if ($userId) {
            $query->customerId($userId);
        } else {
            $query->email($email);
        }

        foreach ($query->all() as $order) {
            if ($this->orderContainsProduct($order, $productId)) {
                return true;
            }
        }

        return false;
    }

    public function orderContainsProduct(Order $order, int $productId): bool
    {
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            if ($purchasable instanceof Variant && (int) $purchasable->productId === $productId) {
                return true;
            }
        }

        return false;
    }

    public function hasExistingReview(int $productId, ?int $userId = null, ?string $email = null): bool
    {
        $query = Entry::find()
            ->section('productReviews')
            ->reviewProduct($productId);

        if ($userId) {
            $query->authorId($userId);
        } elseif ($email) {
            $query->reviewAuthorEmail($email);
        } else {
            return false;
        }

        return $query->exists();
    }

    /**
     * @return array{count: int, average: float, width: int}
     */
    public function getReviewStats(int $productId): array
    {
        $reviews = Entry::find()
            ->section('productReviews')
            ->reviewProduct($productId)
            ->reviewStatus('approved')
            ->all();

        $count = count($reviews);
        if ($count === 0) {
            return ['count' => 0, 'average' => 0.0, 'width' => 0];
        }

        $total = 0;
        foreach ($reviews as $review) {
            $total += (int) ($review->reviewRating ?? 0);
        }

        $average = round($total / $count, 2);
        $width = (int) round(($average / 5) * 100);

        return ['count' => $count, 'average' => $average, 'width' => $width];
    }

    public function generateReviewToken(Order $order, int $productId): string
    {
        $payload = base64_encode(json_encode([
            'orderId' => (int) $order->id,
            'productId' => $productId,
            'email' => (string) $order->email,
            'exp' => time() + self::TOKEN_TTL,
        ], JSON_THROW_ON_ERROR));

        $sig = hash_hmac('sha256', $payload, $this->getSigningKey());

        return $payload . '.' . $sig;
    }

    /**
     * @return array{orderId: int, productId: int, email: string}|null
     */
    public function validateToken(string $token, int $productId): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $sig] = $parts;
        if (!hash_equals(hash_hmac('sha256', $payload, $this->getSigningKey()), $sig)) {
            return null;
        }

        try {
            $data = json_decode(base64_decode($payload, true) ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (
            !is_array($data)
            || (int) ($data['productId'] ?? 0) !== $productId
            || empty($data['email'])
            || (int) ($data['exp'] ?? 0) < time()
        ) {
            return null;
        }

        $order = Order::find()->id((int) ($data['orderId'] ?? 0))->isCompleted(true)->one();
        if (!$order || !$this->orderContainsProduct($order, $productId)) {
            return null;
        }

        if (strcasecmp((string) $order->email, (string) $data['email']) !== 0) {
            return null;
        }

        return [
            'orderId' => (int) $order->id,
            'productId' => $productId,
            'email' => (string) $data['email'],
        ];
    }

    public function buildReviewUrl(string $productUrl, string $token): string
    {
        return UrlHelper::url($productUrl, ['review' => $token]);
    }

    public function sendReviewInvites(Order $order): bool
    {
        $email = $order->email;
        if (!$email || !$order->isCompleted) {
            return false;
        }

        $productIds = [];
        foreach ($order->getLineItems() as $lineItem) {
            $purchasable = $lineItem->getPurchasable();
            if ($purchasable instanceof Variant && $purchasable->productId) {
                $productIds[(int) $purchasable->productId] = true;
            }
        }

        if ($productIds === []) {
            return false;
        }

        $reference = $order->reference ?? (string) $order->id;
        $name = trim(($order->billingAddress?->firstName ?? '') . ' ' . ($order->billingAddress?->lastName ?? ''));

        $lines = [
            'Hi ' . ($name !== '' ? $name : 'there') . ',',
            '',
            'Thank you for your order ' . $reference . '. We hope you enjoy your purchase!',
            '',
            'We would love to hear what you think. Please leave a review for the items you bought:',
            '',
        ];

        foreach (array_keys($productIds) as $productId) {
            $product = Craft::$app->getElements()->getElementById($productId);
            if (!$product || !$product->url) {
                continue;
            }

            if ($this->hasExistingReview($productId, null, $email)) {
                continue;
            }

            $token = $this->generateReviewToken($order, $productId);
            $lines[] = '• ' . $product->title . ': ' . $this->buildReviewUrl($product->url, $token);
        }

        if (count($lines) <= 6) {
            return false;
        }

        $lines[] = '';
        $lines[] = 'Only customers who have purchased a product can leave a review.';
        $lines[] = '';
        $lines[] = 'Thank you for shopping with us!';

        try {
            return Craft::$app->getMailer()
                ->compose()
                ->setTo($email)
                ->setSubject('How did we do? Review your order ' . $reference)
                ->setTextBody(implode("\n", $lines))
                ->send();
        } catch (\Throwable $e) {
            Craft::error('Review invite email failed: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function getSigningKey(): string
    {
        return Craft::parseEnv('$SECURITY_KEY') ?: Craft::$app->getSecurity()->getValidationKey();
    }
}
