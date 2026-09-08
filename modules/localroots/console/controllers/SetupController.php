<?php

namespace modules\localroots\console\controllers;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\commerce\models\ProductType;
use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\TitleField;
use craft\commerce\fields\Products as ProductsField;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Dropdown;
use craft\fields\Email;
use craft\fields\Lightswitch;
use craft\fields\Matrix;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\fields\Tags;
use craft\models\CategoryGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\TagGroup;
use craft\models\Volume;
use craft\models\VolumeFolder;
use modules\localroots\gateways\CashEftGateway;
use modules\localroots\gateways\CashOnDeliveryGateway;
use modules\localroots\gateways\OzowGateway;
use modules\localroots\gateways\PayfastGateway;
use modules\localroots\gateways\YocoGateway;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class SetupController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout("Setting up Local Roots Africa...\n", Console::FG_GREEN);

        $this->_createVolumes();
        $this->_createFields();
        $this->_createCategoryAndTagGroups();
        $this->_createGlobals();
        $this->_createSections();
        $this->_setupCommerce();
        $this->_configureUsers();

        Craft::$app->getProjectConfig()->rebuild();
        $this->stdout("Setup complete!\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function _createVolumes(): void
    {
        $fsService = Craft::$app->getFs();
        if (!$fsService->getFilesystemByHandle('local')) {
            $fs = $fsService->createFilesystem([
                'type' => \craft\fs\Local::class,
                'name' => 'Local',
                'handle' => 'local',
                'hasUrls' => true,
                'url' => '@web/uploads',
                'path' => '@webroot/uploads',
            ]);
            $fsService->saveFilesystem($fs);
            $this->stdout("  Created filesystem: local\n");
        }

        $volumes = Craft::$app->getVolumes();

        foreach ([
            ['handle' => 'products', 'name' => 'Products', 'subpath' => 'products'],
            ['handle' => 'content', 'name' => 'Content', 'subpath' => 'content'],
        ] as $config) {
            if ($volumes->getVolumeByHandle($config['handle'])) {
                continue;
            }
            $volume = new Volume([
                'name' => $config['name'],
                'handle' => $config['handle'],
                'fs' => 'local',
                'subpath' => $config['subpath'],
            ]);
            $volumes->saveVolume($volume);
            $this->stdout("  Created volume: {$config['handle']}\n");
        }
    }

    private function _createFields(): void
    {
        $fieldsService = Craft::$app->getFields();
        $productsVolume = Craft::$app->getVolumes()->getVolumeByHandle('products');
        $contentVolume = Craft::$app->getVolumes()->getVolumeByHandle('content');

        $fieldDefs = [
            ['handle' => 'siteName', 'name' => 'Site Name', 'type' => PlainText::class, 'settings' => ['multiline' => false]],
            ['handle' => 'siteLogo', 'name' => 'Site Logo', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1, 'viewMode' => 'large']],
            ['handle' => 'siteFavicon', 'name' => 'Favicon', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1]],
            ['handle' => 'copyrightText', 'name' => 'Copyright Text', 'type' => PlainText::class, 'settings' => ['multiline' => true]],
            ['handle' => 'socialFacebook', 'name' => 'Facebook URL', 'type' => PlainText::class],
            ['handle' => 'socialInstagram', 'name' => 'Instagram URL', 'type' => PlainText::class],
            ['handle' => 'socialTwitter', 'name' => 'Twitter URL', 'type' => PlainText::class],
            ['handle' => 'socialPinterest', 'name' => 'Pinterest URL', 'type' => PlainText::class],
            ['handle' => 'defaultSeoTitle', 'name' => 'Default SEO Title', 'type' => PlainText::class],
            ['handle' => 'defaultSeoDescription', 'name' => 'Default SEO Description', 'type' => PlainText::class, 'settings' => ['multiline' => true]],
            ['handle' => 'pageHeroImage', 'name' => 'Hero Image', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image']]],
            ['handle' => 'pageBody', 'name' => 'Body', 'type' => PlainText::class, 'settings' => ['multiline' => true, 'initialRows' => 8]],
            ['handle' => 'faqItems', 'name' => 'FAQ Items', 'type' => Table::class, 'settings' => [
                'columns' => [
                    'col1' => ['heading' => 'Section', 'handle' => 'section', 'width' => '25%', 'type' => 'singleline'],
                    'col2' => ['heading' => 'Question', 'handle' => 'question', 'width' => '35%', 'type' => 'singleline'],
                    'col3' => ['heading' => 'Answer', 'handle' => 'answer', 'width' => '40%', 'type' => 'multiline'],
                ],
            ]],
            ['handle' => 'promoTitle', 'name' => 'Promo Title', 'type' => PlainText::class],
            ['handle' => 'promoSubtitle', 'name' => 'Promo Subtitle', 'type' => PlainText::class],
            ['handle' => 'promoImage', 'name' => 'Promo Image', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image']]],
            ['handle' => 'promoLink', 'name' => 'Promo Link', 'type' => PlainText::class],
            ['handle' => 'promoButtonText', 'name' => 'Button Text', 'type' => PlainText::class],
            ['handle' => 'productImages', 'name' => 'Product Images', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image'], 'viewMode' => 'large']],
            ['handle' => 'productCategories', 'name' => 'Categories', 'type' => Categories::class, 'settings' => ['source' => 'group:productCategories']],
            ['handle' => 'productTags', 'name' => 'Tags', 'type' => Tags::class, 'settings' => ['source' => 'group:productTags']],
            ['handle' => 'productShortDescription', 'name' => 'Short Description', 'type' => PlainText::class, 'settings' => ['multiline' => true]],
            ['handle' => 'orderNumber', 'name' => 'Order Number', 'type' => PlainText::class],
            ['handle' => 'customerInfo', 'name' => 'Customer Info', 'type' => PlainText::class, 'settings' => ['multiline' => true]],
            ['handle' => 'lineItems', 'name' => 'Line Items', 'type' => PlainText::class, 'settings' => ['multiline' => true]],
            ['handle' => 'totalPrice', 'name' => 'Total Price', 'type' => Number::class],
            ['handle' => 'paymentStatus', 'name' => 'Payment Status', 'type' => PlainText::class],
            ['handle' => 'orderStatus', 'name' => 'Order Status', 'type' => PlainText::class],
            ['handle' => 'courierTrackingReference', 'name' => 'Courier Tracking Reference', 'type' => PlainText::class, 'settings' => ['placeholder' => 'TCGD000404']],
            ['handle' => 'featuredProduct', 'name' => 'Featured', 'type' => Lightswitch::class],
            ['handle' => 'simplePurchase', 'name' => 'Simple purchase (no options)', 'type' => Lightswitch::class],
            ['handle' => 'reviewBody', 'name' => 'Review Body', 'type' => PlainText::class, 'settings' => ['multiline' => true, 'initialRows' => 4]],
            ['handle' => 'reviewRating', 'name' => 'Rating', 'type' => Number::class, 'settings' => ['decimals' => 0, 'min' => 1, 'max' => 5]],
            ['handle' => 'reviewProduct', 'name' => 'Product', 'type' => ProductsField::class, 'settings' => ['sources' => ['*'], 'limit' => 1]],
            ['handle' => 'reviewOrderNumber', 'name' => 'Order Number', 'type' => PlainText::class],
            ['handle' => 'reviewAuthorName', 'name' => 'Author Name', 'type' => PlainText::class],
            ['handle' => 'reviewAuthorEmail', 'name' => 'Author Email', 'type' => Email::class],
            ['handle' => 'reviewVerifiedPurchase', 'name' => 'Verified Purchase', 'type' => Lightswitch::class],
            ['handle' => 'reviewStatus', 'name' => 'Review Status', 'type' => Dropdown::class, 'settings' => [
                'options' => [
                    ['label' => 'Pending', 'value' => 'pending', 'default' => ''],
                    ['label' => 'Approved', 'value' => 'approved', 'default' => '1'],
                    ['label' => 'Rejected', 'value' => 'rejected', 'default' => ''],
                ],
            ]],
            ['handle' => 'questionBody', 'name' => 'Question', 'type' => PlainText::class, 'settings' => ['multiline' => true, 'initialRows' => 3]],
            ['handle' => 'answerBody', 'name' => 'Answer', 'type' => PlainText::class, 'settings' => ['multiline' => true, 'initialRows' => 4]],
            ['handle' => 'questionProduct', 'name' => 'Product', 'type' => ProductsField::class, 'settings' => ['sources' => ['*'], 'limit' => 1]],
            ['handle' => 'questionAuthorName', 'name' => 'Author Name', 'type' => PlainText::class],
            ['handle' => 'questionAuthorEmail', 'name' => 'Author Email', 'type' => Email::class],
            ['handle' => 'questionStatus', 'name' => 'Question Status', 'type' => Dropdown::class, 'settings' => [
                'options' => [
                    ['label' => 'Pending', 'value' => 'pending', 'default' => '1'],
                    ['label' => 'Answered', 'value' => 'answered', 'default' => ''],
                    ['label' => 'Rejected', 'value' => 'rejected', 'default' => ''],
                ],
            ]],
            ['handle' => 'menuBackgroundImage', 'name' => 'Menu Background Image', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1, 'viewMode' => 'large']],
            ['handle' => 'menuPromoImage', 'name' => 'Menu Promo Image', 'type' => Assets::class, 'settings' => ['allowedKinds' => ['image'], 'maxRelations' => 1, 'viewMode' => 'large']],
            ['handle' => 'menuPromoButtonText', 'name' => 'Menu Promo Button Text', 'type' => PlainText::class],
            ['handle' => 'menuPromoButtonUrl', 'name' => 'Menu Promo Button URL', 'type' => PlainText::class],
            ['handle' => 'menuPrimaryNav', 'name' => 'Menu Primary Navigation', 'type' => Table::class, 'settings' => [
                'columns' => [
                    'col1' => ['heading' => 'Label', 'handle' => 'label', 'width' => '40%', 'type' => 'singleline'],
                    'col2' => ['heading' => 'URL', 'handle' => 'url', 'width' => '60%', 'type' => 'singleline'],
                ],
            ]],
            ['handle' => 'menuFooterNav', 'name' => 'Menu Footer Navigation', 'type' => Table::class, 'settings' => [
                'columns' => [
                    'col1' => ['heading' => 'Label', 'handle' => 'label', 'width' => '40%', 'type' => 'singleline'],
                    'col2' => ['heading' => 'URL', 'handle' => 'url', 'width' => '60%', 'type' => 'singleline'],
                ],
            ]],
        ];

        foreach ($fieldDefs as $def) {
            if ($fieldsService->getFieldByHandle($def['handle'])) {
                continue;
            }
            $settings = $def['settings'] ?? [];
            if (str_contains($def['handle'], 'Image') || str_contains($def['handle'], 'Logo') || str_contains($def['handle'], 'Favicon')) {
                $settings['sources'] = ['volume:' . ($productsVolume?->uid ?? '')];
                if (str_contains($def['handle'], 'page') || str_contains($def['handle'], 'promo') || str_contains($def['handle'], 'menu')) {
                    $settings['sources'] = ['volume:' . ($contentVolume?->uid ?? '')];
                }
            }
            if ($def['handle'] === 'productImages') {
                $settings['sources'] = ['volume:' . ($productsVolume?->uid ?? '')];
            }

            $field = Craft::$app->getFields()->createField([
                'type' => $def['type'],
                'name' => $def['name'],
                'handle' => $def['handle'],
                'settings' => $settings,
            ]);
            Craft::$app->getFields()->saveField($field);
            $this->stdout("  Created field: {$def['handle']}\n");
        }
    }

    private function _createCategoryAndTagGroups(): void
    {
        $groups = Craft::$app->getCategories();
        if (!$groups->getGroupByHandle('productCategories')) {
            $group = new CategoryGroup(['name' => 'Product Categories', 'handle' => 'productCategories']);
            $groups->saveGroup($group);
            $this->stdout("  Created category group: productCategories\n");
        }

        $tagGroups = Craft::$app->getTags();
        if (!$tagGroups->getTagGroupByHandle('productTags')) {
            $group = new TagGroup(['name' => 'Product Tags', 'handle' => 'productTags']);
            $tagGroups->saveTagGroup($group);
            $this->stdout("  Created tag group: productTags\n");
        }
    }

    private function _createGlobals(): void
    {
        $globalsService = Craft::$app->getGlobals();
        $fieldsService = Craft::$app->getFields();

        $globalSets = [
            'siteSettings' => ['Site Settings', ['siteName', 'siteLogo', 'siteFavicon']],
            'footerSettings' => ['Footer Settings', ['copyrightText', 'socialFacebook', 'socialInstagram', 'socialTwitter', 'socialPinterest']],
            'mobileMenuSettings' => ['Mobile Menu', [
                'menuBackgroundImage', 'menuPromoImage', 'menuPromoButtonText', 'menuPromoButtonUrl',
                'menuPrimaryNav', 'menuFooterNav',
            ]],
            'seoSettings' => ['SEO Settings', ['defaultSeoTitle', 'defaultSeoDescription']],
        ];

        foreach ($globalSets as $handle => [$name, $fieldHandles]) {
            if ($globalsService->getSetByHandle($handle)) {
                continue;
            }
            $layout = new FieldLayout(['type' => GlobalSet::class]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
            $elements = [];
            foreach ($fieldHandles as $fh) {
                $field = $fieldsService->getFieldByHandle($fh);
                if ($field) {
                    $elements[] = Craft::$app->getFields()->createLayoutElement([
                        'type' => CustomField::class,
                        'fieldUid' => $field->uid,
                    ]);
                }
            }
            $tab->setElements($elements);
            $layout->setTabs([$tab]);

            $set = new GlobalSet(['name' => $name, 'handle' => $handle]);
            $set->setFieldLayout($layout);
            $globalsService->saveSet($set);
            $this->stdout("  Created global set: {$handle}\n");
        }
    }

    private function _createSections(): void
    {
        $sectionsService = Craft::$app->entries;
        $fieldsService = Craft::$app->getFields();
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        $sections = [
            'pages' => [
                'name' => 'Pages',
                'type' => Section::TYPE_STRUCTURE,
                'uriFormat' => '{slug}',
                'template' => '_pages/_entry',
                'fields' => ['pageHeroImage', 'pageBody', 'faqItems'],
            ],
            'promotions' => [
                'name' => 'Promotions',
                'type' => Section::TYPE_CHANNEL,
                'uriFormat' => null,
                'template' => null,
                'fields' => ['promoTitle', 'promoSubtitle', 'promoImage', 'promoLink', 'promoButtonText'],
            ],
            'orders' => [
                'name' => 'Orders',
                'type' => Section::TYPE_CHANNEL,
                'uriFormat' => null,
                'template' => null,
                'fields' => ['orderNumber', 'customerInfo', 'lineItems', 'totalPrice', 'paymentStatus', 'orderStatus'],
            ],
            'press' => [
                'name' => 'Press',
                'type' => Section::TYPE_CHANNEL,
                'uriFormat' => 'press/{slug}',
                'template' => '_pages/press/_entry',
                'fields' => ['pageHeroImage', 'pageBody'],
            ],
            'deliveryAndReturns' => [
                'name' => 'Delivery and Returns',
                'type' => Section::TYPE_SINGLE,
                'uriFormat' => 'delivery-and-returns',
                'template' => '_pages/delivery-and-returns',
                'fields' => ['pageBody'],
            ],
            'sustainability' => [
                'name' => 'Sustainability',
                'type' => Section::TYPE_SINGLE,
                'uriFormat' => 'sustainability',
                'template' => '_pages/sustainability',
                'fields' => ['pageBody'],
            ],
            'orderStatus' => [
                'name' => 'Order Status',
                'type' => Section::TYPE_SINGLE,
                'uriFormat' => 'order-status',
                'template' => '_pages/order-status',
                'fields' => ['pageBody'],
            ],
            'productReviews' => [
                'name' => 'Product Reviews',
                'type' => Section::TYPE_CHANNEL,
                'uriFormat' => null,
                'template' => null,
                'fields' => [
                    'reviewBody', 'reviewRating', 'reviewProduct', 'reviewOrderNumber',
                    'reviewAuthorName', 'reviewAuthorEmail', 'reviewVerifiedPurchase', 'reviewStatus',
                ],
            ],
            'productQuestions' => [
                'name' => 'Product Questions',
                'type' => Section::TYPE_CHANNEL,
                'uriFormat' => null,
                'template' => null,
                'fields' => [
                    'questionBody', 'answerBody', 'questionProduct',
                    'questionAuthorName', 'questionAuthorEmail', 'questionStatus',
                ],
            ],
        ];

        foreach ($sections as $handle => $config) {
            if ($sectionsService->getSectionByHandle($handle)) {
                continue;
            }

            $layout = new FieldLayout(['type' => \craft\elements\Entry::class]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
            $elements = [Craft::$app->getFields()->createLayoutElement(['type' => TitleField::class])];
            foreach ($config['fields'] as $fh) {
                $field = $fieldsService->getFieldByHandle($fh);
                if ($field) {
                    $elements[] = Craft::$app->getFields()->createLayoutElement([
                        'type' => CustomField::class,
                        'fieldUid' => $field->uid,
                    ]);
                }
            }
            $tab->setElements($elements);
            $layout->setTabs([$tab]);

            $entryType = new \craft\models\EntryType([
                'name' => $config['name'],
                'handle' => $handle,
                'hasTitleField' => true,
            ]);
            $entryType->setFieldLayout($layout);
            $sectionsService->saveEntryType($entryType);

            $section = new Section([
                'name' => $config['name'],
                'handle' => $handle,
                'type' => $config['type'],
                'enableVersioning' => true,
            ]);

            $siteSettings = new Section_SiteSettings([
                'siteId' => $primarySite->id,
                'enabledByDefault' => true,
                'hasUrls' => $config['uriFormat'] !== null,
                'uriFormat' => $config['uriFormat'],
                'template' => $config['template'],
            ]);
            $section->setSiteSettings([$primarySite->id => $siteSettings]);
            $section->setEntryTypes([$entryType]);

            $sectionsService->saveSection($section);
            $this->stdout("  Created section: {$handle}\n");
        }
    }

    private function _setupCommerce(): void
    {
        if (!Craft::$app->plugins->isPluginInstalled('commerce')) {
            $this->stdout("  Commerce not installed, skipping...\n", Console::FG_YELLOW);
            return;
        }

        $commerce = Commerce::getInstance();
        $productTypes = $commerce->getProductTypes();
        $fieldsService = Craft::$app->getFields();
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        if (!$productTypes->getProductTypeByHandle('default')) {
            $layout = new FieldLayout(['type' => \craft\commerce\elements\Product::class]);
            $tab = new FieldLayoutTab(['name' => 'Product', 'layout' => $layout]);
            $elements = [];
            foreach (['productImages', 'productShortDescription', 'productCategories', 'productTags', 'featuredProduct', 'simplePurchase'] as $fh) {
                $field = $fieldsService->getFieldByHandle($fh);
                if ($field) {
                    $elements[] = Craft::$app->getFields()->createLayoutElement([
                        'type' => CustomField::class,
                        'fieldUid' => $field->uid,
                    ]);
                }
            }
            $tab->setElements($elements);
            $layout->setTabs([$tab]);

            $productType = new ProductType([
                'name' => 'Default',
                'handle' => 'default',
            ]);
            $productType->setFieldLayout($layout);

            $siteSettings = new \craft\commerce\models\ProductTypeSite([
                'siteId' => $primarySite->id,
                'hasUrls' => true,
                'uriFormat' => 'products/{slug}',
                'template' => '_pages/products/_entry',
            ]);
            $productType->setSiteSettings([$primarySite->id => $siteSettings]);

            $productTypes->saveProductType($productType);
            $this->stdout("  Created product type: default\n");
        }

        $gateways = $commerce->getGateways();
        $orderStatuses = $commerce->getOrderStatuses();
        if (!$orderStatuses->getOrderStatusByHandle('awaitingPayment')) {
            $store = $commerce->getStores()->getPrimaryStore();
            $status = new \craft\commerce\models\OrderStatus([
                'storeId' => $store?->id,
                'name' => 'Awaiting payment',
                'handle' => 'awaitingPayment',
                'color' => 'orange',
                'description' => 'Order placed via Cash/EFT and awaiting bank payment.',
                'default' => false,
            ]);
            $orderStatuses->saveOrderStatus($status);
            $this->stdout("  Created order status: awaitingPayment\n");
        }

        foreach ([
            ['name' => 'Direct bank transfer', 'handle' => 'cash-eft', 'class' => CashEftGateway::class, 'paymentType' => 'authorize', 'isFrontendEnabled' => true],
            ['name' => 'Cash on delivery', 'handle' => 'cash-on-delivery', 'class' => CashOnDeliveryGateway::class, 'paymentType' => 'authorize', 'isFrontendEnabled' => true],
            ['name' => 'PayFast', 'handle' => 'payfast', 'class' => PayfastGateway::class, 'paymentType' => 'purchase', 'isFrontendEnabled' => false],
            ['name' => 'Ozow', 'handle' => 'ozow', 'class' => OzowGateway::class, 'paymentType' => 'purchase', 'isFrontendEnabled' => false],
            ['name' => 'Yoco', 'handle' => 'yoco', 'class' => YocoGateway::class, 'paymentType' => 'purchase', 'isFrontendEnabled' => false],
        ] as $gw) {
            $existing = $gateways->getGatewayByHandle($gw['handle']);
            if ($existing) {
                $changed = false;
                if ($existing->name !== $gw['name']) {
                    $existing->name = $gw['name'];
                    $changed = true;
                }
                if ($existing->isFrontendEnabled !== $gw['isFrontendEnabled']) {
                    $existing->isFrontendEnabled = $gw['isFrontendEnabled'];
                    $changed = true;
                }
                if ($changed) {
                    $gateways->saveGateway($existing);
                    $this->stdout("  Updated gateway: {$gw['handle']}\n");
                }
                continue;
            }
            $gateway = $gateways->createGateway([
                'name' => $gw['name'],
                'handle' => $gw['handle'],
                'type' => $gw['class'],
                'paymentType' => $gw['paymentType'],
                'isFrontendEnabled' => $gw['isFrontendEnabled'],
            ]);
            $gateways->saveGateway($gateway);
            $this->stdout("  Created gateway: {$gw['handle']}\n");
        }

        $store = $commerce->getStores()->getPrimaryStore();
        if ($store) {
            $store->currency = 'ZAR';
            $commerce->getStores()->saveStore($store);
        }

        $this->_configureOrderFields();
    }

    private function _configureOrderFields(): void
    {
        $fieldsService = Craft::$app->getFields();
        $field = $fieldsService->getFieldByHandle('courierTrackingReference');
        if (!$field) {
            return;
        }

        $layout = $fieldsService->getLayoutByType(\craft\commerce\elements\Order::class);
        foreach ($layout->getCustomFields() as $customField) {
            if ($customField->handle === 'courierTrackingReference') {
                return;
            }
        }

        $tab = $layout->getTabs()[0] ?? new FieldLayoutTab(['name' => 'Shipping', 'layout' => $layout]);
        $elements = $tab->getElements();
        $elements[] = Craft::$app->getFields()->createLayoutElement([
            'type' => CustomField::class,
            'fieldUid' => $field->uid,
        ]);
        $tab->setElements($elements);

        if (!$layout->getTabs()) {
            $layout->setTabs([$tab]);
        }

        $fieldsService->saveLayout($layout);
        $this->stdout("  Added courierTrackingReference to order field layout\n");
    }

    private function _configureUsers(): void
    {
        $settings = Craft::$app->getProjectConfig()->get('users') ?? [];
        $settings['allowPublicRegistration'] = true;
        $settings['requireEmailVerification'] = false;
        $settings['validateOnPublicRegistration'] = true;
        $settings['defaultGroup'] = null;
        Craft::$app->getProjectConfig()->set('users', $settings);
        $this->stdout("  Enabled public registration\n");
    }
}
