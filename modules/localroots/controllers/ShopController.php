<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use verbb\wishlist\elements\Item;
use verbb\wishlist\Wishlist as WishlistPlugin;
use yii\web\Response;

class ShopController extends Controller
{
    protected array|int|bool $allowAnonymous = ['load-more'];

    public function actionLoadMore(): Response
    {
        $shopFilter = Craft::$app->getModule('localroots')->shopFilter;
        $request = Craft::$app->getRequest();
        $categoryPath = $request->getQueryParam('categoryPath');
        $brandSlug = $request->getQueryParam('brandSlug');
        $categoryContext = $categoryPath ? $shopFilter->resolveCategoryPath($categoryPath) : null;
        $brandContext = $brandSlug ? $shopFilter->resolveBrandSlug($brandSlug) : null;
        $params = $shopFilter->applyBrandScope(
            $shopFilter->applyCategoryScope($shopFilter->getParams(), $categoryContext),
            $brandContext
        );
        $result = $shopFilter->getProducts($params);
        $wishlistProductIds = $this->getWishlistProductIds();

        $html = '';
        foreach ($result['products'] as $product) {
            $html .= Craft::$app->getView()->renderTemplate('_includes/vamtam/product-loop-item.twig', [
                'product' => $product,
                'wishlistProductIds' => $wishlistProductIds,
            ]);
        }

        $total = $result['total'];
        $limit = $result['limit'];
        $page = $result['page'];
        $rangeStart = $total ? (($page - 1) * $limit + 1) : 0;
        $rangeEnd = $total ? min($page * $limit, $total) : 0;
        $basePath = $brandContext
            ? $brandContext['basePath']
            : ($categoryContext ? $categoryContext['basePath'] : 'shop');

        return $this->asJson([
            'success' => true,
            'html' => $html,
            'currentPage' => $page,
            'totalPages' => $result['totalPages'],
            'totalProducts' => $total,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'hasMore' => $page < $result['totalPages'],
            'nextPageUrl' => $page < $result['totalPages']
                ? $shopFilter->buildFilterUrl($params, ['page' => $page + 1], $basePath)
                : null,
        ]);
    }

    /**
     * @return list<int>
     */
    private function getWishlistProductIds(): array
    {
        if (!class_exists(WishlistPlugin::class)) {
            return [];
        }

        $list = WishlistPlugin::$plugin->getLists()->getUserList();
        if (!$list?->id) {
            return [];
        }

        return array_map('intval', Item::find()
            ->listId($list->id)
            ->select(['elementId'])
            ->column());
    }
}
