<?php

return [
    'driver' => env('GEOLOCATOR_DRIVER', 'iplocationdb'),

    'drivers' => [
        'iplocationdb' => [
            'editions' => [
                'country' => [
                    'ipv4' => env('IPLOCATIONDB_COUNTRY_DB_PATH', storage_path('app/geolocation/GeoLite2-Country.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_COUNTRY_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-Country-IPv6.mmdb')),
                ],
                'city' => [
                    'ipv4' => env('IPLOCATIONDB_CITY_DB_PATH', storage_path('app/geolocation/GeoLite2-City.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_CITY_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-City-IPv6.mmdb')),
                ],
                'asn' => [
                    'ipv4' => env('IPLOCATIONDB_ASN_DB_PATH', storage_path('app/geolocation/GeoLite2-ASN.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_ASN_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-ASN-IPv6.mmdb')),
                ],
            ],

            'update' => [
                'auto_update' => [
                    'enabled' => env('MAXMIND_AUTO_UPDATE', true),
                    'frequency' => env('MAXMIND_UPDATE_FREQUENCY', 'weekly'), // Options: 'daily', 'weekly', 'monthly', or cron expression
                ],
                'urls' => [
                    /**
                     * The databases can be found at: https://github.com/sapics/ip-location-db
                     */
                    'country' => [
                        'ipv4' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-country-mmdb/geolite2-country-ipv4.mmdb',
                        ],
                        'ipv6' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-country-mmdb/geolite2-country-ipv6.mmdb',
                        ],
                    ],
                    'city' => [
                        'ipv4' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-city-mmdb/geolite2-city-ipv4.mmdb',
                        ],
                        'ipv6' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-city-mmdb/geolite2-city-ipv6.mmdb',
                        ],
                    ],
                    'asn' => [
                        'ipv4' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-asn-mmdb/geolite2-asn-ipv4.mmdb',
                        ],
                        'ipv6' => [
                            'https://cdn.jsdelivr.net/npm/@ip-location-db/geolite2-asn-mmdb/geolite2-asn-ipv6.mmdb',
                        ],
                    ],
                ],
                'options' => [
                    'http' => [
                        'timeout' => 30,
                    ],
                ],
            ],
        ],

        'fake' => [
            'chance_of_empty' => env('GEOLOCATOR_FAKE_CHANCE_OF_EMPTY', 10), // Percentage chance of returning empty result
        ],
    ],
];
