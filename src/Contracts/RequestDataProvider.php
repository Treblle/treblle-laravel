<?php

declare(strict_types=1);

namespace Treblle\Laravel\Contracts;

use Treblle\Laravel\DataTransferObject\Request;

interface RequestDataProvider
{
    public function getRequest(): Request;
}
