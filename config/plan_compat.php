<?php

/**
 * POST /plans/compatibility (API task 102553, PlanCompatibilityController).
 */
return [
    // A call still working after this many seconds stops before its next Bible and answers 503 (revision 6, P2).
    // Counted from when PHP got the request, database time included. Production's gateway and PHP limits are 60
    // seconds (master has none of develop's 1200-second settings), so the Bible in progress has 15 seconds to finish,
    // far more than one Bible normally takes, and a slow call gets this 503 rather than the gateway's 504. A Bible
    // that waits on several cold cache locks can still run past 60 seconds. Todd's review of the release, #1165.
    'time_limit' => 45,
];
