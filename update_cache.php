<?php
/**
 * Cache Updater - Hämta data från Vitec API och cacha lokalt
 * 
 * Denna fil körs manuellt (eller via cron) för att uppdatera 
 * cache-filer från Vitec API
 * 
 * Användning: https://vitec.mcfab.se/update_cache.php
 */

// ===== CONFIGURATION =====
$VITEC_API_BASE = 'https://minasidor.malmocityfastigheter.se';
$API_KEY = 'B1097B1062DFE5CA148A83E48D207386';
$CACHE_DIR = __DIR__;

header('Content-Type: text/plain; charset=utf-8');

echo "=== VITEC CACHE UPDATER ===\n\n";

// ===== UPDATE BOSTÄDER CACHE =====
echo "Uppdaterar cache för bostäder (Group 1)...\n";
$result_bostader = updateCache($VITEC_API_BASE, $API_KEY, 1, 'bostader');
echo "Resultat: " . ($result_bostader['success'] ? "✓ FRAMGÅNG" : "✗ FEL") . "\n";
if (!$result_bostader['success']) {
    echo "  Fel: " . $result_bostader['message'] . "\n";
} else {
    echo "  Antal objekt: " . count($result_bostader['data']) . "\n";
}

echo "\n";

// ===== UPDATE GARAGE CACHE =====
echo "Uppdaterar cache för garage (Group 3)...\n";
$result_garage = updateCache($VITEC_API_BASE, $API_KEY, 3, 'garage');
echo "Resultat: " . ($result_garage['success'] ? "✓ FRAMGÅNG" : "✗ FEL") . "\n";
if (!$result_garage['success']) {
    echo "  Fel: " . $result_garage['message'] . "\n";
} else {
    echo "  Antal objekt: " . count($result_garage['data']) . "\n";
}

echo "\n=== KLART ===\n";

// ===== HELPER FUNCTION =====

function updateCache($api_base, $api_key, $group_id, $type) {
    global $CACHE_DIR;
    
    // Step 1: Authenticate - RÄTT ENDPOINT enligt Vitec
    $auth_url = $api_base . '/api/v1/authentication';
    
    $ch = curl_init($auth_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['Key' => $api_key]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || !$response || $http_code !== 200) {
        return [
            'success' => false,
            'message' => $error ?: 'Authentication failed (HTTP ' . $http_code . ')'
        ];
    }
    
    $token = trim($response, '"');
    
    if (empty($token)) {
        return [
            'success' => false,
            'message' => 'No token received from authentication'
        ];
    }
    
    // Step 2: Fetch objects
    $objects_url = $api_base . '/api/v1/coreexternal/publishedobjects/' . intval($group_id);
    
    $ch = curl_init($objects_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || !$response || $http_code !== 200) {
        return [
            'success' => false,
            'message' => $error ?: 'API request failed (HTTP ' . $http_code . ')'
        ];
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'message' => 'JSON decode error: ' . json_last_error_msg()
        ];
    }
    
    // Step 3: Save to cache
    $cache_file = $CACHE_DIR . '/cache_' . $type . '.json';
    $json_data = json_encode($data);
    
    if (!file_put_contents($cache_file, $json_data, LOCK_EX)) {
        return [
            'success' => false,
            'message' => 'Failed to write cache file: ' . $cache_file
        ];
    }
    
    return [
        'success' => true,
        'data' => $data,
        'cache_file' => $cache_file
    ];
}
?>
