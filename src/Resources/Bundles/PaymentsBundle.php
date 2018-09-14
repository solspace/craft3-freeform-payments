<?php

namespace Solspace\FreeformPayments\Resources\Bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PaymentsBundle extends AbstractFreeformPaymentsAssetBundle
{
    /**
     * @inheritDoc
     */
    public function getStylesheets(): array
    {
        return ['css/main.css'];
    }
}
