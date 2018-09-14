<?php

namespace Solspace\FreeformPayments\Controllers;

use Solspace\FreeformPayments\FreeformPayments;

//TODO: create abstract controller
class SubscriptionsController extends BasePaymentsController
{
    public $enableCsrfValidation = false;

    protected $allowAnonymous = true;

    public function actionCancel(int $id, string $validationKey): string
    {
        //TODO: encrypt id
        //TODO: expose json?
        $subscription = $this->getPaymentsSubscriptionsService()->getById($id);
        if (!$subscription) {
            return $this->renderResponse(FreeformPayments::t('Subscription not found'));
        }

        $generatedKey = sha1($subscription->resourceId);

        if ($validationKey != $generatedKey) {
            return $this->renderResponse(FreeformPayments::t('Subscription not found'));
        }

        $result = $subscription->getIntegration()->cancelSubscription($subscription->resourceId);
        if ($result !== true) {
            return $this->renderResponse(FreeformPayments::t('Error during subscription cancellation'));
        }

        return $this->renderResponse();

    }

    protected function renderResponse(string $error = ''): string
    {
        $isAjax = \Craft::$app->getRequest()->isAjax;

        if ($error) {
            return $isAjax ? $this->asJson(['success' => false, 'error' => $error]) : $error;
        }

        return $isAjax ? $this->asJson(['success' => true]) : FreeformPayments::t('Unsubscribed successfully');
    }
}
