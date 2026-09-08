<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class ShopController extends Controller
{
    protected array|int|bool $allowAnonymous = ['load-more'];

    public function actionLoadMore(): Response
    {
        $shopFilter = Craft::$app->getModule('localroots')->shopFilter;
        $categoryPath = Craft::$app->getRequest()->getQueryParam('categoryPath');
        $categoryContext = $categoryPath ? $shopFilter->resolveCategoryPath($categoryPath) : null;
        $params = $shopFilter->applyCategoryScope($shopFilter->getParams(), $categoryContext);
        $result = $shopFilter->getProducts($params);

        $html = '';
        foreach ($result['products'] as $product) {
            $html .= Craft::$app->getView()->renderTemplate('_includes/vamtam/product-loop-item.twig', [
                'product' => $product,
            ]);
        }

        $total = $result['total'];
        $limit = $result['limit'];
        $page = $result['page'];
        $rangeStart = $total ? (($page - 1) * $limit + 1) : 0;
        $rangeEnd = $total ? min($page * $limit, $total) : 0;
        $basePath = $categoryContext ? $categoryContext['basePath'] : 'shop';

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
}
