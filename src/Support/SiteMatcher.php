<?php

namespace PsobPsay\Client\Support;

/**
 * Match a request host to a publisher site domain the same way serving and page
 * context do: the registered bare domain or its canonical `www.` host. Explicitly
 * registered subdomains match only themselves (and their `www.`); sibling sites
 * never share schema or zones.
 */
final class SiteMatcher
{
    public static function normalizeHost(?string $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        return $host === '' ? null : $host;
    }

    /**
     * Strip a leading `www.` so configured `ADS_SITE` values can be entered either way.
     */
    public static function normalizeConfiguredDomain(?string $domain): ?string
    {
        $domain = self::normalizeHost($domain);

        if ($domain === null) {
            return null;
        }

        return preg_replace('/\Awww\./', '', $domain) ?: $domain;
    }

    public static function hostMatchesDomain(string $host, string $domain): bool
    {
        $host = self::normalizeHost($host);
        $domain = self::normalizeHost($domain);

        return $host !== null && $domain !== null && ($host === $domain || $host === 'www.'.$domain);
    }

    public static function pageMatchesSite(string $page, string $domain): bool
    {
        return self::hostMatchesDomain((string) parse_url($page, PHP_URL_HOST), $domain);
    }

    /**
     * @param  iterable<int, mixed>  $sites
     * @return array<string, mixed>|null
     */
    public static function siteForHost(iterable $sites, string $host): ?array
    {
        foreach ($sites as $site) {
            if (! is_array($site) || ! is_string($site['domain'] ?? null)) {
                continue;
            }

            if (self::hostMatchesDomain($host, $site['domain'])) {
                return $site;
            }
        }

        return null;
    }

    /**
     * @param  iterable<int, mixed>  $sites
     * @return array<string, mixed>|null
     */
    public static function siteForPage(iterable $sites, string $page): ?array
    {
        $host = self::normalizeHost((string) parse_url($page, PHP_URL_HOST));

        return $host === null ? null : self::siteForHost($sites, $host);
    }

    /**
     * Resolve the console site for diagnostics: configured domain, else app URL host.
     *
     * @param  iterable<int, mixed>  $sites
     * @return array<string, mixed>|null
     */
    public static function siteForConfiguredDomain(iterable $sites, ?string $configured, ?string $fallbackHost = null): ?array
    {
        $domain = self::normalizeConfiguredDomain($configured)
            ?? self::normalizeConfiguredDomain($fallbackHost);

        if ($domain === null) {
            return null;
        }

        return self::siteForHost($sites, $domain);
    }
}
