<?php

/**
 * POST /plans/compatibility (API task 102553, PlanCompatibilityController).
 */
return [
    // A call still working after this many seconds stops before its next Bible and answers 503 (revision 6, P2).
    // Counted from when PHP got the request, database time included.
    'time_limit' => 60,
];
