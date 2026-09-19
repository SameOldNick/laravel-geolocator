<?php

namespace SameOldNick\Geolocator;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schedule;
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

        $this->scheduleUpdateCommand();
        $this->registerMacros();
    }

    /**
     * Schedule the IPLocationDB update command if auto-update is enabled.
     */
    protected function scheduleUpdateCommand(): void
    {
        $config = config('geolocator.drivers.iplocationdb.update.auto_update', []);

        if (! ($config['enabled'] ?? false) || empty($config['frequency'])) {
            return;
        }

        Schedule::command(Commands\UpdateIPLocationDB::class)->tap(function ($event) use ($config) {
            $event->description = 'Update IPLocationDB geolocation databases';

            match ($config['frequency']) {
                'hourly' => $event->hourly(),
                'daily' => $event->daily(),
                'weekly' => $event->weekly(),
                'monthly' => $event->monthly(),
                default => $event->cron($config['frequency']),
            };
        })->withoutOverlapping();
    }

    /**
     * Register the geolocate() macro on the Request class.
     */
    protected function registerMacros(): void
    {
        Request::macro('geolocate', function ($default = '0.0.0.0') {
            $geolocate = app(Geolocate::class);

            // Determine the client IP address, ignoring private/internal IPs
            $ip = Arr::first($this->getClientIps(), function ($ip) {
                // getClientIps() yields the raw REMOTE_ADDR value, which may be null
                return is_string($ip) && ! IPAddressHelper::isPrivateIPAddress($ip);
            }, $this->ip());

            return $geolocate->lookup($ip ?? $default);
        });
    }
}
