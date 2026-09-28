<?php

namespace App\Services\Distribution\MaxFlow;

/**
 * A directed graph with integer capacities, stored sparsely.
 *
 * Capacities are set, not added, matching how the legacy code assigned
 * cells of its V x V matrix. Once edges are in place, freeze() fixes each
 * node's neighbours (both directions, so reverse residual edges are
 * reachable) in ascending order. Traversing neighbours in that order
 * visits nodes exactly as a scan over a matrix row would.
 */
final class FlowNetwork
{
    /** @var array<int, array<int, int>> capacity[u][v] */
    private array $capacity = [];

    /** @var array<int, int[]> */
    private array $neighbours = [];

    public function __construct(public readonly int $nodeCount)
    {
    }

    public function source(): int
    {
        return 0;
    }

    public function sink(): int
    {
        return $this->nodeCount - 1;
    }

    public function setCapacity(int $from, int $to, int $capacity): void
    {
        $this->capacity[$from][$to] = $capacity;
        $this->capacity[$to][$from] ??= 0;
    }

    public function capacity(int $from, int $to): int
    {
        return $this->capacity[$from][$to] ?? 0;
    }

    /**
     * Move $amount of flow along the edge, updating residual capacities.
     */
    public function push(int $from, int $to, int $amount): void
    {
        $this->capacity[$from][$to] -= $amount;
        $this->capacity[$to][$from] += $amount;
    }

    /**
     * Nodes reachable from $node by an edge that currently has capacity.
     *
     * @return int[] ascending
     */
    public function outgoing(int $node): array
    {
        $out = [];
        foreach ($this->capacity[$node] ?? [] as $to => $capacity) {
            if ($capacity > 0) {
                $out[] = $to;
            }
        }
        sort($out);

        return $out;
    }

    public function freeze(): void
    {
        foreach ($this->capacity as $node => $edges) {
            $neighbours = array_keys($edges);
            sort($neighbours);
            $this->neighbours[$node] = $neighbours;
        }
    }

    /**
     * Every node sharing an edge with $node in either direction, ascending.
     *
     * @return int[]
     */
    public function neighbours(int $node): array
    {
        return $this->neighbours[$node] ?? [];
    }
}
