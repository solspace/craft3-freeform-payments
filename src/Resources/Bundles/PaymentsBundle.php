<?php

namespace Solspace\FreeformPayments\Resources\Bundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PaymentsBundle extends AbstractFreeformPaymentsAssetBundle
{
    /**
     * @inheritDoc
     */
    protected function getSourcePath(): string
    {
        return '@Solspace/FreeformPayments/Resources';
    }

    /**
     * @inheritDoc
     */
    public function getScripts(): array
    {
        return [
        ];
    }

    /**
     * @inheritDoc
     */
    public function getStylesheets(): array
    {
        return ['css/main.css'];
    }
}
