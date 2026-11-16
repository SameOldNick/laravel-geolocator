<?php

namespace SameOldNick\Geolocator\ClientLocators;

use Illuminate\Container\Attributes\Config;
use RuntimeException;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\Contracts\Geolocator as GeolocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\GeolocatorManager;

readonly class IpClientLocator implements ClientLocatorContract
{
    /**
     * Create a new IpClientLocator instance.
     *
     * @param  GeolocatorManager  $driverManager  The GeolocatorManager instance for resolving drivers
     * @param  string|null  $driver  Optional driver name to use for lookup
     */
    public function __construct(
        protected GeolocatorManager $driverManager,
        #[Config('geolocator.clients.ip.driver')]
        protected ?string $driver,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(): LocationResult
    {
        $ip = $this->getClientIpAddress();

        if ($ip === null) {
            throw new RuntimeException('Unable to determine client IP address.');
        }

        return $this->driver()->lookup($ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(): LocationResult
    {
        $ip = $this->getClientIpAddress();

        if ($ip === null) {
            throw new RuntimeException('Unable to determine client IP address.');
        }

        return $this->driver()->lookupCountry($ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(): LocationResult
    {
        $ip = $this->getClientIpAddress();

        if ($ip === null) {
            throw new RuntimeException('Unable to determine client IP address.');
        }

        return $this->driver()->lookupCity($ip);
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(): LocationResult
    {
        $ip = $this->getClientIpAddress();

        if ($ip === null) {
            throw new RuntimeException('Unable to determine client IP address.');
        }

        return $this->driver()->lookupAsn($ip);
    }

    /**
     * Get the configured driver instance.
     */
    protected function driver(): GeolocatorContract
    {
        return $this->driverManager->driver($this->driver);
    }

    /**
     * Get the client's IP address.
     */
    protected function getClientIpAddress(): ?string
    {
        $ip = request()->ip();

        return $ip;
    }
}
