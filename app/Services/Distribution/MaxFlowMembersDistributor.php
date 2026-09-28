<?php

namespace App\Services\Distribution;

use App\Http\Controllers\MaxFlow\EnumPersonType;
use App\Models\Rotation;
use App\Services\Distribution\MaxFlow\AugmentingPathSolver;
use App\Services\Distribution\MaxFlow\RoleNetwork;
use App\Services\Distribution\MaxFlow\RoleNetworkBuilder;
use App\Services\Distribution\MaxFlow\RotationData;
use App\Services\Distribution\MaxFlow\SelectionState;

/**
 * Distributes members with one max-flow run per role (room heads, then
 * secretaries, then observers), each role's network built without the
 * members' earlier assignments.
 *
 * Produces the same assignments as LegacyMaxFlowMembersDistributor, but
 * loads the rotation once and works on sparse networks instead of issuing
 * queries inside loops and scanning V x V matrices.
 */
final class MaxFlowMembersDistributor implements MembersDistributor
{
    private const ROLES = [EnumPersonType::RoomHead, EnumPersonType::Secertary, EnumPersonType::Observer];

    public function distribute(Rotation $rotation): DistributionResult
    {
        $data = RotationData::load($rotation);
        $builder = new RoleNetworkBuilder($data);
        $state = new SelectionState();

        $assignments = [];
        foreach (self::ROLES as $role) {
            $layout = $builder->build($role, $assignments);
            $paths = (new AugmentingPathSolver($layout, $state))->solve();
            if (!count($paths)) {
                return DistributionResult::unfilled($role);
            }
            $assignments[] = $this->toAssignments($layout, $paths, $data);
        }

        return DistributionResult::success(...$assignments);
    }

    /**
     * Turn node paths into assignments grouped by member, in member node
     * order and then path order.
     *
     * @param array<int, int[]> $paths
     */
    private function toAssignments(RoleNetwork $layout, array $paths, RotationData $data): array
    {
        $pathsByMemberNode = [];
        foreach ($paths as $path) {
            $pathsByMemberNode[$path[0]][] = $path;
        }
        ksort($pathsByMemberNode);

        $assignments = [];
        foreach ($pathsByMemberNode as $memberNode => $memberPaths) {
            foreach ($memberPaths as $path) {
                $course = $layout->courseAt($path[2]);
                $sitting = $data->sittings[$course];
                $assignments['users_observations'][$layout->memberAt($memberNode)][] = [
                    'course' => $course,
                    'room' => $layout->roomAt($path[3]),
                    'date' => $sitting['date'],
                    'time' => $sitting['time'],
                    'duration' => $sitting['duration'],
                    'roleIn' => $layout->role->name,
                ];
            }
        }

        return $assignments;
    }
}
