<?php

declare(strict_types=1);

use App\Services\Demo\Steps\AccountsStep;
use App\Services\Demo\Steps\AuditStep;
use App\Services\Demo\Steps\FinanceStep;
use App\Services\Demo\Steps\MembershipStep;
use App\Services\Demo\Steps\PosStep;
use App\Services\Demo\Steps\WorkspacesStep;

return [
    // `.test` is a reserved TLD, so nothing sent to these addresses can be delivered.
    'email_domain' => 'demo.taqat.test',

    'slug_prefix' => 'demo-',

    // The command runs only where the environment is one of these, or where the
    // APP_URL host contains the marker (staging does not always run APP_ENV=staging).
    'allowed_environments' => ['local', 'testing'],
    'staging_host_marker' => 'staging',

    // Refused whatever APP_ENV says.
    'forbidden_hosts' => ['taqat.space', 'www.taqat.space', 'api.taqat.space'],

    // One class per data area, run in order. Later milestones append here.
    'steps' => [
        AccountsStep::class,
        WorkspacesStep::class,
        MembershipStep::class,
        FinanceStep::class,
        PosStep::class,
        AuditStep::class,
    ],
];
