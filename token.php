<?php
/**
 * LTI 1.3 Platform Token Endpoint
 * This handles OAuth 2.0 token requests from LTI tools (Moodle/Bookroll)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

// Log EVERY request to token endpoint, even invalid ones
logMessage("TOKEN ENDPOINT HIT - ALL REQUESTS", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'not_set',
    'query_string' => $_SERVER['QUERY_STRING'] ?? 'not_set',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not_set',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not_set',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'not_set',
    'all_headers' => getallheaders(),
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
], 'TOKEN_DEBUG');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Simplified and robust POST data parsing
$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

// Start with $_POST if available
$postData = $_POST;

// If $_POST is empty, try parsing raw input
if (empty($postData) && !empty($rawInput)) {
    if (strpos($contentType, 'application/json') !== false) {
        $jsonData = json_decode($rawInput, true);
        $postData = $jsonData ?: [];
    } else {
        // Parse as URL-encoded
        parse_str($rawInput, $postData);
    }
}

// Extract parameters with multiple fallback methods
$client_id = $postData['client_id'] ?? $_POST['client_id'] ?? $_GET['client_id'] ?? '';
$client_assertion = $postData['client_assertion'] ?? $_POST['client_assertion'] ?? '';
$client_assertion_type = $postData['client_assertion_type'] ?? $_POST['client_assertion_type'] ?? '';
$grant_type = $postData['grant_type'] ?? $_POST['grant_type'] ?? $_GET['grant_type'] ?? '';
$scope = $postData['scope'] ?? $_POST['scope'] ?? $_GET['scope'] ?? '';

logMessage("Token endpoint - Final parsed data", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $contentType,
    'raw_input_length' => strlen($rawInput),
    'raw_input_preview' => substr($rawInput, 0, 300),
    'post_globals' => $_POST,
    'parsed_data' => $postData,
    'extracted_params' => [
        'client_id' => $client_id,
        'grant_type' => $grant_type,
        'client_assertion_present' => !empty($client_assertion),
        'scope' => $scope
    ]
], 'TOKEN');

// Also check for HTTP Basic Authentication
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$basic_auth_client_id = '';
$basic_auth_secret = '';

if (preg_match('/Basic\s+(.*)$/i', $auth_header, $matches)) {
    $credentials = base64_decode($matches[1]);
    if (strpos($credentials, ':') !== false) {
        list($basic_auth_client_id, $basic_auth_secret) = explode(':', $credentials, 2);
    }
}

// Use client_id from Basic Auth if not in POST
if (empty($client_id) && !empty($basic_auth_client_id)) {
    $client_id = $basic_auth_client_id;
}

logMessage("AUTHENTICATION METHODS DETECTED", [
    'post_client_id' => $postData['client_id'] ?? 'not_set',
    'basic_auth_client_id' => $basic_auth_client_id ?: 'not_set',
    'final_client_id' => $client_id,
    'has_client_assertion' => !empty($client_assertion),
    'client_assertion_type' => $client_assertion_type,
    'has_basic_auth' => !empty($basic_auth_client_id),
    'auth_header_present' => !empty($auth_header),
    'grant_type' => $grant_type,
    'scope' => $scope
], 'TOKEN');

// Validate required parameters - for OAuth2 client credentials, we need grant_type and usually client_assertion
if (!$grant_type) {
    logMessage("Missing grant_type - DETAILED DEBUG", [
        'grant_type_present' => !empty($grant_type),
        'grant_type_value' => $grant_type,
        'all_post_data' => $postData,
        'all_post_globals' => $_POST,
        'all_get_data' => $_GET,
        'content_type' => $contentType,
        'raw_input_preview' => substr(file_get_contents('php://input'), 0, 500),
        'headers_received' => getallheaders()
    ], 'TOKEN');

    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_request',
        'message' => 'Missing required parameter: grant_type',
        'debug_info' => [
            'received_grant_type' => $grant_type ?: 'EMPTY',
            'content_type' => $contentType,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
    exit;
}

// For client_credentials grant type, we might get client_id from client_assertion JWT
if (!$client_id && !empty($client_assertion)) {
    // Try to extract client_id from client assertion JWT
    $jwtParts = explode('.', $client_assertion);
    if (count($jwtParts) === 3) {
        try {
            $payload = json_decode(base64_decode(str_pad(strtr($jwtParts[1], '-_', '+/'), strlen($jwtParts[1]) % 4, '=', STR_PAD_RIGHT)), true);
            if (isset($payload['sub'])) {
                $client_id = $payload['sub'];
                logMessage("Extracted client_id from JWT assertion", [
                    'extracted_client_id' => $client_id,
                    'jwt_payload' => $payload
                ], 'TOKEN');
            }
        } catch (Exception $e) {
            logMessage("Failed to extract client_id from JWT", ['error' => $e->getMessage()], 'TOKEN');
        }
    }
}

// Final validation
if (!$client_id) {
    logMessage("Missing client_id after all attempts", [
        'client_id_from_post' => $postData['client_id'] ?? 'missing',
        'client_id_from_jwt' => 'extraction_failed',
        'client_assertion_present' => !empty($client_assertion),
        'client_assertion_preview' => $client_assertion ? substr($client_assertion, 0, 100) . '...' : 'empty'
    ], 'TOKEN');

    http_response_code(400);
    echo json_encode([
        'error' => 'invalid_request',
        'message' => 'Missing client_id - provide either as parameter or in client_assertion JWT'
    ]);
    exit;
}

// Log what we received for debugging
logMessage("TOKEN REQUEST DETAILS", [
    'client_id' => $client_id,
    'grant_type' => $grant_type,
    'client_assertion_length' => strlen($client_assertion ?? ''),
    'scope' => $scope,
    'has_client_assertion' => !empty($client_assertion)
], 'TOKEN');

// Only support client_credentials grant type
if ($grant_type !== 'client_credentials') {
    logMessage("Unsupported grant type", ['grant_type' => $grant_type], 'TOKEN');
    http_response_code(400);
    echo json_encode(['error' => 'unsupported_grant_type']);
    exit;
}

// Validate client_id (supports Moodle, Bookroll, and Analysis)
$validClientIds = [MOODLE_CLIENT_ID, BOOKROLL_CLIENT_ID, ANALYSIS_CLIENT_ID];
if (!in_array($client_id, $validClientIds)) {
    logMessage("Invalid client_id", [
        'received' => $client_id,
        'expected_moodle' => MOODLE_CLIENT_ID,
        'expected_bookroll' => BOOKROLL_CLIENT_ID,
        'expected_analysis' => ANALYSIS_CLIENT_ID
    ], 'TOKEN');
    http_response_code(401);
    echo json_encode(['error' => 'invalid_client', 'message' => 'Invalid client_id']);
    exit;
}

// Determine tool type
$toolType = 'moodle'; // default
if ($client_id === BOOKROLL_CLIENT_ID) {
    $toolType = 'bookroll';
} elseif ($client_id === ANALYSIS_CLIENT_ID) {
    $toolType = 'analysis';
}
logMessage("Token request from tool", ['tool_type' => $toolType, 'client_id' => $client_id], 'TOKEN');

try {
    // Validate client assertion JWT if provided (required by LTI 1.3 spec)
    if (!empty($client_assertion)) {
        logMessage("Validating client assertion JWT", [
            'client_assertion_type' => $client_assertion_type,
            'assertion_length' => strlen($client_assertion),
            'assertion_preview' => substr($client_assertion, 0, 100) . '...'
        ], 'TOKEN');

        // Basic JWT validation (in production, verify signature with tool's public key)
        $jwtParts = explode('.', $client_assertion);
        if (count($jwtParts) === 3) {
            $header = json_decode(base64_decode(str_pad(strtr($jwtParts[0], '-_', '+/'), strlen($jwtParts[0]) % 4, '=', STR_PAD_RIGHT)), true);
            $payload = json_decode(base64_decode(str_pad(strtr($jwtParts[1], '-_', '+/'), strlen($jwtParts[1]) % 4, '=', STR_PAD_RIGHT)), true);

            logMessage("Client assertion JWT decoded", [
                'header' => $header,
                'payload_iss' => $payload['iss'] ?? 'missing',
                'payload_sub' => $payload['sub'] ?? 'missing',
                'payload_aud' => $payload['aud'] ?? 'missing',
                'payload_exp' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'missing'
            ], 'TOKEN');

            // Basic validation
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                throw new Exception('Client assertion JWT expired');
            }
            if (!isset($payload['aud']) || $payload['aud'] !== PLATFORM_TOKEN_URL) {
                logMessage("Client assertion audience mismatch", [
                    'expected' => PLATFORM_TOKEN_URL,
                    'received' => $payload['aud'] ?? 'missing'
                ], 'TOKEN');
                // Continue anyway for demo purposes
            }
        } else {
            logMessage("Invalid client assertion JWT format", ['parts' => count($jwtParts)], 'TOKEN');
        }
    } else {
        logMessage("No client assertion provided - continuing anyway for demo", [], 'TOKEN');
    }

    // Create access token for the tool
    logMessage("Creating access token for " . ucfirst($toolType), [
        'client_id' => $client_id,
        'scope' => $scope,
        'tool_type' => $toolType
    ], 'TOKEN');

    $issuedAt = time();
    $expiresAt = $issuedAt + 3600; // 1 hour

    // Create a proper JWT access token
    $tokenPayload = [
        'iss' => PLATFORM_ISSUER,
        'sub' => $client_id,
        'aud' => [$client_id, PLATFORM_DOMAIN],
        'iat' => $issuedAt,
        'exp' => $expiresAt,
        'scope' => $scope,
        'tool_type' => $toolType,
        'token_use' => 'access_token'
    ];

    // Create JWT header
    $header = [
        'typ' => 'JWT',
        'alg' => 'RS256',
        'kid' => JWT_KEY_ID
    ];

    // Create JWT creation functions locally to avoid including auth.php
    function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function getPlatformPrivateKey() {
        $keyFile = __DIR__ . '/platform_pkcs8.key';
        if (!file_exists($keyFile)) {
            throw new Exception("Platform private key file not found: $keyFile. Please ensure platform keys are generated.");
        }
        return file_get_contents($keyFile);
    }

    function createJWT($header, $payload) {
        $headerEncoded = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $dataToSign = $headerEncoded . '.' . $payloadEncoded;

        // Get platform's private key for signing
        $privateKey = getPlatformPrivateKey();
        $key = openssl_pkey_get_private($privateKey);

        if (!$key) {
            throw new Exception('Invalid private key: ' . openssl_error_string());
        }

        // Sign with RS256
        $signResult = openssl_sign($dataToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$signResult) {
            throw new Exception('Failed to sign JWT: ' . openssl_error_string());
        }

        $signatureEncoded = base64UrlEncode($signature);
        return $dataToSign . '.' . $signatureEncoded;
    }

    // Use the same JWT creation function as auth.php
    $accessToken = createJWT($header, $tokenPayload);

    $response = [
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => $scope
    ];

    logMessage("Access token issued successfully", [
        'token_length' => strlen($accessToken),
        'expires_in' => 3600
    ], 'TOKEN');

    echo json_encode($response);

} catch (Exception $e) {
    logMessage("Token creation failed", ['error' => $e->getMessage()], 'TOKEN');
    http_response_code(401);
    echo json_encode([
        'error' => 'invalid_client',
        'message' => $e->getMessage()
    ]);
}
?>