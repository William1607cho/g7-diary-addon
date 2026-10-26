<?php

namespace Plugins\G7\Diary\Addon\Upgrades;

use App\Contracts\Extension\UpgradeStepInterface;
use App\Extension\UpgradeContext;
use Plugins\G7\Diary\Addon\Support\DiaryCategories;

/**
 * 0.2.0-dev upgrade step — default category 3 "개빡치는 날" becomes "분노일기" (D63).
 *
 * Only while the stored name is still the old default; a name the site changed is kept.
 * New installs get the new name from the seed. Safe to run more than once.
 * (The step version '0.2.0-dev' is below 0.2.0, so updates from 0.1.x to 0.2.0 run it too.)
 */
class Upgrade_0_2_0_dev implements UpgradeStepInterface
{
    public function run(UpgradeContext $context): void
    {
        $renamed = DiaryCategories::renameIfDefault(3, '개빡치는 날', '분노일기');

        $context->logger->info('[g7-diary-addon 0.2.0-dev] category 3 '.($renamed ? 'renamed to the new default' : 'left as it is (already renamed or changed by the site)'));
    }
}
