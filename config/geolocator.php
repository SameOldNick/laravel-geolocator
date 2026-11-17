<?php

return [
    /**
     * The geolocation driver used by the manager.
     */
    'driver' => env('GEOLOCATOR_DRIVER', 'iplocationdb'),

    'drivers' => [
        'iplocationdb' => [
            'editions' => [
                'country' => [
                    /**
                     * The IPv4 and IPv6 country database paths.
                     */
                    'ipv4' => env('IPLOCATIONDB_COUNTRY_DB_PATH', storage_path('app/geolocation/GeoLite2-Country.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_COUNTRY_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-Country-IPv6.mmdb')),
                ],
                'city' => [
                    /**
                     * The IPv4 and IPv6 city database paths.
                     */
                    'ipv4' => env('IPLOCATIONDB_CITY_DB_PATH', storage_path('app/geolocation/GeoLite2-City.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_CITY_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-City-IPv6.mmdb')),
                ],
                'asn' => [
                    /**
                     * The IPv4 and IPv6 ASN database paths.
                     */
                    'ipv4' => env('IPLOCATIONDB_ASN_DB_PATH', storage_path('app/geolocation/GeoLite2-ASN.mmdb')),
                    'ipv6' => env('IPLOCATIONDB_ASN_DB_PATH_V6', storage_path('app/geolocation/GeoLite2-ASN-IPv6.mmdb')),
                ],
            ],

            'update' => [
                'auto_update' => [
                    /**
                     * Whether the database update command runs automatically.
                     */
                    'enabled' => env('MAXMIND_AUTO_UPDATE', true),
                    /**
                     * The update frequency or a cron expression.
                     */
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
                    /**
                     * The HTTP request options for downloading the databases.
                     * See https://docs.guzzlephp.org/en/stable/request-options.html for available options.
                     */
                    'http' => [
                        /**
                         * The HTTP request timeout, in seconds.
                         */
                        'timeout' => 30,
                    ],
                ],
            ],
        ],
    ],

    /**
     * The client locator used by the geolocate request macro.
     */
    'client' => env('GEOLOCATOR_CLIENT', 'ip'), // 'ip' or 'headers'

    'clients' => [
        'ip' => [
            /**
             * The geolocation driver used by the IP client locator.
             */
            'driver' => env('GEOLOCATOR_CLIENT_IP_DRIVER', 'iplocationdb'),
        ],

        'headers' => [
            /**
             * The prefix prepended to each mapped request header name.
             */
            'prefix' => env('GEOLOCATOR_REQUEST_HEADERS_PREFIX', 'X_IP2LOCATION_'),

            /**
             * The IP address reported when no fallback header is present.
             */
            'fallback_ip' => env('GEOLOCATOR_REQUEST_HEADERS_FALLBACK_IP', '0.0.0.0'),

            'mappings' => [
                'country' => [
                    /**
                     * Header mappings for country lookups.
                     */
                    'country_code' => env('GEOLOCATOR_REQUEST_HEADER_COUNTRY_CODE', 'COUNTRY_CODE'),
                    'country_name' => env('GEOLOCATOR_REQUEST_HEADER_COUNTRY_NAME', 'COUNTRY_NAME'),
                ],

                'city' => [
                    /**
                     * Header mappings for city lookups.
                     */
                    'country_code' => env('GEOLOCATOR_REQUEST_HEADER_COUNTRY_CODE', 'COUNTRY_CODE'),
                    'country_name' => env('GEOLOCATOR_REQUEST_HEADER_COUNTRY_NAME', 'COUNTRY_NAME'),
                    'city' => env('GEOLOCATOR_REQUEST_HEADER_CITY', 'CITY'),
                    'state1' => env('GEOLOCATOR_REQUEST_HEADER_STATE1', 'REGION'),
                    'state2' => env('GEOLOCATOR_REQUEST_HEADER_STATE2', 'STATE2'),
                    'latitude' => env('GEOLOCATOR_REQUEST_HEADER_LATITUDE', 'LATITUDE'),
                    'longitude' => env('GEOLOCATOR_REQUEST_HEADER_LONGITUDE', 'LONGITUDE'),
                    'timezone' => env('GEOLOCATOR_REQUEST_HEADER_TIMEZONE', 'TIME_ZONE'),
                ],

                'asn' => [
                    /**
                     * Header mappings for ASN lookups.
                     */
                    'asn' => env('GEOLOCATOR_REQUEST_HEADER_ASN', 'ASN'),
                    'organization' => env('GEOLOCATOR_REQUEST_HEADER_ORGANIZATION', 'AS'),
                ],
            ],
        ],
    ],
];
