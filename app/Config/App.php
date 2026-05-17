<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Base Site URL
     * --------------------------------------------------------------------------
     *
     * URL to your CodeIgniter root. Typically, this will be your base URL,
     * WITH a trailing slash:
     *
     * E.g., http://example.com/
     */
    public string $baseURL = '';
    /**
     * Allowed Hostnames in the Site URL other than the hostname in the baseURL.
     * If you want to accept multiple Hostnames, set this.
     *
     * E.g.,
     * When your site URL ($baseURL) is 'http://example.com/', and your site
     * also accepts 'http://media.example.com/' and 'http://accounts.example.com/':
     *     ['media.example.com', 'accounts.example.com']
     *
     * @var list<string>
     */
    public array $allowedHostnames = [];

    /**
     * --------------------------------------------------------------------------
     * Index File
     * --------------------------------------------------------------------------
     *
     * Typically, this will be your `index.php` file, unless you've renamed it to
     * something else. If you have configured your web server to remove this file
     * from your site URIs, set this variable to an empty string.
     */
    public string $indexPage = '';

    /**
     * --------------------------------------------------------------------------
     * URI PROTOCOL
     * --------------------------------------------------------------------------
     *
     * This item determines which server global should be used to retrieve the
     * URI string. The default setting of 'REQUEST_URI' works for most servers.
     * If your links do not seem to work, try one of the other delicious flavors:
     *
     *  'REQUEST_URI': Uses $_SERVER['REQUEST_URI']
     * 'QUERY_STRING': Uses $_SERVER['QUERY_STRING']
     *    'PATH_INFO': Uses $_SERVER['PATH_INFO']
     *
     * WARNING: If you set this to 'PATH_INFO', URIs will always be URL-decoded!
     */
    public string $uriProtocol = 'REQUEST_URI';

    /*
    |--------------------------------------------------------------------------
    | Allowed URL Characters
    |--------------------------------------------------------------------------
    |
    | This lets you specify which characters are permitted within your URLs.
    | When someone tries to submit a URL with disallowed characters they will
    | get a warning message.
    |
    | As a security measure you are STRONGLY encouraged to restrict URLs to
    | as few characters as possible.
    |
    | By default, only these are allowed: `a-z 0-9~%.:_-`
    |
    | Set an empty string to allow all characters -- but only if you are insane.
    |
    | The configured value is actually a regular expression character group
    | and it will be used as: '/\A[<permittedURIChars>]+\z/iu'
    |
    | DO NOT CHANGE THIS UNLESS YOU FULLY UNDERSTAND THE REPERCUSSIONS!!
    |
    */
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    /**
     * --------------------------------------------------------------------------
     * Default Locale
     * --------------------------------------------------------------------------
     *
     * The Locale roughly represents the language and location that your visitor
     * is viewing the site from. It affects the language strings and other
     * strings (like currency markers, numbers, etc), that your program
     * should run under for this request.
     */
    public string $defaultLocale = 'ar';

    /**
     * --------------------------------------------------------------------------
     * Negotiate Locale
     * --------------------------------------------------------------------------
     *
     * If true, the current Request object will automatically determine the
     * language to use based on the value of the Accept-Language header.
     *
     * If false, no automatic detection will be performed.
     */
    public bool $negotiateLocale = true;

    /**
     * --------------------------------------------------------------------------
     * Supported Locales
     * --------------------------------------------------------------------------
     *
     * If $negotiateLocale is true, this array lists the locales supported
     * by the application in descending order of priority. If no match is
     * found, the first locale will be used.
     *
     * IncomingRequest::setLocale() also uses this list.
     *
     * @var list<string>
     */
    public array $supportedLocales = ['ar', 'en'];

    /**
     * --------------------------------------------------------------------------
     * Application Timezone
     * --------------------------------------------------------------------------
     *
     * The default timezone that will be used in your application to display
     * dates with the date helper, and can be retrieved through app_timezone()
     *
     * @see https://www.php.net/manual/en/timezones.php for list of timezones
     *      supported by PHP.
     */
    public string $appTimezone = 'UTC';

    /**
     * --------------------------------------------------------------------------
     * Default Character Set
     * --------------------------------------------------------------------------
     *
     * This determines which character set is used by default in various methods
     * that require a character set to be provided.
     *
     * @see http://php.net/htmlspecialchars for a list of supported charsets.
     */
    public string $charset = 'UTF-8';

    /**
     * --------------------------------------------------------------------------
     * Force Global Secure Requests
     * --------------------------------------------------------------------------
     *
     * If true, this will force every request made to this application to be
     * made via a secure connection (HTTPS). If the incoming request is not
     * secure, the user will be redirected to a secure version of the page
     * and the HTTP Strict Transport Security (HSTS) header will be set.
     */
    public bool $forceGlobalSecureRequests = false;

    /**
     * --------------------------------------------------------------------------
     * Reverse Proxy IPs
     * --------------------------------------------------------------------------
     *
     * If your server is behind a reverse proxy, you must whitelist the proxy
     * IP addresses from which CodeIgniter should trust headers such as
     * X-Forwarded-For or Client-IP in order to properly identify
     * the visitor's IP address.
     *
     * You need to set a proxy IP address or IP address with subnets and
     * the HTTP header for the client IP address.
     *
     * Here are some examples:
     *     [
     *         '10.0.1.200'     => 'X-Forwarded-For',
     *         '192.168.5.0/24' => 'X-Real-IP',
     *     ]
     *
     * @var array<string, string>
     */
    public array $proxyIPs = [];

    /**
     * --------------------------------------------------------------------------
     * Content Security Policy
     * --------------------------------------------------------------------------
     *
     * Enables the Response's Content Secure Policy to restrict the sources that
     * can be used for images, scripts, CSS files, audio, video, etc. If enabled,
     * the Response object will populate default values for the policy from the
     * `ContentSecurityPolicy.php` file. Controllers can always add to those
     * restrictions at run time.
     *
     * For a better understanding of CSP, see these documents:
     *
     * @see http://www.html5rocks.com/en/tutorials/security/content-security-policy/
     * @see http://www.w3.org/TR/CSP/
     */
    public bool $CSPEnabled = false;

    // -------------------------------------------------------------------------
    // Cookie defaults (required by CI4 4.3.x Response / Cookie classes)
    // -------------------------------------------------------------------------

    /** @deprecated use Config\Cookie::$prefix instead */
    public string $cookiePrefix = '';

    /** @deprecated use Config\Cookie::$domain instead */
    public string $cookieDomain = '';

    /** @deprecated use Config\Cookie::$path instead */
    public string $cookiePath = '/';

    /** @deprecated use Config\Cookie::$secure instead */
    public bool $cookieSecure = false;

    /** @deprecated use Config\Cookie::$httponly instead */
    public bool $cookieHTTPOnly = true;

    /** @deprecated use Config\Cookie::$samesite instead */
    public ?string $cookieSameSite = 'Lax';

    // -------------------------------------------------------------------------
    // Session defaults (required by CI4 4.3.x)
    // -------------------------------------------------------------------------

    /** @deprecated use Config\Session::$cookieName instead */
    public string $sessionCookieName = 'ci_session';

    /** @deprecated use Config\Session::$expiration instead */
    public int $sessionExpiration = 7200;

    /** @deprecated use Config\Session::$savePath instead */
    public string $sessionSavePath = WRITEPATH . 'session';

    /** @deprecated use Config\Session::$matchIP instead */
    public bool $sessionMatchIP = false;

    /** @deprecated use Config\Session::$timeToUpdate instead */
    public int $sessionTimeToUpdate = 300;

    /** @deprecated use Config\Session::$regenerateDestroy instead */
    public bool $sessionRegenerateDestroy = false;

    // -------------------------------------------------------------------------
    // CSRF (required by CI4 4.3.x Security class)
    // -------------------------------------------------------------------------

    /** @deprecated use Config\Security::$tokenName instead */
    public string $CSRFTokenName = 'csrf_test_name';

    /** @deprecated use Config\Security::$headerName instead */
    public string $CSRFHeaderName = 'X-CSRF-TOKEN';

    /** @deprecated use Config\Security::$cookieName instead */
    public string $CSRFCookieName = 'csrf_cookie_name';

    /** @deprecated use Config\Security::$expires instead */
    public int $CSRFExpire = 7200;

    /** @deprecated use Config\Security::$regenerate instead */
    public bool $CSRFRegenerate = true;

    /** @deprecated use Config\Security::$redirect instead */
    public bool $CSRFRedirect = false;

    /** @deprecated use Config\Security::$samesite instead */
    public string $CSRFSameSite = 'Lax';

    /**
     * Auto-detect baseURL from server globals when it is not supplied
     * via .env / environment variable.
     *
     * CI4 4.4 added strict URL validation in SiteURI::normalizeBaseURL()
     * and will throw a ConfigException if $baseURL is empty or '/'.
     * On cPanel / reverse-proxy hosts the framework cannot reliably
     * auto-detect scheme+host, so we do it here — in our own application
     * code, which is not affected by OPcache on vendor files.
     *
     * Priority order (highest first):
     *   1. Value already set by parent::__construct() via $_ENV / DotEnv
     *   2. Detection from SCRIPT_FILENAME + DOCUMENT_ROOT (most reliable)
     *   3. Fallback detection from SCRIPT_NAME
     */
    public function __construct()
    {
        parent::__construct();

        // Only detect when baseURL is empty or effectively '/'
        if (rtrim($this->baseURL, '/ ') === '') {
            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
                ?? (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                      || (($_SERVER['SERVER_PORT'] ?? 80) == 443)) ? 'https' : 'http');
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

            // SCRIPT_FILENAME minus DOCUMENT_ROOT gives the reliable sub-path
            if (
                isset($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT'])
                && $_SERVER['DOCUMENT_ROOT'] !== ''
                && str_starts_with($_SERVER['SCRIPT_FILENAME'], $_SERVER['DOCUMENT_ROOT'])
            ) {
                $rel  = substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT']));
                $path = rtrim(dirname($rel), '/\\') . '/';
            } else {
                $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\') . '/';
            }

            $this->baseURL = $proto . '://' . $host . $path;
        }
    }
}
