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
            uuid: strval(data_get($data, 'uuid')),
            apiID: strval(data_get($data, 'api_id')),
            name: strval(data_get($data, 'name')),
            url: strval(data_get($data, 'url')),
            updated: strval(data_get($data, 'updated')),
            endpoints: intval(data_get($data, 'endpoints')),
            errors: intval(data_get($data, 'errors')),
            requests: intval(data_get($data, 'requests')),
            score: intval(data_get($data, 'score')),
            members: $members,
        );
    }
}
