<?php

namespace Solspace\FreeformPayments\migrations;

use craft\db\Migration;

/**
 * m190402_082139_RemoveBinaryFromResourceIds migration.
 */
class m190402_082139_RemoveBinaryFromResourceIds extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn(
            '{{%freeform_payments_subscription_plans}}',
            'resourceId',
            $this->string(255)
        );

        $this->alterColumn(
            '{{%freeform_payments_payments}}',
            'resourceId',
            $this->string(50)
        );

        $this->alterColumn(
            '{{%freeform_payments_subscriptions}}',
            'resourceId',
            $this->string(50)
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->alterColumn(
            '{{%freeform_payments_subscription_plans}}',
            'resourceId',
            $this->string(255)->append('BINARY')
        );

        $this->alterColumn(
            '{{%freeform_payments_payments}}',
            'resourceId',
            $this->string(50)->append('BINARY')
        );

        $this->alterColumn(
            '{{%freeform_payments_subscriptions}}',
            'resourceId',
            $this->string(50)->append('BINARY')
        );

        return true;
    }
}
