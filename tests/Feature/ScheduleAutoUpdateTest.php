<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The console kernel resolves the scheduler while the application boots, so these tests extend
 * Testbench directly: they need no database, and unlike the package TestCase they can safely
 * reboot the application (see scheduleWith()).
 *
 * @internal
 */
class ScheduleAutoUpdateTest extends Orchestra
{
    use WithWorkbench;

    /**
     * Configuration to apply while the test application is created.
     *
     * @var array<string, mixed>
     */
    protected static array $config = [];

    /**
     * Apply the pending configuration as the application is created.
     *
     * The scheduler is resolved during boot, which fires the package's after-resolving hook before a
     * test body can set any configuration. Applying it here keeps the assertions deterministic no
     * matter whether an earlier test has already booted the console kernel.
     */
    protected function defineEnvironment($app)
    {
        foreach (static::$config as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * Reboot the application with the given configuration and resolve the scheduler.
     *
     * @param  array<string, mixed>  $config
     */
    protected function scheduleWith(array $config = []): Schedule
    {
        static::$config = $config;

        $this->refreshApplication();

        return $this->app->make(Schedule::class);
    }

    /**
     * Clear the pending configuration so it cannot leak into another test.
     */
    protected function tearDown(): void
    {
        static::$config = [];

        parent::tearDown();
    }

    /**
     * Ensure the update command is scheduled weekly by default.
     */
    public function test_update_command_is_scheduled_by_default(): void
    {
        $events = $this->scheduleWith()->events();

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
        $events = $this->scheduleWith([
            'geolocator.drivers.iplocationdb.update.auto_update.enabled' => false,
        ])->events();

        $this->assertCount(0, $events);
    }

    /**
     * Ensure a missing auto update configuration does not throw and schedules nothing.
     */
    public function test_nothing_is_scheduled_when_configuration_is_missing(): void
    {
        $events = $this->scheduleWith([
            'geolocator.drivers.iplocationdb.update.auto_update' => [],
        ])->events();

        $this->assertCount(0, $events);
    }

    /**
     * Ensure nothing is scheduled when the frequency is empty.
     */
    public function test_nothing_is_scheduled_when_frequency_is_empty(): void
    {
        $events = $this->scheduleWith([
            'geolocator.drivers.iplocationdb.update.auto_update.enabled' => true,
            'geolocator.drivers.iplocationdb.update.auto_update.frequency' => '',
        ])->events();

        $this->assertCount(0, $events);
    }

    /**
     * Ensure the configured frequency maps onto the expected cron expression.
     */
    #[DataProvider('frequencyProvider')]
    public function test_frequency_maps_to_cron_expression(string $frequency, string $expression): void
    {
        $events = $this->scheduleWith([
            'geolocator.drivers.iplocationdb.update.auto_update.enabled' => true,
            'geolocator.drivers.iplocationdb.update.auto_update.frequency' => $frequency,
        ])->events();

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
