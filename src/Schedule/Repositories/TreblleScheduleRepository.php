<?php

declare(strict_types=1);

namespace Treblle\Laravel\Schedule\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Treblle\Laravel\Exceptions\TreblleException;
use Treblle\Laravel\Schedule\Models\TreblleSchedule;

/**
 * Repository for interacting with treblle_schedules table
 *
 * @package Treblle\Laravel\Schedule\Repositories
 */
final class TreblleScheduleRepository
{
    /**
     * Creates new Treblle schedule entry
     *
     * @param string $payload
     * @return TreblleSchedule
     */
    public function create(string $payload): TreblleSchedule
    {
        $model = new TreblleSchedule();
        $model->payload = $payload;
        $model->save();

        return $model;

    }

    /**
     * Supplies chunks of data to callback, it will be responsible for transmission
     *
     * @param int $chunkSize
     * @param array $callback
     * @return void
     */
    public function processForTransmission(int $chunkSize, array $callback): void
    {
        $builder = $this->getBuilder();

        $builder->chunkById($chunkSize, $callback);
    }

    /**
     * Determent's total rows on record
     *
     * @return int
     */
    public function findTotalFoTransmission(): int
    {
        $builder = $this->getBuilder();

        return $builder->count();
    }

    /**
     * Deletes records for given ids
     *
     * @param array $ids
     * @return void
     * @throws TreblleException
     */
    public function cleanFor(array $ids): void
    {
        $builder = $this->getBuilder();

        $deletedRecords = $builder->whereIn('id', $ids)->delete();

        if ($deletedRecords !== count($ids)) {
            throw new TreblleException('Could not delete all records while cleaning scheduled Treblle transmission');
        }
    }

    /**
     * Returns new Eloquent Builder instance
     *
     * @return Builder
     */
    private function getBuilder(): Builder
    {
        return (new TreblleSchedule())->newModelQuery();
    }
}
