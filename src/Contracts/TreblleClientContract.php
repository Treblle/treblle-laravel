<?php

declare(strict_types=1);

namespace Treblle\Contracts;

use Closure;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;

interface TreblleClientContract
{
    public static function fake(null|array|Closure $callback = null): Factory;

    public function authLookUp(string $email): Response;

    public function register(string $name, string $email, string $password): Response;

    public function login(string $email, string $password): Response;

    public function createProject(string $projectName, string $userUuid): Response;
}
