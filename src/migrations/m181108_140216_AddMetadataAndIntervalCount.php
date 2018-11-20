<?php

namespace Solspace\FreeformPayments\migrations;

use Craft;
use craft\db\Migration;

/**
 * m181108_140216_AddMetadataAndIntervalCount migration.
 */
class m181108_140216_AddMetadataAndIntervalCount extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn(
            '{{%freeform_payments_payments}}',
            'metadata',
            $this->mediumText()
        );

        $this->addColumn(
            '{{%freeform_payments_subscriptions}}',
            'metadata',
            $this->mediumText()
        );

        $this->addColumn(
            '{{%freeform_payments_subscriptions}}',
            'intervalCount',
            $this->smallInteger()->null()
        );
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181108_140216_AddMetadataAndIntervalCount cannot be reverted.\n";
        return false;
    }
}
