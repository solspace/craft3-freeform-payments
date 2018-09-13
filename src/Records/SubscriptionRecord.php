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

namespace Solspace\FreeformPayments\Records;

use craft\db\ActiveRecord;
use yii\db\ActiveQuery;
use Solspace\Freeform\Elements\Submission;

/**
 * @property string $id
 * @property string $submissionId
 * @property string $planId
 * @property string $resourceId
 * @property string $planName
 * @property string $amount
 * @property string $currency
 * @property string $interval
 * @property string $last4
 * @property string $status
 * @property string $errorCode
 * @property string $errorMessage
 */
class SubscriptionRecord extends ActiveRecord
{
    const TABLE = '{{%freeform_payments_subscriptions}}';

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|Submission
     */
    public function getSubmission(): ActiveQuery
    {
        return $this->hasOne(Submission::TABLE, ['submissionId' => 'id']);
    }

    /**
     * @return ActiveQuery|SubscriptionPlanRecord
     */
    public function getPlan(): ActiveQuery
    {
        return $this->hasOne(SubscriptionPlanRecord::TABLE, ['planId' => 'id']);
    }
}
