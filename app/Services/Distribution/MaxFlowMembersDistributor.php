<?php

namespace App\Services\Distribution;

use App\Http\Controllers\MaxFlow\EnumPersonType;
use App\Http\Controllers\MaxFlow\Graph;
use App\Http\Controllers\MaxFlow\MaxFlow;
use App\Models\Rotation;

/**
 * Runs the original max-flow algorithm once per role. Each later role's
 * graph is built with the earlier roles' assignments already removed.
 */
final class MaxFlowMembersDistributor implements MembersDistributor
{
    public function distribute(Rotation $rotation): DistributionResult
    {
        MaxFlow::resetState();

        [$roomHeadPaths, $roomHeads] = (new Graph(EnumPersonType::RoomHead, $rotation))->applyMaxFlowAlgorithm();
        if (!count($roomHeadPaths)) {
            return DistributionResult::unfilled(EnumPersonType::RoomHead);
        }

        [$secretaryPaths, $secretaries] = (new Graph(EnumPersonType::Secertary, $rotation, $roomHeads))->applyMaxFlowAlgorithm();
        if (!count($secretaryPaths)) {
            return DistributionResult::unfilled(EnumPersonType::Secertary);
        }

        [$observerPaths, $observers] = (new Graph(EnumPersonType::Observer, $rotation, $roomHeads, $secretaries))->applyMaxFlowAlgorithm();
        if (!count($observerPaths)) {
            return DistributionResult::unfilled(EnumPersonType::Observer);
        }

        return DistributionResult::success($roomHeads, $secretaries, $observers);
    }
}
