<?php

namespace Solspace\FreeformPayments\Library\ElementHookHandlers;

use Solspace\Freeform\Events\Forms\SaveEvent;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;
use Solspace\Freeform\Services\FormsService;
use Solspace\FreeformPayments\FreeformPayments;
use yii\base\Event;

class FormHookHandler
{
    /**
     * Register hooks on Submission element handled by this class
     *
     * @return void
     */
    public static function registerHooks()
    {
        Event::on(
            FormsService::class,
            FormsService::EVENT_BEFORE_SAVE,
            [self::class, 'validate']
        );
    }

    /**
     * Unregisters all previously registered hooks
     *
     * @return void
     */
    public static function unregisterHooks()
    {
        Event::off(
            FormsService::class,
            FormsService::EVENT_BEFORE_SAVE,
            [self::class, 'validate']
        );
    }

    /**
     * Handler for SaveEvent from Form model
     *
     * @param SaveEvent $event
     *
     * @return void
     */
    public static function validate(SaveEvent $event)
    {
        $formsService = $event->sender;

        $formModel     = $event->getModel();
        $paymentFields = $formModel->getLayout()->getPaymentFields();
        if (!$paymentFields) {
            return;
        }
        $paymentField = $paymentFields[0];

        $paymentProperties = $formModel->getComposer()->getForm()->getPaymentProperties();

        $attribute = $paymentField->getHandle();
        if (!$paymentProperties->getIntegrationId()) {
            $formModel->addError($attribute, FreeformPayments::t('Payment gateway is not configured!'));
        }

        $paymentType = $paymentProperties->getPaymentType();
        if (!$paymentType) {
            $formModel->addError($attribute, FreeformPayments::t('Payment type is not configured!'));
        }

        $paymentFieldMapping = $paymentProperties->getPaymentFieldMapping();
        if ($paymentType != PaymentProperties::PAYMENT_TYPE_PREDEFINED_SUBSCRIPTION) {
            if (!$paymentProperties->getAmount()
                && !isset($paymentFieldMapping[PaymentProperties::FIELD_AMOUNT])
            ) {
                $formModel->addError($attribute, FreeformPayments::t('Payment amount is not configured!'));
            }
        } else {
            //if there are no plans to select from and form is not saved
            //user will end up without ability to create plan
            //so we skip this validation if form is not yet saved
            if ($formModel->id
                && !$paymentProperties->getPlan()
                && !isset($paymentFieldMapping[PaymentProperties::FIELD_PLAN])
            ) {
                $formModel->addError($attribute, FreeformPayments::t('Subscription plan is not configured!'));
            }
        }
    }
}
