<?php

namespace Tests\Unit;

use App\Support\IdFormats;
use PHPUnit\Framework\TestCase;

class IdFormatsDisplayTest extends TestCase
{
    public function test_tin_is_shown_as_three_groups_of_three_and_odd_values_are_left_alone(): void
    {
        $this->assertSame('123-456-789', IdFormats::formatTin('123456789'));
        $this->assertSame('123-456-789', IdFormats::formatTin('123-456-789'));
        $this->assertSame('TIN-PENDING', IdFormats::formatTin('TIN-PENDING'));
        $this->assertSame('', IdFormats::formatTin(null));
    }

    public function test_vrn_is_shown_as_two_six_and_a_letter_and_odd_values_are_left_alone(): void
    {
        $this->assertSame('35-235223-P', IdFormats::formatVrn('35235223P'));
        $this->assertSame('35-235223-P', IdFormats::formatVrn('35-235223-p'));
        $this->assertSame('VRN-9876543', IdFormats::formatVrn('VRN-9876543'));
        $this->assertSame('', IdFormats::formatVrn(null));
    }
}
