<?php

namespace App\Services\Distribution\MaxFlow;

/**
 * Heuristic state that carries over from one role's max-flow run to the
 * next within a single distribution (the legacy algorithm kept it in
 * static properties of App\Http\Controllers\MaxFlow\MaxFlow).
 */
final class SelectionState
{
    /** Member node of the previous augmenting path (0 before the first). */
    public int $previousMember = 0;

    /** Member node of the latest augmenting path. */
    public int $currentMember = 0;

    /** Paths found in a row, modulo 4. */
    public int $streak = 0;

    /** Whether the first room node is closed to the next search. */
    public bool $firstRoomClosed = false;
}
