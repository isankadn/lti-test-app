<?php
/**
 * LTI 1.3 Platform Token Endpoint
 * This handles OAuth 2.0 token requests from Moodle
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

logMessage("Token endpoint called", $_POST, 'TOKEN');

// Get POST parameters
$client_id = $_POST['client_id'] ?? '';
$client_assertion = $_POST['client_assertion'] ?? '';
$grant_type = $_POST['grant_type'] ?? '';
$scope = $_POST['scope'] ?? '';

// Validate required parameters
if (!$client_id || !$client_assertion || !$grant_type) {
    logMessage("Missing required parameters", $_POST, 'TOKEN');
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request', 'message' => 'Missing required parameters']);
    exit;
}

// Only support client_credentials grant type
if ($grant_type !== 'client_credentials') {
    logMessage("Unsupported grant type", ['grant_type' => $grant_type], 'TOKEN');
    http_response_code(400);
    echo json_encode(['error' => 'unsupported_grant_type']);
    exit;
}

// Validate client_id
if ($client_id !== MOODLE_CLIENT_ID) {
    logMessage("Invalid client_id", ['received' => $client_id, 'expected' => MOODLE_CLIENT_ID], 'TOKEN');
    http_response_code(401);
    echo json_encode(['error' => 'invalid_client', 'message' => 'Invalid client_id']);
    exit;
}

try {
    // In production, you should:
    // 1. Validate the client_assertion JWT
    // 2. Verify it's signed by Moodle's private key
    // 3. Check the claims (iss, sub, aud, exp, etc.)

    // For demo purposes, we'll create a simple access token
    logMessage("Creating access token for Moodle", [
        'client_id' => $client_id,
        'scope' => $scope
    ], 'TOKEN');

    $issuedAt = time();
    $expiresAt = $issuedAt + 3600; // 1 hour

    // Create a simple access token (in production, use a proper JWT)
    $accessToken = base64_encode(json_encode([
        'iss' => PLATFORM_ISSUER,
        'sub' => $client_id,
        'aud' => PLATFORM_TOKEN_URL,
        'iat' => $issuedAt,
        'exp' => $expiresAt,
        'scope' => $scope
    ]));

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