<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside on the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => 'horizon',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its own
    | threshold, which can be specified in seconds, minutes or hours.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, you may wish for these
    | to be persisted for longer than the rest of your jobs so you can have
    | time to review and debug them.
    |
    */

    'trim' => [
        'recent' => 60,
        'recent_failed' => 10080, // 7 dias
        'monitored' => 10080, // 7 dias
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait for all of the current jobs in the queue to finish executing
    | before terminating. Instead, Horizon will only wait for the current
    | job to finish executing before terminating.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory a Horizon worker may
    | consume before it is terminated and restarted. You should set this
    | value according to the resources available to your server.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings work in harmony
    | with the queue configuration in your application's config/queue.php.
    |
    */

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 10,
                'maxTime' => 0,
                'maxJobs' => 0,
                'memory' => 128,
                'tries' => 3,
                'timeout' => 300,
                'nice' => 0,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'force' => false,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'processes' => 3,
                'tries' => 3,
                'timeout' => 300,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | The following array of classes will be ignored by Horizon's exception
    | reporter, allowing you to silence exceptions thrown by jobs that you
    | do not want to appear in Horizon's exception reports.
    |
    */

    'silenced' => [
        // \App\Jobs\ExampleJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | Horizon. This will get used in combination with Horizon's intervals
    | to determine the exact number of snapshots to keep in memory.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Balancing Strategy
    |--------------------------------------------------------------------------
    |
    | This option determines which balancing strategy should be used to assign
    | jobs to queue workers. By default, Laravel will use a simple strategy
    | where each worker is assigned jobs in a round-robin fashion.
    |
    | Supported: "simple", "auto"
    |
    */

    'balance' => 'auto',

    /*
    |--------------------------------------------------------------------------
    | Snapshot Every Seconds
    |--------------------------------------------------------------------------
    |
    | This option determines how often Horizon should take a snapshot of the
    | queue metrics. This snapshot is used to display metrics in Horizon's
    | dashboard. You may adjust this value based on your application needs.
    |
    */

    'snapshot_every' => 60,

];
