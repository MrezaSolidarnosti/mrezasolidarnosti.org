<?php

$guest = [
    '/',
    // Every public step of the login flow, for every entity type. The wildcard is the
    // entity-type segment: /login/user/..., /login/delegate/..., and whatever is registered
    // next. Two-factor is not listed because this app does not use it — the pending-login
    // state lives in the session, so those paths would need to be added here to switch it on.
    '/login/*/magicLinkForm/',
    '/login/*/requestMagicLink/',
    '/login/*/verifyMagicLink/',
    '/cron/*',
];

// staff
$level2 = [
    '/delegate/view/*',
    '/delegate/tableHandler/*',
    '/delegate/form/*',
    '/delegate/update/*',
    '/donor/update/*',
    '/donor/view/*',
    '/donor/tableHandler/*',
    '/donor/form/*',
    '/transaction/update/*',
    '/transaction/view/*',
    '/transaction/tableHandler/*',
    '/transaction/form/*',
    '/statistics',
    '/school/*',
    '/school/*',
    '/schoolType/*',
    '/city/*',
    '/user/view/',
    '/user/tableHandler/',

    '/user/update/*',
    '/login/logout/',
];

$level1 = [
    '/cache/*',
    '/user/*',
    '/donor/*',
    '/delegate/*',
    '/transaction/*',
    '/template/*',
    '/translator/*',
    '/activity/*',
    '/emailList/*',
];

// Delegate permissions (role 10)
$delegate = [
    '/delegate/view/*',
    '/delegate/update/*',
    '/school/view/*',
    '/school/tableHandler/*',
    '/school/form/*',
    '/login/logout/',
];

//can also see everything level2 can see
$level1 = array_merge($level2, $level1);

return [
    0 => $guest,
    1 => $level1,
    2 => $level2,
    10 => $delegate,
];
