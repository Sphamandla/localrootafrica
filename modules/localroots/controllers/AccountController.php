<?php

namespace modules\localroots\controllers;

use Craft;
use craft\elements\Address;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class AccountController extends Controller
{
    public function actionSaveAddress(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $user = Craft::$app->getUser()->getIdentity();
        $request = Craft::$app->getRequest();
        $addressType = (string)$request->getBodyParam('addressType', '');
        if (!in_array($addressType, ['billing', 'shipping'], true)) {
            throw new BadRequestHttpException('Invalid address type.');
        }

        $prefix = $addressType === 'billing' ? 'billingAddress' : 'shippingAddress';
        $addressId = (int)$request->getBodyParam('addressId', 0);

        if ($addressId) {
            $address = Address::find()->id($addressId)->ownerId($user->id)->one();
            if (!$address) {
                throw new BadRequestHttpException('Address not found.');
            }
        } else {
            $address = new Address();
            $address->setOwner($user);
        }

        $address->firstName = trim((string)$request->getBodyParam($prefix . '[firstName]', ''));
        $address->lastName = trim((string)$request->getBodyParam($prefix . '[lastName]', ''));
        $address->organization = trim((string)$request->getBodyParam($prefix . '[organization]', '')) ?: null;
        $address->addressLine1 = trim((string)$request->getBodyParam($prefix . '[addressLine1]', ''));
        $address->addressLine2 = trim((string)$request->getBodyParam($prefix . '[addressLine2]', '')) ?: null;
        $address->locality = trim((string)$request->getBodyParam($prefix . '[locality]', ''));
        $address->postalCode = trim((string)$request->getBodyParam($prefix . '[postalCode]', ''));
        $address->countryCode = trim((string)$request->getBodyParam($prefix . '[countryCode]', 'ZA')) ?: 'ZA';
        $address->phone = trim((string)$request->getBodyParam($prefix . '[phone]', '')) ?: null;

        if ($addressType === 'billing') {
            $address->isPrimaryBilling = true;
        } else {
            $address->isPrimaryShipping = true;
        }

        if (!Craft::$app->getElements()->saveElement($address)) {
            Craft::$app->getSession()->setError('Could not save your address. Please check the form and try again.');

            return $this->redirectToPostedUrl('/account/edit-address');
        }

        Craft::$app->getSession()->setNotice('Address saved.');

        return $this->redirectToPostedUrl('/account/edit-address');
    }
}
