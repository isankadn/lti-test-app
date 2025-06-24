<?php
// Load central configuration
require_once __DIR__ . '/config.php';

/**
 * LTI 1.3 Platform JWKS Endpoint
 * Provides public keys for JWT verification by tools
 */

header('Content-Type: application/json');

logMessage("JWKS endpoint called", $_REQUEST, 'JWKS');

/**
 * Get platform's public key details for JWKS endpoint
 */
function getPlatformPublicKeyDetails() {
    $keyFile = __DIR__ . '/platform_publickey.crt';

    if (!file_exists($keyFile)) {
        throw new Exception("Platform public key file not found: $keyFile");
    }

    $publicKey = file_get_contents($keyFile);
    $keyResource = openssl_pkey_get_public($publicKey);

    if (!$keyResource) {
        throw new Exception("Invalid platform public key format");
    }

    $keyDetails = openssl_pkey_get_details($keyResource);
    return $keyDetails;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

try {
    $keyDetails = getPlatformPublicKeyDetails();

    if (!$keyDetails || !isset($keyDetails['rsa'])) {
        throw new Exception('Unable to extract platform public key details');
    }

    $rsaKey = $keyDetails['rsa'];

    // Create JWK (JSON Web Key)
    $jwk = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => 'platform-key-1',
        'n' => base64UrlEncode($rsaKey['n']),
        'e' => base64UrlEncode($rsaKey['e'])
    ];

    // Create JWKS response
    $jwks = [
        'keys' => [$jwk]
    ];

    logMessage("JWKS response sent", ['key_count' => count($jwks['keys'])], 'JWKS');

    echo json_encode($jwks, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    logMessage("JWKS error", ['error' => $e->getMessage()], 'JWKS');

    $errorResponse = [
        'error' => 'key_generation_failed',
        'message' => 'Unable to provide public key: ' . $e->getMessage(),
        'keys' => []
    ];

    http_response_code(500);
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}
?>