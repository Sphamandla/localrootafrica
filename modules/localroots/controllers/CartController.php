<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class CartController extends Controller
{
    protected array|int|bool $allowAnonymous = ['mini-cart'];

    public function actionMiniCart(): Response
    {
        $html = Craft::$app->getView()->renderTemplate('_includes/vamtam/mini-cart');
        return $this->asRaw($html);
    }
}
