<?php

return [
    'history' => [
        '1.1.1' => [
            'name' => 'Reports checked against SGC',
            'summary' => 'Citizen reports remain private and only contribute a count to an official SGC earthquake after the participant threshold is reached.',
            'changes' => [
                'One citizen_reports table in the existing MySQL database; reports never create earthquakes.',
                'At least three distinct participants and one matching SGC event in time and distance; ambiguous cases remain pending.',
                'Reported by N users count in the map, list, history, detail and API, separate from official felt reports.',
                'CSRF, single-use session challenge, honeypot and session/network rate limits.',
                'HMAC identifiers, network and IPv6 /64 deduplication and bounded private-data retention.',
                'Legacy reports excluded from public queries without deleting them or counting them as identified participants.',
                'Public editing and deletion blocked to prevent unauthenticated changes to official events.',
                'Logic and HTTP protection tests; MySQL test with rolled-back fixtures in the existing database.',
            ],
        ],
        '1.1.0' => [
            'name' => 'API and contracts',
            'summary' => 'Input validation and consistent JSON responses across the earthquake and citizen network APIs.',
            'changes' => [
                'Validate coordinates, radius, sort order and numeric parameters before accessing data.',
                'Validate JSON request bodies and normalize booleans in device requests.',
                'Return JSON and Cache-Control no-store for authentication and persistence errors too.',
                'Include X-SIGRA-Version and X-API-Version headers in both APIs.',
                'Compare keys with hash_equals and reject protected requests without a configured key in production.',
                'Reject invalid optional device tokens with 401 instead of treating them as anonymous.',
                'Preserve Android routes and response fields; the API contract remains v1.',
                'Document both APIs and test CGI routes and responses with isolated persistence.',
            ],
        ],
        '1.0.4' => array (
  'name' => 'Filters and presentation',
  'summary' => 'Fixes for distance filtering, translated release history and vector icons.',
  'changes' => 
  array (
    0 => 'Radius selection available on load; choosing a distance requests location.',
    1 => 'Timeout and retry for IP geolocation; preferences work without local storage.',
    2 => 'Complete Spanish/English translation of release history and channel labels.',
    3 => 'Interface emojis replaced with local SVG icons.',
    4 => 'Refreshed cache and network updates for scripts and styles.',
    5 => 'Distance filter regression tests without a browser or MySQL.',
  ),
),
        '1.0.3' => [
            'name' => 'Global reach',
            'summary' => 'SIGRA expands beyond Colombia: SGC and USGS events share one world map with a radius filter around your location.',
            'changes' => [
                'Unified SGC (Colombia) and USGS (worldwide) ingestion into the same table and map.',
                'Automatic deduplication between sources to avoid showing the same earthquake twice.',
                'Near me filter with a configurable radius (50 to 5,000 km) and sorting by distance.',
                'Source filter and worldwide place search.',
                'Magnitude filter (M2.5+ to M6+) and a compact, collapsible filter panel.',
                'Bilingual Spanish/English interface with a navbar language selector and automatic translation.',
                'New icons and colors for earthquake reports and browser alerts.',
                'Third-party legal notices in LICENSE and THIRD_PARTY_NOTICES.md.',
                'Global synchronization with a disk cache and the `php spark sigra:sync` command for cron.',
                'API `/api/sismos/recent` with lat, lng, radius_km and order=distancia.',
                'USGS event details: alert level, tsunami risk, significance and official report.',
                'Open source preparation: README, license, contribution guide, security policy and example .env.',
            ],
        ],
        '1.0.2' => [
            'name' => 'Live network',
            'summary' => 'The citizen network joins the navbar, SMS is removed, and browser alerts, the PWA and animated wave propagation arrive.',
            'changes' => [
                'Live network in the navbar with a phone counter and alert status.',
                'Browser alerts with Web Notifications, sound and an emergency overlay counting down to the S wave.',
                'Installable PWA: manifest, service worker, icons and an offline page.',
                'Animated P and S wave propagation on the /red map.',
                'Your risk now panel with distance, ETA and risk level; location stays in the browser.',
                'Seismic activity playback on /sismos.',
                'Shareable alerts from /red through the Web Share API or clipboard.',
                'Complete removal of SMS, OTP, Twilio and the sms_subscriptions table.',
                'Hardened .htaccess, versioned health check and test documentation.',
            ],
        ],
        '1.0.1' => [
            'name' => 'Phone network',
            'summary' => 'First release of the citizen P-wave network and Android app.',
            'changes' => [
                'Network backend: devices, detections, alerts and deliveries.',
                'TDOA fusion, magnitude estimation, confidence and S-wave ETA.',
                'API /api/red/* for registration, heartbeat, detection and alerts.',
                'Public /red dashboard with network statistics and map.',
                'Android app with foreground service, sensor and notifications.',
                'Optional FCM and fallback polling.',
            ],
        ],
        '1.0.0' => [
            'name' => 'Seismic map',
            'summary' => 'First public release of the SGC event viewer.',
            'changes' => [
                'Interactive map with Leaflet and OpenStreetMap.',
                'Synchronization with the official SGC feed.',
                'Searchable history, filters and event details.',
                'Citizen location reports.',
                'Light/dark themes and visual and sound alerts.',
            ],
        ],
    ],
];
