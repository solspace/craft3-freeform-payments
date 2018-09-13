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

namespace Solspace\FreeformPayments\Variables;

use Solspace\FreeformPayments\FreeformPayments;
use Solspace\FreeformPayments\Models\PaymentModel;
use Solspace\FreeformPayments\Services\PaymentsService;

class FreeformPaymentsVariable
{
    /**
     * @param string|int $submissionId
     *
     * @return null|PaymentModel
     */
    public function payments($submissionId)
    {
        return $this->getPaymentsService()->getPaymentDetails($submissionId);
    }

    /**
     * Returns payments service
     *
     * @return PaymentsService
     */
    private function getPaymentsService()
    {
        return FreeformPayments::getInstance()->payments;
    }
}
