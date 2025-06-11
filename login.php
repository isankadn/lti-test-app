<?php
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';

/**
 * LTI 1.3 Platform Login Handler
 * This endpoint handles OIDC authentication responses from Moodle tool
 * and redirects to our authentication endpoint to issue JWT tokens
 */

logMessage("=== Platform login handler called ===", $_REQUEST, 'LOGIN');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;

    // This endpoint receives the OIDC response from Moodle
    // Moodle will redirect here with authentication parameters

    logMessage("OIDC response from Moodle received", $params, 'LOGIN');

    // Validate required parameters from Saltire
    $requiredParams = ['iss', 'login_hint', 'target_link_uri'];
    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            logMessage("Missing required parameter: $param", $params, 'LOGIN');
            http_response_code(400);
            echo "Bad Request: Missing required parameter $param";
            exit;
        }
    }

    // Validate issuer (should be Moodle)
    if ($params['iss'] !== MOODLE_TOOL_DOMAIN) {
        logMessage("Invalid issuer", ['received' => $params['iss'], 'expected' => MOODLE_TOOL_DOMAIN], 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Invalid issuer";
        exit;
    }

    // Get our stored state and nonce from session
    $state = $_SESSION['state'] ?? null;
    $nonce = $_SESSION['nonce'] ?? null;

    if (!$state || !$nonce) {
        logMessage("Missing state or nonce in session", [
            'state' => $state ? 'present' : 'missing',
            'nonce' => $nonce ? 'present' : 'missing'
        ], 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Invalid session state";
        exit;
    }

    logMessage("Session state validated", ['state' => $state, 'nonce' => $nonce], 'LOGIN');

    // Build authentication request parameters for our auth endpoint
    $authParams = [
        'response_type' => 'id_token',
        'scope' => 'openid',
        'response_mode' => 'form_post',
        'client_id' => MOODLE_CLIENT_ID,
        'redirect_uri' => $params['target_link_uri'] ?? MOODLE_LAUNCH_URL,
        'login_hint' => $params['login_hint'],
        'state' => $state,
        'nonce' => $nonce
    ];

    // Add LTI message hint if provided
    if (isset($params['lti_message_hint'])) {
        $authParams['lti_message_hint'] = $params['lti_message_hint'];
    }

    // Redirect to our authentication endpoint
    $authUrl = PLATFORM_AUTH_URL . '?' . http_build_query($authParams);

    logMessage("Redirecting to platform auth endpoint", ['url' => $authUrl], 'LOGIN');

    header("Location: $authUrl");
    exit;

} else {
    logMessage("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']], 'LOGIN');
    http_response_code(405);
    echo "Method not allowed";
    exit;
}
?>