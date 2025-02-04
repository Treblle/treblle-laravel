<?php

declare(strict_types=1);

namespace Treblle\DataObjects;

final class Project
{
    /**
     * @param string|null       $uuid
     * @param string|null       $apiID
     * @param string|null       $name
     * @param string|null       $url
     * @param string|null       $updated
     * @param int               $endpoints
     * @param int               $errors
     * @param int               $requests
     * @param int               $score
     * @param array<int,Member> $members
     */
    public function __construct(
        public null|string $uuid,
        public null|string $apiID,
        public null|string $name,
        public null|string $url,
        public null|string $updated,
        public int $endpoints = 0,
        public int $errors = 0,
        public int $requests = 0,
        public int $score = 0,
        public array $members = [],
    ) {
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public static function fromRequest(array $data): self
    {
        $members = array_map(
            callback: static fn (mixed $member): Member => Member::fromRequest(
                data: (array) $member,
            ),
            array: (array) data_get($data, 'members'),
        );

        return new self(
            uuid: (string) (data_get($data, 'uuid')),
            apiID: (string) (data_get($data, 'api_id')),
            name: (string) (data_get($data, 'name')),
            url: (string) (data_get($data, 'url')),
            updated: (string) (data_get($data, 'updated')),
            endpoints: (int) (data_get($data, 'endpoints')),
            errors: (int) (data_get($data, 'errors')),
            requests: (int) (data_get($data, 'requests')),
            score: (int) (data_get($data, 'score')),
            members: $members,
        );
    }
}
