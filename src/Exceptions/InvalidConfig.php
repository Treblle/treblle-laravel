<?php

namespace Treblle\Exceptions;

use Exception;

class InvalidConfig extends Exception {
    
    public static function apiKeyMissing(): Exception {
        return new static('You need to add TREBLLE_API_KEY to your .env');
    }

    public static function projectIdMissing() {
        return new static('You need to add TREBLLE_PROJECT_ID to your .env');
    }
}
