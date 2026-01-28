<?php

declare(strict_types=1);

namespace Treblle\Laravel\Schedule\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for storing Treblle schedules
 *
 * @property int $id
 * @property string $payload
 * @property bool $sent
 *
 * @package Treblle\Laravel\Schedule\Models
 */
final class TreblleSchedule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payload',
        'sent',
    ];
}
