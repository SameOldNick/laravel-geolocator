<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * @internal
 */
class ScheduleAutoUpdateTest extends TestCase
{
    /**
     * Resolve the scheduler, which triggers the package's after-resolving hook.
     */
    protected function schedule(): Schedule
    {
        return $this->app->make(Schedule::class);
    }

    /**
     * Ensure the update command is scheduled weekly by default.
     */
    public function test_update_command_is_scheduled_by_default(): void
    {
        $events = $this->schedule()->events();

        $this->assertCount(1, $events);
        $this->assertStringContainsString('geolocation:update-iplocationdb', $events[0]->command);
        $this->assertSame('0 0 * * 0', $events[0]->expression);
        $this->assertSame('Update IPLocationDB geolocation databases', $events[0]->description);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    /**
     * Ensure nothing is scheduled when auto update is switched off.
     */
    public function test_nothing_is_scheduled_when_auto_update_is_disabled(): void
    {
        config()->set('geolocator.drivers.iplocationdb.update.auto_update.enabled', false);

        $this->assertCount(0, $this->schedule()->events());
    }

    /**
     * Ensure a missing auto update configuration does not throw and schedules nothing.
     */
    public function test_nothing_is_scheduled_when_configuration_is_missing(): void
    {
        config()->set('geolocator.drivers.iplocationdb.update.auto_update', []);

        $this->assertCount(0, $this->schedule()->events());
    }

    /**
     * Ensure nothing is scheduled when the frequency is empty.
     */
    public function test_nothing_is_scheduled_when_frequency_is_empty(): void
    {
        config()->set('geolocator.drivers.iplocationdb.update.auto_update.enabled', true);
        config()->set('geolocator.drivers.iplocationdb.update.auto_update.frequency', '');

        $this->assertCount(0, $this->schedule()->events());
    }

    /**
     * Ensure the configured frequency maps onto the expected cron expression.
     */
    #[DataProvider('frequencyProvider')]
    public function test_frequency_maps_to_cron_expression(string $frequency, string $expression): void
    {
        config()->set('geolocator.drivers.iplocationdb.update.auto_update.enabled', true);
        config()->set('geolocator.drivers.iplocationdb.update.auto_update.frequency', $frequency);

        $events = $this->schedule()->events();

        $this->assertCount(1, $events);
        $this->assertSame($expression, $events[0]->expression);
    }

    /**
     * Frequencies and the cron expression each one should produce.
     */
    public static function frequencyProvider(): array
    {
        return [
            'hourly' => ['hourly', '0 * * * *'],
            'daily' => ['daily', '0 0 * * *'],
            'weekly' => ['weekly', '0 0 * * 0'],
            'monthly' => ['monthly', '0 0 1 * *'],
            'raw cron expression' => ['*/15 3 * * 1-5', '*/15 3 * * 1-5'],
        ];
    }
}
