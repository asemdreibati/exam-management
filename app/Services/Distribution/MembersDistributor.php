<?php

namespace App\Services\Distribution;

use App\Models\Rotation;

interface MembersDistributor
{
    /**
     * Assign room heads, then secretaries, then observers to the rooms of
     * every course in the rotation's exam program. Nothing is persisted.
     */
    public function distribute(Rotation $rotation): DistributionResult;
}
