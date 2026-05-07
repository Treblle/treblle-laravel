<?php

declare(strict_types=1);

namespace Treblle\Laravel\Contracts;

use Treblle\Laravel\DataTransferObject\Response;

interface ResponseDataProvider
{
    public function getResponse(): Response;
}
