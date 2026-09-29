<?php

namespace SameOldNick\Geolocator\Drivers\Fake\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait MocksResults
{
    /**
     * The mocked results.
     */
    protected array $mockedResults = [];

    /**
     * Set a mocked result for the given IP address.
     */
    protected function setMock(string $ip, mixed $result): self
    {
        $this->mockedResults[$ip] = $result;

        return $this;
    }

    /**
     * Unset a mocked result for the given IP address.
     */
    protected function unsetMock(string $ip): self
    {
        unset($this->mockedResults[$ip]);

        return $this;
    }

    /**
     * Find all mocked results matching the given IP address.
     *
     * @return array<string, mixed> Matched results, keyed by the pattern that matched
     */
    protected function findMockedResults(string $ip): array
    {
        return Arr::where($this->getMocks(), fn ($value, $key) => Str::is($key, $ip));
    }

    /**
     * Determine if a mocked result exists for the given IP address.
     */
    protected function hasMockedResult(string $ip): bool
    {
        $found = $this->findMockedResults($ip);

        return count($found) > 0;
    }

    /**
     * Get the most specific mocked result for the given IP address, or default if none is defined.
     */
    protected function getMockedResult(string $ip, string $method, $default = null): mixed
    {
        $results = $this->findMockedResults($ip);

        $sorted = Arr::sortDesc($results, fn ($value, $key) => match (true) {
            $key === '*' => 1, // Wildcard
            Str::contains($key, '*') => 2, // Glob pattern
            default => 3, // Specific IP address
        });

        return value(array_shift($sorted), $ip, $method) ?? value($default, $ip, $method);
    }

    /**
     * Get all mocked results
     */
    public function getMocks(): array
    {
        return $this->mockedResults;
    }
}
