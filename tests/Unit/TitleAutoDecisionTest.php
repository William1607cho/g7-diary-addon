<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Diary\Addon\Support\TitleAutoDecision;

/**
 * title_auto decision table.
 */
class TitleAutoDecisionTest extends TestCase
{
    public function test_create_blank_title_fills_and_flags_auto(): void
    {
        foreach ([null, '', '   ', "\u{00A0}"] as $blank) {
            $this->assertSame(['fill' => true, 'flag' => 1], TitleAutoDecision::forCreate($blank));
        }
    }

    public function test_create_user_title_keeps_and_flags_manual(): void
    {
        $this->assertSame(['fill' => false, 'flag' => 0], TitleAutoDecision::forCreate('내 제목'));
    }

    public function test_update_without_title_key_changes_nothing(): void
    {
        $this->assertSame(['fill' => false, 'flag' => null], TitleAutoDecision::forUpdate(false, null, '기존'));
    }

    public function test_update_blank_title_refills_automatically(): void
    {
        $this->assertSame(['fill' => true, 'flag' => 1], TitleAutoDecision::forUpdate(true, null, '기존'));
        $this->assertSame(['fill' => true, 'flag' => 1], TitleAutoDecision::forUpdate(true, '', '기존'));
    }

    public function test_update_changed_title_becomes_manual(): void
    {
        $this->assertSame(['fill' => false, 'flag' => 0], TitleAutoDecision::forUpdate(true, '새 제목', '기존'));
    }

    public function test_update_same_title_keeps_flag(): void
    {
        // An auto title sent back unchanged by the form stays auto (flag untouched),
        // a manual title sent back unchanged stays manual.
        $this->assertSame(['fill' => false, 'flag' => null], TitleAutoDecision::forUpdate(true, '기존', '기존'));
    }
}
