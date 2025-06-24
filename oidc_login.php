<?php
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';

/**
 * LTI 1.3 OIDC Authentication Endpoint
 * This handles the authentication request from Bookroll
 * This is NOT a login initiation endpoint, but the authentication endpoint itself
 */

logMessage("=== OIDC Authentication Endpoint Called ===", $_REQUEST, 'OIDC_AUTH');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;

    // These are the parameters that Bookroll actually sends
    $requiredParams = ['client_id', 'login_hint', 'nonce', 'redirect_uri', 'response_type', 'scope'];
    $missingParams = [];

    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            $missingParams[] = $param;
        }
    }

    if (!empty($missingParams)) {
        logMessage("Missing required parameters", [
            'missing' => $missingParams,
            'received' => array_keys($params)
        ], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode([
            'error' => 'invalid_request',
            'error_description' => 'Missing required parameters: ' . implode(', ', $missingParams)
        ]);
        exit;
    }

    // Validate client ID
    if ($params['client_id'] !== BOOKROLL_CLIENT_ID) {
        logMessage("Invalid client_id in OIDC auth", [
            'received' => $params['client_id'],
            'expected' => BOOKROLL_CLIENT_ID
        ], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode(['error' => 'invalid_client', 'error_description' => 'Invalid client_id']);
        exit;
    }

    // Validate response_type
    if ($params['response_type'] !== 'id_token') {
        logMessage("Invalid response_type", ['received' => $params['response_type']], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode(['error' => 'unsupported_response_type', 'error_description' => 'Only id_token is supported']);
        exit;
    }

    // Generate or retrieve user (simulating platform user lookup based on login_hint)
    $user = [
        'user_id' => $params['login_hint'],
        'name' => 'Generated User',
        'given_name' => 'Generated',
        'family_name' => 'User',
        'email' => $params['login_hint'] . '@example.edu',
        'role' => 'learner',
        'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner']
    ];

    // Store user and request details in session
    $_SESSION['user'] = $user;
    $_SESSION['target_tool'] = 'bookroll';
    $_SESSION['login_hint'] = $params['login_hint'];
    $_SESSION['redirect_uri'] = $params['redirect_uri'];
    $_SESSION['client_id'] = $params['client_id'];
    $_SESSION['nonce'] = $params['nonce'];  // Use the nonce from Bookroll
    $_SESSION['state'] = $params['state'] ?? null;

    // Store LTI message hint if provided
    if (isset($params['lti_message_hint'])) {
        $_SESSION['lti_message_hint'] = $params['lti_message_hint'];
    }

    logMessage("OIDC authentication request processed", [
        'user' => $user,
        'client_id' => $params['client_id'],
        'login_hint' => $params['login_hint'],
        'redirect_uri' => $params['redirect_uri'],
        'nonce' => $params['nonce'],
        'state' => $params['state'] ?? 'not_provided'
    ], 'OIDC_AUTH');

    // Build the authentication request parameters for our auth.php endpoint
    $authParams = [
        'response_type' => 'id_token',
        'scope' => 'openid',
        'response_mode' => 'form_post',
        'client_id' => $params['client_id'],
        'redirect_uri' => $params['redirect_uri'],
        'login_hint' => $params['login_hint'],
        'nonce' => $params['nonce'],  // Critical: Use Bookroll's nonce
        'tool_type' => 'bookroll'
    ];

    // Add state if provided
    if (isset($params['state'])) {
        $authParams['state'] = $params['state'];
    }

    // Add LTI message hint if provided
    if (isset($params['lti_message_hint'])) {
        $authParams['lti_message_hint'] = $params['lti_message_hint'];
    }

    // Redirect to our authentication endpoint which will create the JWT
    $authUrl = PLATFORM_AUTH_URL . '?' . http_build_query($authParams);

    logMessage("Redirecting to platform auth endpoint", ['url' => $authUrl], 'OIDC_AUTH');

    header("Location: $authUrl");
    exit;

} else {
    logMessage("Invalid request method for OIDC auth", ['method' => $_SERVER['REQUEST_METHOD']], 'OIDC_AUTH');
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method not allowed']);
    exit;
}
?>