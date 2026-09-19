<?php

namespace SameOldNick\Geolocator;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use SameOldNick\Geolocator\Contracts\Geolocator;
use SameOldNick\Geolocator\Support\IPAddressHelper;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Geolocate::class, function ($app) {
            return new Geolocate($app);
        });

        $this->app->bind(Drivers\IPLocationDB\Updater::class, function ($app) {
            return new Drivers\IPLocationDB\Updater($app['config']->get('geolocation.drivers.iplocationdb'));
        });

        $this->app->alias(Geolocate::class, 'geolocate');
        $this->app->alias(Geolocate::class, Geolocator::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/geolocator.php' => config_path('geolocator.php'),
        ], 'geolocator-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\UpdateIPLocationDB::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule, $app) {
            $config = config('geolocation.drivers.iplocationdb.update.auto_update', []);

            if (! $config['enabled'] || empty($config['frequency'])) {
                return;
            }

            $schedule->command(Commands\UpdateIPLocationDB::class)->tap(function ($event) use ($config) {
                $event->description = 'Update IPLocationDB geolocation databases';

                match ($config['frequency']) {
                    'hourly' => $event->hourly(),
                    'daily' => $event->daily(),
                    'weekly' => $event->weekly(),
                    'monthly' => $event->monthly(),
                    default => $event->cron($config['frequency']),
                };
            })->withoutOverlapping();
        });

        Request::macro('geolocate', function ($default = '0.0.0.0') {
            $geolocate = app(Geolocate::class);

            // Determine the client IP address, ignoring private/internal IPs
            $ip = Arr::first($this->getClientIps(), function ($ip) {
                return ! IPAddressHelper::isPrivateIPAddress($ip);
            }, $this->ip());

            return $geolocate->lookup($ip ?? $default);
        });
    }
}
