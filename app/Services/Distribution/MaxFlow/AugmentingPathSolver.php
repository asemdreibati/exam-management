<?php

namespace App\Services\Distribution\MaxFlow;

/**
 * Ford-Fulkerson with breadth-first search, including the selection
 * heuristics of App\Http\Controllers\MaxFlow\MaxFlow so that the paths
 * found, and their order, are identical:
 *
 * - a member who already received an assignment is postponed once in the
 *   queue when reached straight from the source, spreading work around;
 * - the first room may be closed to the next search after several paths
 *   in a row for the same member;
 * - a path that reroutes flow (longer than source-member-group-course-
 *   room-sink) is split into the member paths it implies.
 *
 * Neighbours are scanned in ascending node order, as the legacy matrix
 * scan did.
 */
final class AugmentingPathSolver
{
    /** Nodes on a direct path, excluding the source: member, group, course, room, sink. */
    private const DIRECT_PATH_LENGTH = 5;

    /** @var array<int, int> how many paths started at each member node */
    private array $takenCount = [];

    private int $takenTotal = 0;

    public function __construct(
        private readonly RoleNetwork $layout,
        private readonly SelectionState $state,
    ) {
    }

    /**
     * @return array<int, int[]> paths as node lists: member, group, course, room, sink
     */
    public function solve(): array
    {
        $network = $this->layout->network;
        $source = $network->source();
        $sink = $network->sink();
        $paths = [];

        while (($parent = $this->search()) !== null) {
            $path = [];
            $pathFlow = PHP_INT_MAX;
            for ($v = $sink; $v !== $source; $v = $parent[$v]) {
                $pathFlow = min($pathFlow, $network->capacity($parent[$v], $v));
            }
            for ($v = $sink; $v !== $source; $v = $parent[$v]) {
                $path[] = $v;
                $network->push($parent[$v], $v, $pathFlow);
            }
            $path = array_reverse($path);

            $this->markTaken($path[0]);
            if (count($path) === self::DIRECT_PATH_LENGTH) {
                $this->updateFirstRoomRule($parent);
            }

            $paths = count($path) > self::DIRECT_PATH_LENGTH
                ? $this->reroute($paths, $path)
                : array_merge($paths, [$path]);
        }

        return $paths;
    }

    /**
     * Breadth-first search for an augmenting path.
     *
     * @return array<int, int>|null parent of each node on the path, or null if none
     */
    private function search(): ?array
    {
        $network = $this->layout->network;
        $source = $network->source();
        $sink = $network->sink();

        $visited = array_fill(0, $network->nodeCount, false);
        $visited[$this->layout->roomNode(0)] = $this->state->firstRoomClosed;
        $visited[$source] = true;
        $parent = [$source => -1];
        $postponed = [];
        $queue = [$source];

        for ($head = 0; $head < count($queue); $head++) {
            $u = $queue[$head];
            if (isset($this->takenCount[$u]) && $parent[$u] === $source && !isset($postponed[$u])) {
                $queue[] = $u;
                $postponed[$u] = true;
                continue;
            }
            foreach ($network->neighbours($u) as $v) {
                if ($visited[$v] || $network->capacity($u, $v) <= 0) {
                    continue;
                }
                $parent[$v] = $u;
                if ($v === $sink) {
                    $this->state->streak = ($this->state->streak + 1) % 4;
                    $this->state->currentMember = $parent[$parent[$parent[$parent[$v]]]];

                    return $parent;
                }
                $queue[] = $v;
                $visited[$v] = true;
            }
        }

        return null;
    }

    /**
     * Count a path starting at $member; once as many paths were counted as
     * there are members, start counting afresh.
     */
    private function markTaken(int $member): void
    {
        $this->takenCount[$member] = ($this->takenCount[$member] ?? 0) + 1;
        $this->takenTotal++;
        if ($this->takenTotal === count($this->layout->members)) {
            $this->takenCount = [];
            $this->takenTotal = 0;
        }
    }

    /**
     * After a direct path, decide whether the first room is closed to the
     * next search: only while the same member keeps receiving paths and the
     * next course of their group still has several open rooms including it.
     *
     * @param array<int, int> $parent
     */
    private function updateFirstRoomRule(array $parent): void
    {
        $state = $this->state;
        $layout = $this->layout;
        $network = $layout->network;

        if ($state->previousMember !== 0 && $state->previousMember !== $state->currentMember) {
            $state->streak = 0;
            $state->firstRoomClosed = false;
        } elseif ($state->streak !== 0) {
            $group = $parent[$parent[$parent[$network->sink()]]];
            if ($group < $layout->sameTimeNode($layout->sameTimeCount - 1)) {
                $this->closeFirstRoomIfNextCourseHasChoice($group);
            }
        } else {
            $state->firstRoomClosed = false;
        }
        $state->previousMember = $state->currentMember;
    }

    private function closeFirstRoomIfNextCourseHasChoice(int $group): void
    {
        $layout = $this->layout;
        $network = $layout->network;

        $nextCourse = -1;
        for ($i = 0; $i < count($layout->courses); $i++) {
            if ($network->capacity($group, $layout->courseNode($i)) > 0) {
                $nextCourse = $layout->courseNode($i);
                break;
            }
        }

        $openRooms = 0;
        if ($nextCourse !== -1) {
            for ($i = 0; $i < count($layout->rooms); $i++) {
                if ($network->capacity($nextCourse, $layout->roomNode($i)) === 1) {
                    $openRooms++;
                }
            }
        }

        if ($openRooms > 1 && $network->capacity($nextCourse, $layout->roomNode(0)) === 1) {
            $this->state->firstRoomClosed = true;
        }
    }

    /**
     * Split a rerouting path into the direct paths it implies: its last
     * five nodes become a new path for the member at their head, and each
     * earlier member-group pair moves an existing path of the previous
     * member in that group over to that member.
     *
     * @param array<int, int[]> $paths
     * @param int[] $path
     * @return array<int, int[]>
     */
    private function reroute(array $paths, array $path): array
    {
        $hints = [];
        $moved = [];
        $tail = [];
        $memberX = -1;

        for ($i = count($path) - 1; $i !== -1; $i--) {
            if (count($tail) < self::DIRECT_PATH_LENGTH) {
                array_unshift($tail, $path[$i]);
                if (count($tail) === self::DIRECT_PATH_LENGTH) {
                    $memberX = $tail[0];
                    $hints[] = $tail;
                }
            } elseif ($i % 2 === 0) {
                $moved[0][0] = $path[$i];
                $hints[] = $moved[0];
                $memberX = $path[$i];
            } else {
                $group = $path[$i];
                $moved = array_values(array_filter($paths, fn ($p) => $p[0] == $memberX && $p[1] == $group));
                $paths = array_values(array_filter($paths, fn ($p) => $p[0] != $memberX || ($p[0] == $memberX && $p[1] != $group)));
            }
        }

        return array_merge($paths, $hints);
    }
}
