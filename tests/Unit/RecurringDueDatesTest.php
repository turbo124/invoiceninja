<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\Traits\Recurring\HasRecurrence;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * @test
 *
 * @covers App\Utils\Traits\Recurring\HasRecurrence
 */
class RecurringDueDatesTest extends TestCase
{
    use HasRecurrence;

    public function testFirstDate(): void
    {
        $date = Carbon::parse('2020-02-15');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testFirstOfMonthOnFirst(): void
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testFirstOfMonthOnLast(): void
    {
        $date = Carbon::parse('2020-03-31');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-04-01', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonth(): void
    {
        $date = Carbon::parse('2020-02-15');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonthOnFirst(): void
    {
        $date = Carbon::parse('2020-02-1');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonthOnLast(): void
    {
        $date = Carbon::parse('2020-02-29');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonth(): void
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->setDayOfMonth($date, '15');

        $this->assertEquals('2020-02-15', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthInFuture(): void
    {
        $date = Carbon::parse('2020-02-16');

        $due_date = $this->setDayOfMonth($date, '15');

        $this->assertEquals('2020-03-15', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthSameDay(): void
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->setDayOfMonth($date, '1');

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow(): void
    {
        $date = Carbon::parse('2020-1-31');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow2(): void
    {
        $date = Carbon::parse('2020-02-29');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2020-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow3(): void
    {
        $date = Carbon::parse('2020-01-30');

        $due_date = $this->setDayOfMonth($date, '30');

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow4(): void
    {
        $date = Carbon::parse('2019-02-28');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2019-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow5(): void
    {
        $date = Carbon::parse('2019-1-31');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2019-02-28', $due_date->format('Y-m-d'));
    }
}
