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

namespace Solspace\FreeformPayments\Services;

use craft\base\Component;
use Solspace\FreeformPayments\Resources\Bundles\PaymentsBundle;
use Solspace\Freeform\Events\Assets\RegisterEvent;

class AssetsService extends Component
{
    public function payments(RegisterEvent $event) {
        $event->getView()->registerAssetBundle(PaymentsBundle::class);
    }
}
