<?php

declare(strict_types=1);

namespace Treblle\DataObjects;

final class Account
{
    public function __construct(
        public null|string $uuid = null,
        public null|string $firstName = null,
        public null|string $name = null,
        public null|string $email = null,
        public null|string $timezone = null,
        public null|string $initials = null,
        public null|string $color = null,
        public null|string $apiKey = null,
        public array $settings = []
    ) {

    }

    public static function fromRequest(array $data): self
    {
        return new self(
            uuid: (string) (data_get($data, 'user.uuid')),
            firstName: (string) (data_get($data, 'user.first_name')),
            name: (string) (data_get($data, 'user.name')),
            email: (string) (data_get($data, 'user.email')),
            timezone: (string) (data_get($data, 'user.timezone')),
            initials: (string) (data_get($data, 'user.initials')),
            color: (string) (data_get($data, 'user.color')),
            apiKey: (string) (data_get($data, 'user.api_key')),
            settings: (array) data_get($data, 'user.settings'),
        );
    }
}
