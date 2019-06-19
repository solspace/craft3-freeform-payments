<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2017, Solspace, Inc.
 * @link          https://solspace.com/craft/freeform
 * @license       https://solspace.com/software/license-agreement
 */

namespace Solspace\FreeformPayments\Services;

use craft\base\Component;
use Solspace\Freeform\Events\Forms\FormRenderEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\Freeform\Library\Composer\Components\FieldInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\SubmitField;
use Solspace\Freeform\Library\Composer\Components\Form;
use Solspace\Freeform\Library\Composer\Components\Page;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;
use Solspace\Freeform\Services\SettingsService;
use Solspace\FreeformPayments\Integrations\PaymentGateways\Stripe;

class StripeService extends Component
{
    const FIELD_GROUP_TYPES = [FieldInterface::TYPE_CHECKBOX_GROUP, FieldInterface::TYPE_RADIO_GROUP];

    /**
     * Adds honeypot javascript to forms
     *
     * @param FormRenderEvent $event
     */
    public function addFormJavascript(FormRenderEvent $event)
    {
        $form = $event->getForm();

        if ($this->hasPaymentFieldDisplayed($form)) {
            $ffPaymentsPath = \Yii::getAlias('@freeform-payments');

            $script = $this->getStripeJavascriptScript($form);
            $event->appendJsToOutput($script);

            $stripeJs = file_get_contents($ffPaymentsPath . '/Resources/js/form/stripe-submit.js');
            $event->appendJsToOutput($stripeJs);
        }
    }

    /**
     * @param Form $form
     *
     * @return string
     */
    public function getStripeJavascriptScript(Form $form): string
    {
        $paymentFields = $form->getLayout()->getPaymentFields();
        $integrationId = $form->getPaymentProperties()->getIntegrationId();
        $integration   = Freeform::getInstance()->paymentGateways->getIntegrationById($integrationId);

        $publicKey             = $integration->getIntegrationObject()->getPublicKey();
        $values                = $this->getPaymentFieldJSValues($form);
        $props                 = $form->getPaymentProperties();
        $zeroDecimalCurrencies = json_encode(Stripe::ZERO_DECIMAL_CURRENCIES);
        $isSubscription        = $props->getPaymentType() !== PaymentProperties::PAYMENT_TYPE_SINGLE;
        $usage                 = $isSubscription ? 'reusable' : 'single_use';
        $submitName            = SubmitField::SUBMIT_INPUT_NAME;

        if (count($paymentFields) == 0) {
            return '';
        }

        $paymentField = $paymentFields[0];

        $script = <<<JS
window.ffStripeValues = {
  zeroDecimalCurrencies: {$zeroDecimalCurrencies},
  id: "{$paymentField->getIdAttribute()}",
  formAnchor: "{$form->getAnchor()}",
  currencySelector: {$values['currencySelector']},
  currencyFixed: {$values['currencyFixed']},
  usage: "{$usage}",
  amountSelector: {$values['amountSelector']},
  amountFixed: {$values['amountFixed']},
  submitName: "{$submitName}",
  publicKey: "{$publicKey}",
};
JS;

        return $script;
    }

    /**
     * @param Form $form
     *
     * @return array
     */
    private function getPaymentFieldJSValues(Form $form): array
    {
        $props          = $form->getPaymentProperties();
        $staticAmount   = $props->getAmount() ? "'{$props->getAmount()}'" : null;
        $staticCurrency = $props->getCurrency() ? "'{$props->getCurrency()}'" : null;
        $mapping        = $props->getPaymentFieldMapping();

        if (!isset($mapping['amount']) && !isset($mapping['currency'])) {
            return [
                'amountSelector'   => "'null'",
                'amountFixed'      => $staticAmount,
                'currencySelector' => "'null'",
                'currencyFixed'    => $staticCurrency,
            ];
        }

        $elementAmount = $elementCurrency = $dynamicAmount = $dynamicCurrency = null;
        //process 3 cases, fixed value, value on same page, value on different page
        $pageFields = $form->getCurrentPage()->getFields();
        foreach ($pageFields as $pageField) {
            if (in_array($pageField->getType(), self::FIELD_GROUP_TYPES, true)) {
                $selector = "'[name={$pageField->getHandle()}]:checked'";
            } else {
                $selector = "'#{$pageField->getIdAttribute()}'";
            }

            if (isset($mapping['amount']) && $mapping['amount'] == $pageField->getHandle()) {
                $elementAmount = $selector;
            }

            if (isset($mapping['currency']) && $mapping['currency'] == $pageField->getHandle()) {
                $elementCurrency = $selector;
            }
        }

        if (isset($mapping['amount'])) {
            $dynamicAmount = "'{$form->get($mapping['amount'])->getValue()}'";
        }
        if (isset($mapping['currency'])) {
            $dynamicCurrency = "'{$form->get($mapping['currency'])->getValue()}'";
        }

        return [
            'amountSelector'   => $elementAmount ?? $dynamicAmount ?? "'null'",
            'amountFixed'      => $elementAmount || $dynamicAmount ? "'null'" : $staticAmount,
            'currencySelector' => $elementCurrency ?? $dynamicCurrency ?? $staticCurrency,
            'currencyFixed'    => $elementCurrency || $dynamicCurrency ? "'null'" : $staticCurrency,
        ];
    }

    /**
     * @return SettingsService
     */
    private function getSettingsService(): SettingsService
    {
        return Freeform::getInstance()->settings;
    }

    private function hasPaymentFieldDisplayed(Form $form): bool
    {
        $paymentFields    = $form->getLayout()->getPaymentFields();
        $hasPaymentFields = count($paymentFields) > 0;

        if (count($paymentFields) == 0) {
            return false;
        }

        $paymentField = $paymentFields[0];

        return $this->isFieldOnPage($paymentField, $form->getCurrentPage());
    }

    private function isFieldOnPage(AbstractField $field, Page $page): bool
    {
        $pageFields  = $page->getFields();
        $fieldHandle = $field->getHandle();

        foreach ($pageFields as $pageField) {
            if ($fieldHandle == $pageField->getHandle()) {
                return true;
            }
        }

        return false;
    }
}
