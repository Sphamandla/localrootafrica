<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class NewsletterController extends Controller
{
    protected array|int|bool $allowAnonymous = ['subscribe'];

    public function actionSubscribe(): Response
    {
        $this->requirePostRequest();

        $email = Craft::$app->getRequest()->getRequiredBodyParam('email');
        $apiKey = Craft::parseEnv('$MAILCHIMP_API_KEY');
        $listId = Craft::parseEnv('$MAILCHIMP_LIST_ID');

        if (!$apiKey || !$listId || str_contains($apiKey, 'your_')) {
            Craft::$app->getSession()->setError('Newsletter is not configured yet.');
            return $this->redirectToPostedUrl(Craft::$app->getRequest()->getReferrer() ?: '/');
        }

        $dc = 'us1';
        if (preg_match('/-(\w+)$/', $apiKey, $m)) {
            $dc = $m[1];
        }

        $url = "https://{$dc}.api.mailchimp.com/3.0/lists/{$listId}/members";
        $payload = json_encode([
            'email_address' => $email,
            'status' => 'subscribed',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => 'user:' . $apiKey,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (in_array($code, [200, 400], true)) {
            Craft::$app->getSession()->setNotice('Thanks for subscribing!');
        } else {
            Craft::$app->getSession()->setError('Subscription failed. Please try again.');
        }

        return $this->redirectToPostedUrl(Craft::$app->getRequest()->getReferrer() ?: '/');
    }
}
