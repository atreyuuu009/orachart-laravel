<?php

namespace Tests\Unit;

use App\Support\SlotCalculator as S;
use PHPUnit\Framework\TestCase;

class SlotCalculatorTest extends TestCase
{
    public function test_generates_half_hour_slots_that_fit_before_closing(): void
    {
        $t = S::startTimes([[540, 720]], 30, 60, 0, 0, []);   // 09:00-12:00, 60-min treatment
        $this->assertSame([540, 570, 600, 630, 660], $t);       // last start 11:00
    }

    public function test_after_buffer_blocks_the_next_start(): void
    {
        // Existing 09:00-10:00 booking stored with 10-min buffer => busy 540-610
        $t = S::startTimes([[540, 720]], 30, 30, 0, 10, [[540, 610]]);
        $this->assertSame(630, $t[0]);                          // 09:00-10:00 blocked; first free start is 10:30
        $this->assertNotContains(600, $t);
    }

    public function test_treatment_cannot_overlap_a_later_booking(): void
    {
        // 90-min treatment, someone already booked 10:30-11:00
        $t = S::startTimes([[540, 720]], 30, 90, 0, 0, [[630, 660]]);
        $this->assertSame([540], $t);                           // 09:00-10:30 fits exactly; 09:30+ runs into 10:30; 11:00 won't fit
    }

    public function test_split_shift_windows_and_intersection(): void
    {
        $this->assertSame([[600, 660]], S::intersect([[540, 660]], [[600, 1080]]));
        $this->assertSame([], S::intersect([[540, 600]], [[600, 700]]));
        $t = S::startTimes([[540, 600], [780, 840]], 30, 30, 0, 0, []);
        $this->assertSame([540, 570, 780, 810], $t);
    }

    public function test_formatting(): void
    {
        $this->assertSame('09:30', S::fmt(570));
        $this->assertSame('9:30 AM', S::label(570));
        $this->assertSame('12:00 PM', S::label(720));
        $this->assertSame('12:15 AM', S::label(15));
        $this->assertSame(1050, S::parse('17:30:00'));
    }
}
