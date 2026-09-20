<?php

namespace SameOldNick\Geolocator\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use SameOldNick\Geolocator\Drivers\FakeGeolocator;
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
 * Runs the examples in README.md, so the documentation cannot drift away from the code.
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
        Geolocator::fake(0);

        $result = Geolocator::lookup('192.168.1.1');

        $this->assertFalse($result->hasResults());
        $this->assertSame('Unknown Location', (string) $result);
    }

    /**
     * The "Looking up an address" accessor list.
     */
    public function test_the_documented_accessors_return_the_documented_values(): void
    {
        Geolocator::fake(0);

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
        $driver = Geolocator::fake(0);

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

        // The README documents the chain getClientIps() -> $request->ip() -> '0.0.0.0'. Laravel's
        // test requests carry 127.0.0.1, so this lands on the second step of that chain.
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
