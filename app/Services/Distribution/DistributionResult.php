<?php

namespace App\Services\Distribution;


/**
 * Outcome of distributing faculty members over a rotation's exam rooms.
 *
 * Each role's assignments use the shape
 * ['users_observations' => [userId => [['course', 'room', 'date', 'time', 'duration', 'roleIn'], ...]]].
 */
final class DistributionResult
{
    private function __construct(
        public readonly array $roomHeads,
        public readonly array $secretaries,
        public readonly array $observers,
        public readonly ?MemberRole $unfilledRole,
    ) {
    }

    public static function success(array $roomHeads, array $secretaries, array $observers): self
    {
        return new self($roomHeads, $secretaries, $observers, null);
    }

    /**
     * No member of the given role could be assigned, so the distribution stopped.
     */
    public static function unfilled(MemberRole $role): self
    {
        return new self([], [], [], $role);
    }

    public function succeeded(): bool
    {
        return $this->unfilledRole === null;
    }
}
