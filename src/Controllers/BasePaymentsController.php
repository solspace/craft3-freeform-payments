<?php

namespace Solspace\FreeformPayments\Controllers;

use Solspace\Freeform\Controllers\BaseController;
use Solspace\FreeformPayments\FreeformPayments;
use Solspace\FreeformPayments\Services\StripeService;
use Solspace\FreeformPayments\Services\NotificationService;
use Solspace\FreeformPayments\Services\PaymentsService;
use Solspace\FreeformPayments\Services\SubscriptionsService;

class BasePaymentsController extends BaseController
{
    /**
     * @return StripeService
     */
    protected function getPaymentsStripeService() : StripeService
    {
        return FreeformPayments::getInstance()->stripe;
    }

    /**
     * @return NotificationService
     */
    protected function getPaymentsNotificationService(): NotificationService
    {
        return FreeformPayments::getInstance()->notification;
    }

    /**
     * @return PaymentsService
     */
    protected function getPaymentsPaymentsService(): PaymentsService
    {
        return FreeformPayments::getInstance()->payments;
    }

    /**
     * @return SubscriptionsService
     */
    protected function getPaymentsSubscriptionsService(): SubscriptionsService
    {
        return FreeformPayments::getInstance()->subscriptions;
    }
}
