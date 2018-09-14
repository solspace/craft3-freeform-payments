<?php

namespace Solspace\FreeformPayments\Resources\Bundles;

use Solspace\Commons\Resources\CpAssetBundle;

abstract class AbstractFreeformPaymentsAssetBundle extends CpAssetBundle
{
    /**
     * @inheritDoc
     */
    protected function getSourcePath(): string
    {
        return '@Solspace/FreeformPayments/Resources';
    }
}
