<?php
// Configure session cookies for cross-origin LTI support
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '', // Keep empty for current domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None' // Allow cross-site cookies for LTI
]);

session_start();

// Load central configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

/**
 * LTI 1.3 Platform OIDC Authentication Endpoint
 *
 * This handles Step 2 of the LTI 1.3 launch flow:
 * After we POST to the tool's login initiation endpoint,
 * the tool redirects the user here with authentication parameters.
 *
 * Our job is to validate the request and redirect to auth.php
 * which will generate the JWT ID token and send it to the tool's launch endpoint.
 */

logMessage("=== Platform OIDC Authentication Endpoint Called ===", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'get_params' => $_GET,
    'post_params' => $_POST,
    'session_data' => [
        'user_exists' => isset($_SESSION['user']),
        'target_tool' => $_SESSION['target_tool'] ?? 'not_set',
        'nonce' => $_SESSION['nonce'] ?? 'not_set'
    ]
], 'OIDC_AUTH');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;

    // Validate required parameters that the tool should send
    $requiredParams = ['client_id', 'login_hint', 'nonce', 'redirect_uri', 'response_type', 'scope'];
    $missingParams = [];

    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            $missingParams[] = $param;
        }
    }

    if (!empty($missingParams)) {
        logMessage("Missing required parameters from tool", [
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

    // Validate client ID and determine tool type
    $toolType = null;
    if ($params['client_id'] === BOOKROLL_CLIENT_ID) {
        $toolType = 'bookroll';
    } elseif ($params['client_id'] === ANALYSIS_CLIENT_ID) {
        $toolType = 'analysis';
    } else {
        logMessage("Invalid client_id from tool", [
            'received' => $params['client_id'],
            'expected_bookroll' => BOOKROLL_CLIENT_ID,
            'expected_analysis' => ANALYSIS_CLIENT_ID
        ], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode(['error' => 'invalid_client', 'error_description' => 'Invalid client_id']);
        exit;
    }

    // Validate response_type
    if ($params['response_type'] !== 'id_token') {
        logMessage("Invalid response_type from tool", ['received' => $params['response_type']], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode(['error' => 'unsupported_response_type', 'error_description' => 'Only id_token is supported']);
        exit;
    }

    // Get user from session (should have been set when we initiated the launch)
    if (!isset($_SESSION['user'])) {
        logMessage("No user found in session - this shouldn't happen", [
            'session_id' => session_id(),
            'session_contents' => array_keys($_SESSION)
        ], 'OIDC_AUTH');
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => 'No user session found']);
        exit;
    }

    $user = $_SESSION['user'];

    // Verify the login_hint matches our user (security check)
    if ($params['login_hint'] !== $user['user_id']) {
        logMessage("Login hint mismatch", [
            'tool_login_hint' => $params['login_hint'],
            'session_user_id' => $user['user_id']
        ], 'OIDC_AUTH');
        // In production, you might want to fail here, but for demo we'll allow it
    }

    logMessage("Tool authentication request validated", [
        'tool_type' => $toolType,
        'client_id' => $params['client_id'],
        'login_hint' => $params['login_hint'],
        'redirect_uri' => $params['redirect_uri'],
        'nonce' => $params['nonce'],
        'state' => $params['state'] ?? 'not_provided',
        'user_from_session' => $user['user_id']
    ], 'OIDC_AUTH');

    // Now redirect to our auth.php endpoint to generate the JWT ID token
    $authParams = [
        'response_type' => 'id_token',
        'scope' => 'openid',
        'response_mode' => 'form_post',
        'client_id' => $params['client_id'],
        'redirect_uri' => $params['redirect_uri'],  // Tool's launch endpoint
        'login_hint' => $params['login_hint'],
        'nonce' => $params['nonce'],  // Use the nonce from the tool
        'tool_type' => $toolType
    ];

    // Add state if provided
    if (isset($params['state'])) {
        $authParams['state'] = $params['state'];
    }

    // Add LTI message hint if provided
    if (isset($params['lti_message_hint'])) {
        $authParams['lti_message_hint'] = $params['lti_message_hint'];
    }

    // Build the URL to our auth endpoint
    $authUrl = PLATFORM_AUTH_URL . '?' . http_build_query($authParams);

    logMessage("Redirecting to platform auth endpoint to generate JWT", [
        'url' => $authUrl,
        'tool_type' => $toolType,
        'params_for_auth' => $authParams
    ], 'OIDC_AUTH');

    header("Location: $authUrl");
    exit;

} else {
    logMessage("Invalid request method for OIDC auth", ['method' => $_SERVER['REQUEST_METHOD']], 'OIDC_AUTH');
    http_response_code(405);
    echo json_encode(['error' => 'invalid_request', 'error_description' => 'Method not allowed']);
    exit;
}
?>