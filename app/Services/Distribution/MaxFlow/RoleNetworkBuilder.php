<?php

namespace App\Services\Distribution\MaxFlow;

use App\Services\Distribution\MemberRole;

/**
 * Builds the flow network for one role, reproducing the graph the
 * original implementation built, from preloaded data.
 *
 * Source -> member: the member's remaining quota.
 * Member -> same-time group: 1, unless the member objects to (or teaches)
 *   a course in the group or already serves in it in an earlier role.
 * Same-time group -> course: the number of rooms the course uses.
 * Course -> room: 1, except rooms already reached by an earlier course of
 *   the same group (a shared room is staffed once).
 * Room -> sink: unlimited.
 */
final class RoleNetworkBuilder
{
    public function __construct(private readonly RotationData $data)
    {
    }

    /**
     * @param array[] $earlierRoles assignments of the roles already distributed, in order
     */
    public function build(MemberRole $role, array $earlierRoles = []): RoleNetwork
    {
        $excluded = $this->excludedCoursesByMember($role);
        $members = array_keys($excluded);
        $courses = $this->coursesByObjectionCount($excluded);
        [$sameTimeOfCourse, $sameTimeCount] = $this->sameTimeGroups($courses);
        $rooms = $this->data->rooms;

        $layout = new RoleNetwork(
            $role,
            new FlowNetwork(count($members) + $sameTimeCount + count($courses) + count($rooms) + 2),
            $members,
            $sameTimeCount,
            $courses,
            $rooms,
        );
        $network = $layout->network;
        $courseIndex = array_flip($courses);
        $memberIndex = array_flip($members);

        foreach ($members as $i => $member) {
            $network->setCapacity($network->source(), $layout->memberNode($i), $this->data->quotas[$member]);
        }
        $this->linkMembersToSameTimeGroups($layout, $excluded, $sameTimeOfCourse, $courseIndex);

        foreach ($earlierRoles as $assignments) {
            $this->removeEarlierAssignments($layout, $assignments, $memberIndex, $courseIndex, $sameTimeOfCourse);
        }

        foreach ($sameTimeOfCourse as $i => $group) {
            $network->setCapacity($layout->sameTimeNode($group), $layout->courseNode($i), 1);
        }
        $roomIndex = array_flip($rooms);
        foreach ($this->data->courseRooms as $course => $courseRooms) {
            foreach ($courseRooms as $room) {
                $network->setCapacity($layout->courseNode($courseIndex[$course]), $layout->roomNode($roomIndex[$room]), 1);
            }
        }
        for ($group = 0; $group < $sameTimeCount; $group++) {
            $this->dropRepeatedRooms($network, $layout->sameTimeNode($group));
        }

        foreach ($sameTimeOfCourse as $i => $group) {
            $roomCount = count($this->data->courseRooms[$courses[$i]] ?? []);
            $network->setCapacity($layout->sameTimeNode($group), $layout->courseNode($i), $roomCount);
        }
        foreach ($rooms as $i => $room) {
            $network->setCapacity($layout->roomNode($i), $network->sink(), PHP_INT_MAX);
        }

        $network->freeze();

        return $layout;
    }

    /**
     * Excluded course ids per member of the role, ordered with the most
     * exclusions first (ties keep pool order).
     *
     * @return array<int, int[]>
     */
    private function excludedCoursesByMember(MemberRole $role): array
    {
        $excluded = [];
        foreach ($this->data->membersOf($role) as $member) {
            $excluded[$member] = $this->data->excludedCourses[$member] ?? [];
        }
        uasort($excluded, fn ($a, $b) => count($b) <=> count($a));

        return $excluded;
    }

    /**
     * Course ids ordered with the most-objected courses first (ties keep
     * exam date/time order).
     *
     * @param array<int, int[]> $excluded
     * @return int[]
     */
    private function coursesByObjectionCount(array $excluded): array
    {
        $objections = [];
        foreach ($this->data->courses as $course) {
            $objections[$course] = 0;
            foreach ($excluded as $courses) {
                if (in_array($course, $courses)) {
                    $objections[$course]++;
                }
            }
        }
        uasort($objections, fn ($a, $b) => $b <=> $a);

        return array_keys($objections);
    }

    /**
     * Group courses whose exams overlap on the same date, directly or
     * through a chain of overlapping exams.
     *
     * @param int[] $courses course ids in node order
     * @return array{0: array<int, int>, 1: int} [group per course node index, group count]
     */
    private function sameTimeGroups(array $courses): array
    {
        $courseIndex = array_flip($courses);
        $groupOf = [];
        $group = -1;
        $groupDate = null;
        $groupEnd = 0;
        foreach ($this->data->courses as $course) {
            $sitting = $this->data->sittings[$course];
            $start = self::minutes($sitting['time']);
            $end = $start + self::minutes($sitting['duration']);

            if ($sitting['date'] !== $groupDate || $start >= $groupEnd) {
                $group++;
                $groupDate = $sitting['date'];
                $groupEnd = $end;
            } else {
                $groupEnd = max($groupEnd, $end);
            }
            $groupOf[$courseIndex[$course]] = $group;
        }
        ksort($groupOf);

        return [$groupOf, $group + 1];
    }

    /**
     * @param array<int, int[]> $excluded
     */
    private function linkMembersToSameTimeGroups(RoleNetwork $layout, array $excluded, array $sameTimeOfCourse, array $courseIndex): void
    {
        $groups = array_unique($sameTimeOfCourse);
        foreach ($layout->members as $i => $member) {
            $blocked = [];
            foreach ($excluded[$member] as $course) {
                if (isset($courseIndex[$course])) {
                    $blocked[$sameTimeOfCourse[$courseIndex[$course]]] = true;
                }
            }
            foreach ($groups as $group) {
                if (!isset($blocked[$group])) {
                    $layout->network->setCapacity($layout->memberNode($i), $layout->sameTimeNode($group), 1);
                }
            }
        }
    }

    /**
     * Lower each member's quota by what an earlier role already gave them,
     * and keep them out of the same-time groups they already serve in.
     */
    private function removeEarlierAssignments(RoleNetwork $layout, array $assignments, array $memberIndex, array $courseIndex, array $sameTimeOfCourse): void
    {
        $network = $layout->network;
        foreach ($assignments['users_observations'] as $member => $observations) {
            if (isset($memberIndex[$member])) {
                $node = $layout->memberNode($memberIndex[$member]);
                $network->setCapacity($network->source(), $node, $this->data->quotas[$member] - count($observations));
            }
        }
        foreach ($assignments['users_observations'] as $member => $observations) {
            if (isset($memberIndex[$member])) {
                $node = $layout->memberNode($memberIndex[$member]);
                foreach ($observations as $observation) {
                    $group = $sameTimeOfCourse[$courseIndex[$observation['course']]];
                    $network->setCapacity($node, $layout->sameTimeNode($group), 0);
                }
            }
        }
    }

    /**
     * Depth-first walk from a same-time node over unit edges. An edge to a
     * node already reached is removed, so a room shared by courses of the
     * group stays linked only to the first course that reaches it.
     */
    private function dropRepeatedRooms(FlowNetwork $network, int $start): void
    {
        $visited = [];
        $walk = function (int $node) use (&$walk, &$visited, $network): void {
            $visited[$node] = true;
            foreach ($network->outgoing($node) as $next) {
                if ($next === $network->sink() || $network->capacity($node, $next) !== 1) {
                    continue;
                }
                if (isset($visited[$next])) {
                    $network->setCapacity($node, $next, 0);
                } else {
                    $walk($next);
                }
            }
        };
        $walk($start);
    }

    /**
     * Minutes in "H:i[:s]", a time of day or a duration such as "1:30".
     */
    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
