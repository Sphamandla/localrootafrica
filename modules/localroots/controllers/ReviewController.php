<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\Entry;
use craft\web\Controller;
use modules\localroots\services\ProductReviewService;
use yii\web\Response;

class ReviewController extends Controller
{
    protected array|int|bool $allowAnonymous = ['submit'];

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $productId = (int) $request->getRequiredBodyParam('productId');
        $rating = (int) $request->getRequiredBodyParam('rating');
        $body = trim((string) $request->getRequiredBodyParam('body'));
        $redirect = (string) $request->getBodyParam('redirect', '/');
        $token = trim((string) $request->getBodyParam('reviewToken', ''));

        if ($rating < 1 || $rating > 5) {
            Craft::$app->getSession()->setError('Please select a rating between 1 and 5 stars.');
            return $this->redirect($redirect);
        }

        if ($body === '') {
            Craft::$app->getSession()->setError('Please enter your review.');
            return $this->redirect($redirect);
        }

        $product = Product::find()->id($productId)->one();
        if (!$product) {
            Craft::$app->getSession()->setError('Product not found.');
            return $this->redirect($redirect);
        }

        /** @var ProductReviewService $reviews */
        $reviews = Craft::$app->getModule('localroots')->productReviews;

        $user = Craft::$app->getUser()->getIdentity();
        $tokenData = $token !== '' ? $reviews->validateToken($token, $productId) : null;

        $email = $tokenData['email'] ?? ($user?->email ?? null);
        $orderNumber = null;
        $verified = false;

        if ($tokenData) {
            $order = \craft\commerce\elements\Order::find()->id($tokenData['orderId'])->one();
            $orderNumber = $order?->reference ?? (string) ($tokenData['orderId'] ?? '');
            $verified = true;
        } elseif ($user && $reviews->hasPurchased($productId, $user->id)) {
            $verified = true;
        }

        if (!$verified) {
            Craft::$app->getSession()->setError('Only customers who have purchased this product may leave a review.');
            return $this->redirect($redirect);
        }

        if ($reviews->hasExistingReview($productId, $user?->id, $email)) {
            Craft::$app->getSession()->setError('You have already reviewed this product.');
            return $this->redirect($redirect);
        }

        $section = Craft::$app->entries->getSectionByHandle('productReviews');
        if (!$section) {
            Craft::$app->getSession()->setError('Reviews are not configured yet.');
            return $this->redirect($redirect);
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            Craft::$app->getSession()->setError('Reviews are not configured yet.');
            return $this->redirect($redirect);
        }

        $authorName = $user
            ? trim($user->fullName ?: $user->username)
            : trim((string) $request->getBodyParam('authorName', 'Customer'));

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'authorId' => $user?->id,
            'title' => 'Review: ' . $product->title . ' by ' . $authorName,
            'enabled' => true,
        ]);

        $entry->setFieldValues([
            'reviewBody' => $body,
            'reviewRating' => $rating,
            'reviewProduct' => [$productId],
            'reviewOrderNumber' => $orderNumber ?? '',
            'reviewAuthorName' => $authorName,
            'reviewAuthorEmail' => $email ?? '',
            'reviewVerifiedPurchase' => true,
            'reviewStatus' => 'approved',
        ]);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            Craft::$app->getSession()->setError('Could not save your review. Please try again.');
            return $this->redirect($redirect);
        }

        Craft::$app->getSession()->setNotice('Thank you! Your review has been published.');
        return $this->redirect($redirect);
    }
}
