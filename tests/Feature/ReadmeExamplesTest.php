<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use SameOldNick\Geolocator\Drivers\Fake\FakeGeolocator;
use SameOldNick\Geolocator\DTOs\AsnResult;
use SameOldNick\Geolocator\DTOs\CityResult;
use SameOldNick\Geolocator\DTOs\CountryResult;
use SameOldNick\Geolocator\DTOs\LocationResult;
use SameOldNick\Geolocator\Events\DatabaseFileUpdated;
use SameOldNick\Geolocator\Events\DatabaseFileUpdateFailed;
use SameOldNick\Geolocator\Events\DatabaseUpdatesCompleted;
use SameOldNick\Geolocator\Facades\Geolocator;
use SameOldNick\Geolocator\Tests\Fixtures\RecordingGeolocator;
use SameOldNick\Geolocator\Tests\TestCase;

/**
 * Runs the consumer examples that the README and the `docs/wiki/` pages document, so the
 * documentation cannot drift away from the code. CI cannot read the wiki, so this file is where that
 * guarantee lives — move it with the page it documents.
 *
 * Anything that needs a real MaxMind database is deliberately absent: the package ships no `.mmdb`
 * fixture, so lookups run through the fake or a recording driver. See issue 08 for that gap.
 *
 * @internal
 */
class ReadmeExamplesTest extends TestCase
{
    /**
     * The "Private, reserved and unparseable addresses" example.
     */
    public function test_the_private_address_example_returns_an_empty_result(): void
    {
        Geolocator::fake();

        $result = Geolocator::lookup('192.168.1.1');

        $this->assertFalse($result->hasResults());
        $this->assertSame('Unknown Location', (string) $result);
    }

    /**
     * The "Looking up an address" accessor list.
     */
    public function test_the_documented_accessors_return_the_documented_values(): void
    {
        Geolocator::fake();

        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: CityResult::create(
                countryCode: 'US',
                city: 'Ashburn',
                state1: 'Virginia',
                state2: null,
                latitude: 39.0438,
                longitude: -77.4874,
                timezone: 'America/New_York',
            ),
            asn: AsnResult::create(15169, 'Google LLC'),
        );

        /** @var FakeGeolocator $driver */
        $driver = Geolocator::fake();

        $driver->mock('8.8.8.8', $result);

        $location = Geolocator::lookup('8.8.8.8');

        $this->assertSame('8.8.8.8', $location->ipAddress);
        $this->assertSame('US', $location->country?->countryCode);
        $this->assertSame('United States', $location->country?->countryName);
        $this->assertSame('Ashburn', $location->city?->city);
        $this->assertSame('Virginia', $location->city?->state1);
        $this->assertSame('America/New_York', $location->city?->timezone);
        $this->assertSame(15169, $location->asn?->asn);
        $this->assertSame('Google LLC', $location->asn?->organization);
        $this->assertTrue($location->hasResults());

        // The README documents getCoordinates() as ['latitude' => .., 'longitude' => ..].
        $coordinates = $location->country?->getCoordinates();

        $this->assertIsArray($coordinates);
        $this->assertArrayHasKey('latitude', $coordinates);
        $this->assertArrayHasKey('longitude', $coordinates);

        $this->assertSame(['ipAddress', 'country', 'city', 'asn'], array_keys($location->toArray()));
    }

    /**
     * The "Faking lookups in your tests" section: the documented key forms, precedence and closures.
     */
    public function test_the_faking_example_matches_the_documented_behaviour(): void
    {
        $result = new LocationResult(
            ipAddress: '8.8.8.8',
            country: null,
            city: null,
            asn: AsnResult::create(15169, 'Google LLC'),
        );

        /** @var FakeGeolocator $driver */
        $driver = Geolocator::fake(mockedResults: ['*' => null]);

        $driver->mock('8.8.8.8', $result);

        // An exact address takes precedence over the wildcard.
        $this->assertSame($result, $driver->lookup('8.8.8.8'));

        // Anything else falls back to the wildcard, which resolves to nothing.
        $this->assertFalse($driver->lookup('9.9.9.9')->hasResults());

        // A glob pattern applies to the addresses it matches.
        $subnet = new LocationResult(
            ipAddress: '8.8.8.4',
            country: null,
            city: null,
            asn: null,
        );

        $driver->mock('8.8.8.*', $subnet);

        $this->assertSame($subnet, $driver->lookup('8.8.8.4'));

        // A closure receives the address and the calling method.
        $driver->mock('8.8.4.4', fn (string $ip, string $method) => new LocationResult(
            ipAddress: "{$ip}/{$method}",
            country: null,
            city: null,
            asn: null,
        ));

        $this->assertSame('8.8.4.4/lookupCity', $driver->lookupCity('8.8.4.4')->ipAddress);
    }

    /**
     * The "Faking lookups in your tests" defaults: chanceOfEmpty, generated data and private addresses.
     */
    public function test_the_faking_defaults_match_the_documented_behaviour(): void
    {
        /** @var FakeGeolocator $driver */
        $driver = Geolocator::fake();

        $this->assertSame(0, $driver->chanceOfEmpty);

        // Unmocked public addresses get generated data; private ones resolve to nothing.
        $this->assertTrue($driver->lookup('8.8.4.4')->hasResults());
        $this->assertFalse($driver->lookup('192.168.1.1')->hasResults());

        // The README warns that a wildcard mock answers private addresses too.
        $result = new LocationResult(
            ipAddress: '192.168.1.1',
            country: null,
            city: null,
            asn: null,
        );

        $driver->mock('*', $result);

        $this->assertSame($result, $driver->lookup('192.168.1.1'));

        // chanceOfEmpty is documented as a named argument.
        $this->assertSame(100, Geolocator::fake(chanceOfEmpty: 100)->chanceOfEmpty);
    }

    /**
     * The "Resolving the current request" example, route included.
     */
    public function test_the_request_example_returns_the_location_as_an_array(): void
    {
        $recorder = new RecordingGeolocator(new LocationResult(
            ipAddress: '8.8.8.8',
            country: CountryResult::create('US'),
            city: null,
            asn: null,
        ));

        Geolocator::extend('readme', fn () => $recorder);
        config()->set('geolocator.driver', 'readme');
        Geolocator::forgetDrivers();

        Route::get('/location', function (Request $request) {
            return $request->geolocate()->toArray();
        });

        $response = $this->getJson('/location');

        $response->assertOk();
        $response->assertJsonStructure(['ipAddress', 'country', 'city', 'asn']);
        $response->assertJsonPath('country.countryCode', 'US');

        // The README documents $request->ip(), with '0.0.0.0' as the fallback. Laravel's test requests
        // carry 127.0.0.1, so that is the address handed to the driver — the macro no longer skips a
        // private address in favour of a later entry in the client IP list.
        $this->assertSame('127.0.0.1', $recorder->calls[0]['ip']);
    }

    /**
     * The "Writing a custom driver" note about unsupported names.
     */
    public function test_an_unknown_driver_name_throws_the_documented_message(): void
    {
        config()->set('geolocator.driver', 'unknown');
        Geolocator::forgetDrivers();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Driver \[unknown\] not supported\.$/');

        Geolocator::lookup('8.8.8.8');
    }

    /**
     * The same note's caveat: "the container is not consulted, so a container binding alone will not
     * register a driver".
     */
    public function test_a_container_binding_alone_does_not_register_a_driver(): void
    {
        $this->app->bind('readme-driver', fn () => new RecordingGeolocator(
            new LocationResult('8.8.8.8', null, null, null),
        ));

        config()->set('geolocator.driver', 'readme-driver');
        Geolocator::forgetDrivers();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Driver \[readme-driver\] not supported\.$/');

        Geolocator::lookup('8.8.8.8');
    }

    /**
     * The Events section's claim that the package "sends no notifications itself".
     */
    public function test_the_package_registers_no_listeners_of_its_own(): void
    {
        $listeners = $this->app->make('events')->getRawListeners();

        foreach ([DatabaseFileUpdated::class, DatabaseFileUpdateFailed::class, DatabaseUpdatesCompleted::class] as $event) {
            $this->assertArrayNotHasKey(
                $event,
                $listeners,
                "[{$event}] has a listener the README does not mention.",
            );
        }
    }
}
