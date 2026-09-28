<?php

namespace App\Services\Distribution\MaxFlow;

use App\Http\Controllers\MaxFlow\EnumPersonType;

/**
 * The flow network for one role together with the meaning of its nodes.
 *
 * Nodes are laid out in layers: source (0), members, same-time groups,
 * courses, rooms, sink (last).
 */
final class RoleNetwork
{
    /**
     * @param int[] $members member ids in node order
     * @param int $sameTimeCount number of same-time groups
     * @param int[] $courses course ids in node order
     * @param int[] $rooms room ids in node order
     */
    public function __construct(
        public readonly EnumPersonType $role,
        public readonly FlowNetwork $network,
        public readonly array $members,
        public readonly int $sameTimeCount,
        public readonly array $courses,
        public readonly array $rooms,
    ) {
    }

    public function memberNode(int $index): int
    {
        return 1 + $index;
    }

    public function sameTimeNode(int $group): int
    {
        return 1 + count($this->members) + $group;
    }

    public function courseNode(int $index): int
    {
        return 1 + count($this->members) + $this->sameTimeCount + $index;
    }

    public function roomNode(int $index): int
    {
        return 1 + count($this->members) + $this->sameTimeCount + count($this->courses) + $index;
    }

    public function memberAt(int $node): int
    {
        return $this->members[$node - $this->memberNode(0)];
    }

    public function courseAt(int $node): int
    {
        return $this->courses[$node - $this->courseNode(0)];
    }

    public function roomAt(int $node): int
    {
        return $this->rooms[$node - $this->roomNode(0)];
    }
}
