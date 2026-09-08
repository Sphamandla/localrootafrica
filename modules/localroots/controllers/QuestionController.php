<?php

namespace modules\localroots\controllers;

use Craft;
use craft\commerce\elements\Product;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\Response;

class QuestionController extends Controller
{
    protected array|int|bool $allowAnonymous = ['submit'];

    public function actionSubmit(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $productId = (int) $request->getRequiredBodyParam('productId');
        $question = trim((string) $request->getRequiredBodyParam('question'));
        $redirect = (string) $request->getBodyParam('redirect', '/');

        if ($question === '') {
            Craft::$app->getSession()->setError('Please enter your question.');
            return $this->redirect($redirect);
        }

        $product = Product::find()->id($productId)->one();
        if (!$product) {
            Craft::$app->getSession()->setError('Product not found.');
            return $this->redirect($redirect);
        }

        $user = Craft::$app->getUser()->getIdentity();
        $name = trim((string) $request->getBodyParam('authorName', ''));
        $email = trim((string) $request->getBodyParam('authorEmail', ''));

        if ($user) {
            $name = trim($user->fullName ?: $user->username);
            $email = (string) $user->email;
        }

        if ($name === '') {
            Craft::$app->getSession()->setError('Please enter your name.');
            return $this->redirect($redirect);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Craft::$app->getSession()->setError('Please enter a valid email address.');
            return $this->redirect($redirect);
        }

        $section = Craft::$app->entries->getSectionByHandle('productQuestions');
        if (!$section) {
            Craft::$app->getSession()->setError('Q&A is not configured yet.');
            return $this->redirect($redirect);
        }

        $entryType = $section->getEntryTypes()[0] ?? null;
        if (!$entryType) {
            Craft::$app->getSession()->setError('Q&A is not configured yet.');
            return $this->redirect($redirect);
        }

        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'authorId' => $user?->id,
            'title' => 'Q: ' . $product->title . ' – ' . mb_substr($question, 0, 60),
            'enabled' => true,
        ]);

        $entry->setFieldValues([
            'questionBody' => $question,
            'answerBody' => '',
            'questionProduct' => [$productId],
            'questionAuthorName' => $name,
            'questionAuthorEmail' => $email,
            'questionStatus' => 'pending',
        ]);

        if (!Craft::$app->getElements()->saveElement($entry)) {
            Craft::$app->getSession()->setError('Could not submit your question. Please try again.');
            return $this->redirect($redirect);
        }

        Craft::$app->getSession()->setNotice('Thank you! Your question has been submitted. We will respond soon.');
        return $this->redirect($redirect);
    }
}
