<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'auth'          => \App\Filters\AuthFilter::class,
        'permission'    => \App\Filters\PermissionFilter::class,
        'ratelimit'     => \App\Filters\RateLimitFilter::class,
        'install_check' => \App\Filters\InstallCheckFilter::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $globals = [
        'before' => [
            'csrf',
            'invalidchars',
            'install_check',
        ],
        'after' => [
            'toolbar',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a particular HTTP method.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any before or after URI patterns.
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [
        'ratelimit' => ['before' => ['auth/login']],
    ];
}
