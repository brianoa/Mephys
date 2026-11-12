<?php
/** Public clinic information. One source of truth for both public footers and maps. */
if (!function_exists('mephysClinicLocations')) {
    function mephysClinicLocations(): array {
        return [
            'syokimau' => [
                'short' => 'Syokimau Katani',
                'name' => 'Syokimau Katani - Veteran Hospital Doctors Plaza, Ground Floor',
                'address' => 'Veteran Hospital Doctors Plaza, Ground Floor',
                'map_query' => 'Veteran Hospital Doctors Plaza, Katani, Syokimau, Kenya',
            ],
            'komarocks' => [
                'short' => 'Komarocks Sector 2',
                'name' => 'Komarocks Sector 2 - Dawasmile Medical Centre',
                'address' => 'Dawasmile Medical Centre',
                'map_query' => 'Dawasmile Medical Centre, Komarocks Sector 2, Nairobi, Kenya',
            ],
        ];
    }
    function mephysClinicMapUrl(string $key, bool $embed = false): string {
        $locations = mephysClinicLocations();
        if (!isset($locations[$key])) return '../location.php#map';
        $query = rawurlencode($locations[$key]['map_query']);
        return $embed
            ? 'https://www.google.com/maps?q=' . $query . '&output=embed'
            : 'https://www.google.com/maps/search/?api=1&query=' . $query;
    }
    function mephysClinicSocialUrl(string $network): string {
        $env = $network === 'instagram' ? 'MEPHYS_INSTAGRAM_URL' : 'MEPHYS_TIKTOK_URL';
        $allowed = $network === 'instagram' ? ['instagram.com', 'www.instagram.com'] : ['tiktok.com', 'www.tiktok.com'];
        $url = trim((string) getenv($env));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_starts_with($url, 'https://') && in_array($host, $allowed, true)) return $url;
        // When the official account is unknown, open an honest discovery search, not an invented profile.
        return 'https://www.google.com/search?q=' . rawurlencode('site:' . ($network === 'instagram' ? 'instagram.com' : 'tiktok.com') . ' Mephys Physiotherapy Clinic Kenya');
    }
}
