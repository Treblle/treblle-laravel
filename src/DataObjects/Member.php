<?php

declare(strict_types=1);

namespace Treblle\DataObjects;

final class Member
{
    /**
     * @param string|null $uuid
     * @param string|null $status
     * @param string|null $name
     * @param string|null $email
     * @param string|null $initials
     * @param string|null $color
     * @param string|null $inviteURL
     */
    public function __construct(
        public null|string $uuid,
        public null|string $status,
        public null|string $name,
        public null|string $email,
        public null|string $initials,
        public null|string $color,
        public null|string $inviteURL,
    ) {
    }

    public static function fromRequest(array $data): self
    {
        return new self(
            uuid: strval(data_get($data, 'uuid')),
            status: strval(data_get($data, 'status')),
            name: strval(data_get($data, 'name')),
            email: strval(data_get($data, 'email')),
            initials: strval(data_get($data, 'initials')),
            color: strval(data_get($data, 'color')),
            inviteURL: strval(data_get($data, 'invite_url')),
        );
    }
}
