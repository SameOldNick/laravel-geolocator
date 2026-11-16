<?php

namespace SameOldNick\Geolocator;

use Illuminate\Support\Manager;
use SameOldNick\Geolocator\ClientLocators\Headers\HeadersClientLocator;
use SameOldNick\Geolocator\ClientLocators\IpClientLocator;

class ClientLocatorManager extends Manager implements Contracts\ClientLocator
{
    /**
     * {@inheritDoc}
     */
    public function getDefaultDriver()
    {
        return $this->config->get('geolocator.client');
    }

    /**
     * Create a headers client locator driver instance.
     *
     * @return HeadersClientLocator
     */
    public function createHeadersDriver()
    {
        return $this->container->make(HeadersClientLocator::class);
    }

    /**
     * Create an IP client locator driver instance.
     *
     * @return IpClientLocator
     */
    protected function createIpDriver()
    {
        return $this->container->make(IpClientLocator::class);
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(): DTOs\LocationResult
    {
        return $this->driver()->lookup();
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(): DTOs\LocationResult
    {
        return $this->driver()->lookupCountry();
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(): DTOs\LocationResult
    {
        return $this->driver()->lookupCity();
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(): DTOs\LocationResult
    {
        return $this->driver()->lookupAsn();
    }
}
