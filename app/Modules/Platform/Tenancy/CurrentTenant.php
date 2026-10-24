<?php

namespace App\Modules\Platform\Tenancy;

use App\Models\Client;
use Closure;
use Illuminate\Container\Attributes\Scoped;
use LogicException;

/**
 * The client whose suite data this request (or job) works on. Set once by the suite
 * middleware, or explicitly by Baydaq staff code and jobs. Tenant tables refuse to read
 * or write while nothing is set, so a missing tenant is an error, never a leak.
 */
#[Scoped]
class CurrentTenant
{
    private ?Client $client = null;

    public function set(Client $client): void
    {
        $this->client = $client;
    }

    public function clear(): void
    {
        $this->client = null;
    }

    public function isSet(): bool
    {
        return $this->client !== null;
    }

    public function id(): ?int
    {
        return $this->client?->id;
    }

    public function client(): Client
    {
        return $this->client ?? throw new LogicException('No tenant has been resolved for this request.');
    }

    /**
     * Run a callback as the tenant with this id (for jobs and commands that only hold an id).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runFor(int $clientId, Closure $callback): mixed
    {
        return $this->run(Client::query()->findOrFail($clientId), $callback);
    }

    /**
     * Run a callback as the given tenant, restoring the previous one afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Client $client, Closure $callback): mixed
    {
        $previous = $this->client;
        $this->client = $client;

        try {
            return $callback();
        } finally {
            $this->client = $previous;
        }
    }
}
