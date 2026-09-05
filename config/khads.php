<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sponsored Slots
    |--------------------------------------------------------------------------
    |
    | Ads come from a separate ad server (khads) over its server-to-server API.
    | This site is a publisher there: the site is verified and each placement
    | on this side is a zone on that side. Nothing here runs unless enabled and
    | a key is set, and a failing ad server never fails a page — the slot
    | simply stays empty.
    |
    */

    'enabled' => (bool) env('ADS_ENABLED', false),

    /** Origin of the ad server's serving host, e.g. https://s.khable.com. */
    'base_url' => rtrim((string) env('ADS_BASE_URL', ''), '/'),

    /** The publisher API key issued in the ad console. Server side only. */
    'api_key' => env('ADS_API_KEY'),

    /**
     * How long to wait for the ad server's answer. When every placement is a
     * deferred prop, this budget is spent on a follow-up request after the page
     * has already drawn — nothing the reader is looking at waits on it, which is
     * why it is not the sub-150ms a render-blocking call would deserve. Measured
     * against a local khads, being turned away for a bad key already costs about
     * 100ms before any auction runs, so a budget near that leaves a real serve no
     * room: the circuit trips on every view and no slot ever fills.
     */
    'timeout_ms' => (int) env('ADS_TIMEOUT_MS', 500),

    /**
     * Connecting is separate and stays tight. A host that is down should cost one
     * short failure even though a host that is merely slow is given room to think.
     */
    'connect_timeout_ms' => (int) env('ADS_CONNECT_TIMEOUT_MS', 150),

    /** Seconds to stop asking after a failure, so a down ad server costs one timeout, not one per page view. */
    'circuit_seconds' => (int) env('ADS_CIRCUIT_SECONDS', 60),

    /**
     * The request header carrying the visitor's ISO country, set by the edge the
     * site sits behind. Cloudflare's is the default; leave empty behind no edge
     * and the ad server geolocates nothing.
     */
    'country_header' => env('ADS_COUNTRY_HEADER', 'CF-IPCountry'),

    /** Optional link back to the publisher console, shown to platform admins. */
    'console_url' => rtrim((string) env('ADS_CONSOLE_URL', ''), '/'),

    /**
     * Public advertiser acquisition stays separately gated during the closed
     * beta. Enabling it without a valid destination keeps the page hidden.
     */
    'advertise_enabled' => (bool) env('ADS_ADVERTISE_ENABLED', false),
    'advertiser_url' => rtrim((string) env('ADS_ADVERTISER_URL', ''), '/'),

    /**
     * Zone public keys, one per placement. The placement names are this site's
     * own — they are what `KhadsClient::slotsFor()` is asked for and the keys the
     * page receives back — and each maps to a zone created for this site in the
     * ad console. An empty value disables that placement.
     *
     *     'zones' => [
     *         'feed' => env('ADS_ZONE_FEED'),
     *         'sidebar' => env('ADS_ZONE_SIDEBAR'),
     *     ],
     */
    'zones' => [],

];
