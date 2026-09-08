<?php

namespace modules\localroots\services;

use Craft;
use craft\base\Component;
use craft\commerce\models\Coupon;
use craft\commerce\models\Discount;
use craft\commerce\Plugin as Commerce;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeZone;
use Throwable;

class EnvCouponService extends Component
{
    private const CACHE_KEY = 'localroots-env-coupons-hash';

    /**
     * Sync env coupons when the COUPONS value changes.
     */
    public function syncIfChanged(): void
    {
        if (!Craft::$app->plugins->isPluginInstalled('commerce')) {
            return;
        }

        $raw = trim((string)(App::env('COUPONS') ?? ''));
        $hash = md5($raw);
        $cache = Craft::$app->getCache();

        if ($cache->get(self::CACHE_KEY) === $hash) {
            return;
        }

        $this->syncFromEnv($raw);
        $cache->set(self::CACHE_KEY, $hash);
    }

    /**
     * @return list<array{code: string, percent: float, amount: float, uses: int, expires: string}>
     */
    public function parseEnvCoupons(?string $raw = null): array
    {
        $raw = trim($raw ?? (string)(App::env('COUPONS') ?? ''));
        if ($raw === '') {
            return [];
        }

        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                Craft::warning('COUPONS env var is invalid JSON.', __METHOD__);
                return [];
            }

            return array_values(array_filter(array_map([$this, '_normalizeCoupon'], $decoded)));
        }

        $coupons = [];
        foreach (preg_split('/\s*;\s*/', $raw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $chunk));
            if (count($parts) < 4) {
                $parts = array_map('trim', explode(':', $chunk));
            }

            if (count($parts) < 4) {
                Craft::warning("Skipping invalid COUPONS entry: {$chunk}", __METHOD__);
                continue;
            }

            $coupons[] = $this->_normalizeCoupon([
                'code' => $parts[0],
                'percent' => $parts[1],
                'uses' => $parts[2],
                'expires' => $parts[3],
                'amount' => $parts[4] ?? 0,
            ]);
        }

        return array_values(array_filter($coupons));
    }

    /**
     * @return array{created: int, updated: int, disabled: int}
     */
    public function syncFromEnv(?string $raw = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'disabled' => 0];
        if (!Craft::$app->plugins->isPluginInstalled('commerce')) {
            return $stats;
        }

        $configs = $this->parseEnvCoupons($raw);
        $commerce = Commerce::getInstance();
        $store = $commerce->getStores()->getPrimaryStore();
        if (!$store) {
            return $stats;
        }

        $activeCodes = [];
        foreach ($configs as $config) {
            if (!$this->_isActive($config)) {
                continue;
            }

            $activeCodes[] = strtoupper($config['code']);
            $existingCoupon = $commerce->getCoupons()->getCouponByCode($config['code']);
            $existingDiscount = $existingCoupon
                ? $commerce->getDiscounts()->getDiscountById($existingCoupon->discountId, $store->id)
                : null;
            $isNew = $existingDiscount === null;

            $discount = $existingDiscount ?? new Discount();
            $discount->storeId = $store->id;
            $discount->name = 'Env coupon: ' . strtoupper($config['code']);
            $discount->description = 'Managed via COUPONS env variable';
            $discount->requireCouponCode = true;
            $discount->enabled = true;
            $discount->allPurchasables = true;
            $discount->allCategories = true;
            $discount->ignorePromotions = false;
            $discount->excludeOnPromotion = false;
            $discount->totalDiscountUseLimit = $config['uses'];
            $discount->dateFrom = new DateTime('now', new DateTimeZone('UTC'));
            $discount->dateTo = DateTimeHelper::toDateTime($config['expires'] . ' 23:59:59');

            if ($config['amount'] > 0) {
                $discount->baseDiscount = $config['amount'];
                $discount->percentDiscount = 0;
            } else {
                $discount->baseDiscount = 0;
                $discount->percentDiscount = min(100, max(0, $config['percent']));
            }

            $coupon = new Coupon([
                'code' => strtoupper($config['code']),
                'maxUses' => $config['uses'],
            ]);

            if ($existingDiscount) {
                $existingCoupons = $commerce->getCoupons()->getCouponsByDiscountId($existingDiscount->id);
                $coupon = $existingCoupons[0] ?? $coupon;
                $coupon->code = strtoupper($config['code']);
                $coupon->maxUses = $config['uses'];
                $coupon->discountId = $existingDiscount->id;
            }

            $discount->setCoupons([$coupon]);

            if ($commerce->getDiscounts()->saveDiscount($discount)) {
                $stats[$isNew ? 'created' : 'updated']++;
            }
        }

        foreach ($commerce->getDiscounts()->getAllDiscounts($store->id) as $discount) {
            if (!str_starts_with((string)$discount->description, 'Managed via COUPONS env variable')) {
                continue;
            }

            $codes = array_map(static fn(Coupon $coupon) => strtoupper((string)$coupon->code), $discount->getCoupons());
            $stillActive = (bool)array_intersect($codes, $activeCodes);

            if (!$stillActive && $discount->enabled) {
                $discount->enabled = false;
                if ($commerce->getDiscounts()->saveDiscount($discount)) {
                    $stats['disabled']++;
                }
            }
        }

        Craft::$app->getCache()->set(self::CACHE_KEY, md5(trim($raw ?? (string)(App::env('COUPONS') ?? ''))));

        return $stats;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{code: string, percent: float, amount: float, uses: int, expires: string}|null
     */
    private function _normalizeCoupon(array $config): ?array
    {
        $code = strtoupper(trim((string)($config['code'] ?? '')));
        $expires = trim((string)($config['expires'] ?? ''));
        $uses = (int)($config['uses'] ?? 0);

        if ($code === '' || $expires === '' || $uses <= 0) {
            return null;
        }

        return [
            'code' => $code,
            'percent' => (float)($config['percent'] ?? 0),
            'amount' => (float)($config['amount'] ?? 0),
            'uses' => $uses,
            'expires' => $expires,
        ];
    }

    /**
     * @param array{code: string, percent: float, amount: float, uses: int, expires: string} $config
     */
    private function _isActive(array $config): bool
    {
        try {
            $expires = new DateTime($config['expires'] . ' 23:59:59');
            return $expires >= new DateTime('now');
        } catch (Throwable) {
            return false;
        }
    }
}
