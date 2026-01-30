<?php

namespace Treblle\Laravel\Monitor\Enums;

/**
 *
 * @package Treblle\Laravel\Monitor\Enums
 */
enum PayloadDataType: string
{
    case ORIGIN = 'origin';
    case THIRD_PARTY = 'third_party';
}
