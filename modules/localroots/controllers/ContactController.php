<?php

namespace modules\localroots\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class ContactController extends Controller
{
    protected array|int|bool $allowAnonymous = ['submit'];

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $name = trim((string) $request->getRequiredBodyParam('name'));
        $email = trim((string) $request->getRequiredBodyParam('email'));
        $phone = trim((string) $request->getRequiredBodyParam('phone'));
        $orderNumber = trim((string) $request->getBodyParam('orderNumber', ''));
        $message = trim((string) $request->getRequiredBodyParam('message'));

        $redirect = $request->getBodyParam('redirect', '/contact');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Craft::$app->getSession()->setError('Please enter a valid email address.');
            return $this->redirect($redirect);
        }

        $to = Craft::parseEnv('$CONTACT_TO_EMAIL')
            ?: Craft::$app->getSystemSettings()->getEmailSettings()->fromEmail;

        if (!$to) {
            Craft::$app->getSession()->setError('Contact form is not configured yet.');
            return $this->redirect($redirect);
        }

        $body = "Name: {$name}\n"
            . "Email: {$email}\n"
            . "Phone: {$phone}\n"
            . ($orderNumber !== '' ? "Order number: {$orderNumber}\n" : '')
            . "\nMessage:\n{$message}\n";

        try {
            $sent = Craft::$app->getMailer()
                ->compose()
                ->setTo($to)
                ->setReplyTo([$email => $name])
                ->setSubject('Contact form: ' . $name)
                ->setTextBody($body)
                ->send();

            if ($sent) {
                Craft::$app->getSession()->setNotice('Thanks for your message. We will get back to you soon.');
            } else {
                Craft::$app->getSession()->setError('Could not send your message. Please try again later.');
            }
        } catch (\Throwable) {
            Craft::$app->getSession()->setError('Could not send your message. Please try again later.');
        }

        return $this->redirect($redirect);
    }
}
