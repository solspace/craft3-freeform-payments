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
use craft\web\View;
use Solspace\Freeform\Events\Forms\FormRenderEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\Form;
use Solspace\Freeform\Services\SettingsService;
use Solspace\FreeformPayments\FreeformPayments;
use Solspace\FreeformPayments\Integrations\PaymentGateways\Stripe;
use Solspace\Freeform\Library\Composer\Components\Properties\PaymentProperties;
use Solspace\Freeform\Library\Composer\Components\Page;
use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\Freeform\Library\Composer\Components\Fields\SubmitField;
use Solspace\Freeform\Library\Composer\Components\FieldInterface;

class StripeService extends Component
{
    const FIELD_GROUP_TYPES = array(FieldInterface::TYPE_CHECKBOX_GROUP, FieldInterface::TYPE_RADIO_GROUP);

    /**
     * Adds honeypot javascript to forms
     *
     * @param FormRenderEvent $event
     */
    public function addFormJavascript(FormRenderEvent $event)
    {
        $isFooterScripts = $this->getSettingsService()->isFooterScripts();
        $form            = $event->getForm();

        if ($this->hasPaymentFieldDisplayed($form)) {
            $script = $this->getStripeJavascriptScript($form);

            if ($isFooterScripts) {
                \Craft::$app->view->registerJs($script, View::POS_END);
            } else {
                $event->appendJsToOutput($script);
            }
        }
    }

    /**
     * @param Form $form
     *
     * @return string
     */
    public function getStripeJavascriptScript(Form $form): string
    {
        $paymentFields         = $form->getLayout()->getPaymentFields();
        $integrationId         = $form->getPaymentProperties()->getIntegrationId();
        $integration           = Freeform::getInstance()->paymentGateways->getIntegrationById($integrationId);
        $publicKey             = $integration->settings[Stripe::SETTING_PUBLIC_KEY];
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

        $script =<<<JS
(function() {
    var zeroDecimalCurrencies = {$zeroDecimalCurrencies};
    var id                    = '{$paymentField->getIdAttribute()}';
    var form                  = document.getElementById('{$form->getAnchor()}').parentElement;
    var ready                 = false;

    var stripe, elements, cardNumber, cardExpiry, cardCvc;

    var displayStripeError = function(message) {
        if (window.renderErrors === undefined) {
            alert(message);

            return;
        }

        if (window.removeMessages !== undefined) {
            removeMessages(form);
        }

        renderFormErrors([message], form);
    }

    var handleSubmit = function(e) {
        if (ready) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation && e.stopImmediatePropagation();

        var additionalData = {
            type: 'card',
            currency: {$values["currency"]},
            usage: '{$usage}',
        };

        if (additionalData.amount) {
            var multiplier        = zeroDecimalCurrencies.indexOf(additionalData.currency) >= 0 ? 1 :  100;
            additionalData.amount = {$values['amount']} * multiplier;
        }

        stripe.createSource(cardNumber, additionalData).then(function(result) {
            if (result.error) {
                console.log(result.error);
                displayStripeError(result.error.message + (result.error.param ? ' - ' + result.error.param : ''));
                form.querySelector('[name="{$submitName}"]').disabled = false;

                return;
            }
            document.getElementById(id).value = result.source.id;
            ready = true;

            if (form.dispatchEvent(new Event('submit', {cancelable: true}))) {
                form.submit();
            }
        });
        return false;
    };

    var handleReset = function () {
        cardNumber && cardNumber.clear();
        cardCvc && cardCvc.clear();
        cardExpiry && cardExpiry.clear();
    }

    form.addEventListener('reset', handleReset, false);
    form.addEventListener('submit', handleSubmit, false);

    var script = document.createElement('script');
    script.onload = function() {
        var numberDivId       = id + '_card_number';
        var cvcDivId          = id + '_card_cvc';
        var expiryDivId       = id + '_card_expiry';
        var numberDiv         = document.getElementById(numberDivId);
        var cvcDiv            = document.getElementById(cvcDivId);
        var expiryDiv         = document.getElementById(expiryDivId);
        var numberPlaceholder = numberDiv.attributes.placeholder;
        var expiryPlaceholder = expiryDiv.attributes.placeholder;
        var cvcPlaceholder    = cvcDiv.attributes.placeholder;

        stripe     = Stripe('{$publicKey}');
        elements   = stripe.elements();
        cardNumber = elements.create('cardNumber', {
            placeholder: numberPlaceholder ? numberPlaceholder.value : '',
        });
        cardExpiry = elements.create('cardExpiry', {
            placeholder: expiryPlaceholder ? expiryPlaceholder.value : '',
        });
        cardCvc    = elements.create('cardCvc', {
            placeholder: cvcPlaceholder ? cvcPlaceholder.value : '',
        });

        cardNumber.mount('#' + numberDivId);
        cardExpiry.mount('#' + expiryDivId);
        cardCvc.mount('#' + cvcDivId);

        cardNumber.on('change', function() {
            ready = false;
        });
    };
    script.src = "https://js.stripe.com/v3/";
    document.body.appendChild(script);
})();
JS;

        return $script;
    }

    private function getPaymentFieldJSValues($form)
    {
        $props          = $form->getPaymentProperties();
        $staticAmount   = $props->getAmount() ? "'{$props->getAmount()}'" : "null";
        $staticCurrency = $props->getCurrency() ? "'{$props->getCurrency()}'" : "null";
        $mapping        = $props->getPaymentFieldMapping();

        if (!isset($mapping['amount']) && !isset($mapping['currency'])) {
            return array(
                'amount'   => $staticAmount,
                'currency' => $staticCurrency,
            );
        }

        //process 3 cases, fixed value, value on same page, value on different page
        $pageFields = $form->getCurrentPage()->getFields();
        //TODO: does not work for radio
        foreach ($pageFields as $pageField) {
            //TODO: get name from constant
            if (in_array($pageField->getType(), self::FIELD_GROUP_TYPES)) {
                $valueGetter = "form.querySelector('[name={$pageField->getHandle()}]:checked').value";
            } else {
                $valueGetter = "document.getElementById('{$pageField->getIdAttribute()}').value";
            }

            if (isset($mapping['amount']) && $mapping['amount'] == $pageField->getHandle()) {
                $elementAmount = $valueGetter;
            }
            if (isset($mapping['currency']) && $mapping['currency'] == $pageField->getHandle()) {
                $elementCurrency = $valueGetter;
            }
        }
        if (isset($mapping['amount'])) {
            $dynamicAmount = "'{$form->get($mapping['amount'])->getValue()}'";
        }
        if (isset($mapping['currency'])) {
            $dynamicCurrency = "'{$form->get($mapping['currency'])->getValue()}'";
        }

        return array(
            'amount'   => $elementAmount ?? $dynamicAmount ?? $staticAmount,
            'currency' => $elementCurrency ?? $dynamicCurrency ?? $staticCurrency,
        );
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
