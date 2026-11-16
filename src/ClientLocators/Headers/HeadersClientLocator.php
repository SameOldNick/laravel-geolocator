<?php

namespace SameOldNick\Geolocator\ClientLocators\Headers;

use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Arr;
use SameOldNick\Geolocator\Contracts\ClientLocator as ClientLocatorContract;
use SameOldNick\Geolocator\DTOs\LocationResult;

class HeadersClientLocator implements ClientLocatorContract
{
    public const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public const LOWER = '-abcdefghijklmnopqrstuvwxyz';

    /**
     * The request header mapper instance.
     */
    protected readonly RequestHeaderMapper $requestHeaderMapper;

    /**
     * Create a new HeadersClientLocator instance.
     *
     * @param  string|null  $requestHeaderPrefix  Prefix for request headers
     * @param  array  $countryRequestHeaders  Mapping of country header keys to their corresponding request header names
     * @param  array  $cityRequestHeaders  Mapping of city header keys to their corresponding request header names
     * @param  array  $asnRequestHeaders  Mapping of ASN header keys to their corresponding request header names
     */
    public function __construct(
        #[Config('geolocator.clients.headers.prefix')]
        protected readonly ?string $requestHeaderPrefix,
        #[Config('geolocator.clients.headers.mappings.country', default: [])]
        protected readonly array $countryRequestHeaders,
        #[Config('geolocator.clients.headers.mappings.city', default: [])]
        protected readonly array $cityRequestHeaders,
        #[Config('geolocator.clients.headers.mappings.asn', default: [])]
        protected readonly array $asnRequestHeaders,
        #[Config('geolocator.clients.headers.fallback_ip', default: '0.0.0.0')]
        protected readonly string $fallbackIp,
    ) {
        $this->requestHeaderMapper = new RequestHeaderMapper(
            $this->prepareHeaders($this->countryRequestHeaders),
            $this->prepareHeaders($this->cityRequestHeaders),
            $this->prepareHeaders($this->asnRequestHeaders),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookup(): LocationResult
    {
        return $this->requestHeaderMapper->mapHeaders(
            $this->getClientIpAddress() ?? $this->getFallbackIpAddress(),
            $this->getRequestHeaders(),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCountry(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->getClientIpAddress() ?? $this->getFallbackIpAddress(),
            country: $this->requestHeaderMapper->mapCountry($this->getRequestHeaders()),
            city: null,
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupCity(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->getClientIpAddress() ?? $this->getFallbackIpAddress(),
            country: null,
            city: $this->requestHeaderMapper->mapCity($this->getRequestHeaders()),
            asn: null,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function lookupAsn(): LocationResult
    {
        return new LocationResult(
            ipAddress: $this->getClientIpAddress() ?? $this->getFallbackIpAddress(),
            country: null,
            city: null,
            asn: $this->requestHeaderMapper->mapAsn($this->getRequestHeaders()),
        );
    }

    /**
     * Get the client IP address from the request.
     */
    protected function getClientIpAddress(): ?string
    {
        return request()->ip();
    }

    /**
     * Get the fallback IP address from the configuration.
     */
    protected function getFallbackIpAddress(): string
    {
        return $this->fallbackIp;
    }

    /**
     * Get the request headers from the request.
     */
    protected function getRequestHeaders(): array
    {
        return request()->headers->all();
    }

    /**
     * Prepare the request headers for processing.
     *
     * @param  array  $headers  Request headers
     */
    protected function prepareHeaders(array $headers): array
    {
        return Arr::mapWithKeys(
            $headers,
            fn ($value, $key) => [$key => $this->normalizeHeaderName($this->prefixHeaderName($value))],
        );
    }

    /**
     * Prefix the request header name with the configured prefix, if any.
     *
     * @param  string  $headerName  Request header name
     */
    protected function prefixHeaderName(string $headerName): string
    {
        return $this->requestHeaderPrefix ? $this->requestHeaderPrefix.$headerName : $headerName;
    }

    /**
     * Normalize the request header name to lowercase and replace underscores with hyphens.
     *
     * @param  string  $headerName  Request header name
     */
    protected function normalizeHeaderName(string $headerName): string
    {
        return strtr($headerName, self::UPPER, self::LOWER);
    }
}
