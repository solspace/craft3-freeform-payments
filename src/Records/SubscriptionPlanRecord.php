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
use Solspace\Freeform\Records\IntegrationRecord;
use yii\db\ActiveQuery;

/**
 * @property string $id
 * @property string $integrationId
 * @property string $resourceId
 * @property string $name
 */
class SubscriptionPlanRecord extends ActiveRecord
{
    const TABLE = '{{%freeform_payments_subscription_plans}}';

    /**
     * @return string
     */
    public static function tableName(): string
    {
        return self::TABLE;
    }

    /**
     * @return ActiveQuery|IntegrationRecord
     */
    public function getIntegration(): ActiveQuery
    {
        return $this->hasOne(IntegrationRecord::TABLE, ['integrationId' => 'id']);
    }
}
