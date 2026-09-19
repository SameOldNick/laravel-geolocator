<?php

namespace SameOldNick\Geolocator\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class AsnResult implements Arrayable
{
    /**
     * Constructor
     *
     * @param  int  $asn  Autonomous System Number
     * @param  string  $organization  Autonomous System Organization
     */
    public function __construct(
        public readonly int $asn,
        public readonly string $organization,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'asn' => $this->asn,
            'organization' => $this->organization,
        ];
    }

    /**
     * Creates AsnResult instance
     *
     * @param  int  $asn  Autonomous System Number
     * @param  string  $organization  Autonomous System Organization
     */
    public static function create(int $asn, string $organization): self
    {
        return new self(
            asn: $asn,
            organization: $organization,
        );
    }
}
