<?php

namespace Solspace\FreeformPayments\migrations;

use craft\db\Query;
use Solspace\Commons\Migrations\ForeignKey;
use Solspace\Commons\Migrations\StreamlinedInstallMigration;
use Solspace\Commons\Migrations\Table;

/**
 * Install migration.
 */
class Install extends StreamlinedInstallMigration
{
    /**
     * @return Table[]
     */
    protected function defineTableData(): array
    {
        return [
            (new Table('freeform_payments_subscription_plans'))
                ->addField('id', $this->primaryKey())
                ->addField('integrationId', $this->integer()->notNull())
                ->addField('resourceId', $this->string(255)->append('BINARY'))
                ->addField('name', $this->string(255))
                ->addField('status', $this->string(20))
                ->addForeignKey('integrationId', 'freeform_integrations', 'id', ForeignKey::CASCADE),

            (new Table('freeform_payments_payments'))
                ->addField('id', $this->primaryKey())
                ->addField('integrationId', $this->integer()->notNull())
                ->addField('submissionId', $this->integer()->notNull())
                ->addField('subscriptionId', $this->integer())
                ->addField('resourceId', $this->string(50)->append('BINARY'))
                ->addField('amount', $this->float(2))
                ->addField('currency', $this->string(3))
                ->addField('last4', $this->smallInteger())
                ->addField('status', $this->string(20))
                ->addField('errorCode', $this->string(20))
                ->addField('errorMessage', $this->string(255))
                ->addForeignKey('submissionId', 'freeform_submissions', 'id', ForeignKey::CASCADE)
                ->addForeignKey('subscriptionId', 'freeform_payments_subscriptions', 'id', ForeignKey::CASCADE)
                ->addForeignKey('integrationId', 'freeform_integrations', 'id', ForeignKey::CASCADE)
                ->addIndex(['integrationId', 'resourceId'], true),

            (new Table('freeform_payments_subscriptions'))
                ->addField('id', $this->primaryKey())
                ->addField('integrationId', $this->integer()->notNull())
                ->addField('submissionId', $this->integer()->notNull())
                ->addField('planId', $this->integer()->notNull())
                ->addField('resourceId', $this->string(50)->append('BINARY'))
                ->addField('amount', $this->float(2))
                ->addField('currency', $this->string(3))
                ->addField('interval', $this->string(20))
                ->addField('last4', $this->smallInteger())
                ->addField('status', $this->string(20))
                ->addField('errorCode', $this->string(20))
                ->addField('errorMessage', $this->string(255))
                ->addForeignKey('submissionId', 'freeform_submissions', 'id', ForeignKey::CASCADE)
                ->addForeignKey('integrationId', 'freeform_integrations', 'id', ForeignKey::CASCADE)
                ->addForeignKey('planId', 'freeform_payments_subscription_plans', 'id', ForeignKey::CASCADE)
                ->addIndex(['integrationId', 'resourceId'], true),
        ];
    }
}
