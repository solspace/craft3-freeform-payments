<?php

namespace Solspace\FreeformPayments\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use Solspace\FreeformPayments\Integrations\PaymentGateways\Stripe;

/**
 * m181116_161041_SwitchToLiveAndTestKeysForStripe migration.
 */
class m181116_161041_SwitchToLiveAndTestKeysForStripe extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $rows = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%freeform_integrations}}')
            ->where([
                'type' => 'payment_gateway',
                'class' => Stripe::class,
            ])
            ->all();

        foreach ($rows as $row) {
            $id = $row['id'];
            $settings = json_decode($row['settings'], true);

            $settings['live_mode'] = '';
            if (isset($settings['public_key'])) {
                $settings['public_key_live'] = $settings['public_key_test'] = $settings['public_key'];
                unset($settings['public_key']);
            }

            if (isset($settings['secret_key'])) {
                $settings['secret_key_live'] = $settings['secret_key_test'] = $settings['secret_key'];
                unset($settings['secret_key']);
            }

            $this->update(
                '{{%freeform_integrations}}',
                ['settings' => json_encode($settings)],
                ['id' => $id]
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $rows = (new Query())
            ->select(['id', 'settings'])
            ->from('{{%freeform_integrations}}')
            ->where([
                'type' => 'payment_gateway',
                'class' => Stripe::class,
            ])
            ->all();

        foreach ($rows as $row) {
            $id = $row['id'];
            $settings = json_decode($row['settings'], true);

            $settings['live_mode'] = '';
            if (isset($settings['public_key_test'])) {
                $settings['public_key'] = $settings['public_key_test'];
                unset($settings['public_key_test'], $settings['public_key_live']);
            }

            if (isset($settings['secret_key_test'])) {
                $settings['secret_key'] = $settings['secret_key_test'];
                unset($settings['secret_key_test'], $settings['secret_key_live']);
            }

            $this->update(
                '{{%freeform_integrations}}',
                ['settings' => json_encode($settings)],
                ['id' => $id]
            );
        }

        return true;
    }
}
