<?php

namespace Solspace\FreeformPayments;

use craft\base\Plugin;
use craft\db\Query;
use craft\events\PluginEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use Solspace\Freeform\Events\Fields\FetchFieldTypes;
use Solspace\Freeform\Events\Integrations\FetchPaymentGatewayTypesEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Services\FieldsService;
use Solspace\Freeform\Services\FormsService;
use Solspace\Freeform\Services\PaymentGatewaysService;
use Solspace\FreeformPayments\Models\Settings;
use Solspace\FreeformPayments\Services\StripeService;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use yii\base\Event;
use Solspace\FreeformPayments\Controllers\WebhooksController;
use Solspace\FreeformPayments\Services\NotificationService;
use Solspace\Freeform\Models\FieldModel;
use Solspace\Freeform\Library\Composer\Components\FieldInterface;
use Solspace\Freeform\Controllers\SubmissionsController;
use Solspace\FreeformPayments\Services\AssetsService;
use Solspace\FreeformPayments\Services\PaymentsService;
use Solspace\FreeformPayments\Services\SubscriptionsService;
use Solspace\FreeformPayments\Services\SubscriptionPlansService;
use Solspace\Freeform\Elements\Submission;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\base\Element;
use Solspace\FreeformPayments\Library\ElementHookHandlers\SubmissionHookHandler;
use Solspace\FreeformPayments\Library\ElementHookHandlers\FormHookHandler;
use Solspace\FreeformPayments\Controllers\SubscriptionsController;
use Solspace\Freeform\Records\IntegrationRecord;
use Solspace\Freeform\Records\FieldRecord;
use Solspace\Freeform\Library\Composer\Components\Properties;
use Solspace\FreeformPayments\Variables\FreeformPaymentsVariable;
use craft\web\twig\variables\CraftVariable;

/**
 * Class FreeformPayments
 *
 * @property StripeService $stripe
 * @property NotificationService $notification
 * @property AssetsService $assets
 * @property PaymentsService $payments
 * @property SubscriptionsService $subscriptions
 * @property SubscriptionPlansService $subscriptionPlans
 */
class FreeformPayments extends Plugin
{
    const TRANSLATION_CATEGORY = 'freeform';

    public $hasCpSection = false;

    //XXX: for some reason CP section gone only with this method override
    public function getCpNavItem()
    {
        return null;
    }

    /**
     * @return FreeformPayments|Plugin
     */
    public static function getInstance(): FreeformPayments
    {
        return parent::getInstance();
    }

    /**
     * @return Settings
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Add events
     */
    public function init()
    {
        parent::init();

        $this->initControllers();
        $this->initServices();

        if (!class_exists(Freeform::class)) {
            return;
        }

        $this->initRoutes();
        $this->initIntegrations();
        $this->initPermissions();
        $this->initStripe();
        $this->initAssets();
        $this->initHookHandlers();
        $this->initTwigVariables();
    }

    /**
     * @param string $message
     * @param array  $params
     * @param string $language
     *
     * @return string
     */
    public static function t(string $message, array $params = [], string $language = null): string
    {
        return \Craft::t(self::TRANSLATION_CATEGORY, $message, $params, $language);
    }

    /**
     * @inheritDoc
     */
    protected function afterInstall()
    {
        parent::afterInstall();

        $fieldService  = Freeform::getInstance()->fields;
        $field         = FieldModel::create();
        $field->handle = 'payment';
        $field->label  = '';
        $field->type   = FieldInterface::TYPE_CREDIT_CARD_DETAILS;
        $fieldService->save($field);
    }

    /**
     * Install only if Freeform Lite is installed
     *
     * @return bool
     */
    protected function beforeInstall(): bool
    {
        $isLiteInstalled = (bool) (new Query())
            ->select('id')
            ->from('{{%plugins}}')
            ->where(['handle' => 'freeform'])
            ->one();

        if (!$isLiteInstalled) {
            \Craft::$app->session->setNotice(
                \Craft::t('app', 'You must install Freeform Lite before you can install Freeform Payments')
            );

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function beforeUninstall(): bool
    {
        $this->removeHookHandlers();

        IntegrationRecord::deleteAll(array('type' => IntegrationRecord::TYPE_PAYMENT_GATEWAY));

        $fieldIds = (new Query())
            ->select('id')
            ->from('{{%freeform_fields}}')
            ->where(array('type' => FieldInterface::TYPE_CREDIT_CARD_DETAILS))
            ->column();

        $fieldService = Freeform::getInstance()->fields;
        foreach ($fieldIds as $fieldId) {
            $fieldService->deleteById($fieldId);
        }

        $formService = Freeform::getInstance()->forms;
        $forms = $formService->getAllForms();
        foreach ($forms as $form) {
            try {
                $composer = $form->getComposer();
                $composer->removeProperty(Properties::PAYMENT_HASH);
                $form->layoutJson = $composer->getComposerStateJSON();
                $formService->save($form);
            } catch (FreeformException $e) {
            }
        }

        return true;
    }

    private function initControllers()
    {
        if (!\Craft::$app->request->isConsoleRequest) {
            $this->controllerMap = [
                'webhooks'      => WebhooksController::class,
                'subscriptions' => SubscriptionsController::class,
            ];
        }
    }

    private function initServices()
    {
        $this->setComponents(
            [
                'stripe'            => StripeService::class,
                'notification'      => NotificationService::class,
                'assets'            => AssetsService::class,
                'payments'          => PaymentsService::class,
                'subscriptions'     => SubscriptionsService::class,
                'subscriptionPlans' => SubscriptionPlansService::class,
            ]
        );
    }

    private function initRoutes()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $routes       = include __DIR__.'/routes.php';
                $event->rules = array_merge($event->rules, $routes);
            }
        );
    }

    private function initIntegrations()
    {
        Event::on(
            PaymentGatewaysService::class,
            PaymentGatewaysService::EVENT_FETCH_TYPES,
            function (FetchPaymentGatewayTypesEvent $event) {
                $finder = new Finder();

                $namespace = 'Solspace\FreeformPayments\Integrations\PaymentGateways';

                /** @var SplFileInfo[] $files */
                $files = $finder
                    ->name('*.php')
                    ->files()
                    ->ignoreDotFiles(true)
                    ->in(__DIR__ . '/Integrations/PaymentGateways/');

                foreach ($files as $file) {
                    $className = str_replace('.' . $file->getExtension(), '', $file->getBasename());
                    $className = $namespace . '\\' . $className;
                    $event->addType($className);
                }
            }
        );
    }

    private function initPermissions()
    {
        if (\Craft::$app->getEdition() >= \Craft::Pro) {
            Event::on(
                UserPermissions::class,
                UserPermissions::EVENT_REGISTER_PERMISSIONS,
                function (RegisterUserPermissionsEvent $event) {
                    if (!isset($event->permissions[Freeform::PERMISSION_NAMESPACE])) {
                        $event->permissions[Freeform::PERMISSION_NAMESPACE] = [];
                    }

                    $event->permissions[Freeform::PERMISSION_NAMESPACE] = array_merge(
                        $event->permissions[Freeform::PERMISSION_NAMESPACE],
                        [
                            //TODO: add permission management
                        ]
                    );
                }
            );
        }
    }

    private function initStripe()
    {
        Event::on(
            FormsService::class,
            FormsService::EVENT_RENDER_CLOSING_TAG,
            [$this->stripe, 'addFormJavascript']
        );
    }

    private function initAssets()
    {
        Event::on(
            SubmissionsController::class,
            SubmissionsController::EVENT_REGISTER_INDEX_ASSETS,
            [$this->assets, 'payments']
        );
        Event::on(
            SubmissionsController::class,
            SubmissionsController::EVENT_REGISTER_EDIT_ASSETS,
            [$this->assets, 'payments']
        );
    }

    private function initHookHandlers()
    {
        SubmissionHookHandler::registerHooks();
        FormHookHandler::registerHooks();
    }

    private function removeHookHandlers()
    {
        SubmissionHookHandler::unregisterHooks();
        FormHookHandler::unregisterHooks();
    }

    private function initTwigVariables()
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $event->sender->set('freeformPayments', FreeformPaymentsVariable::class);
            }
        );
    }
}
