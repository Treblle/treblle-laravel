<?php

declare(strict_types=1);

namespace Treblle\Laravel\Schedule;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model for storing Treblle schedules
 *
 * @property int $id
 * @property string $payload
 * @property bool $sent
 *
 * @package Treblle\Laravel\Schedule
 */
final class TreblleSchedule extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'payload',
        'sent',
    ];
}
