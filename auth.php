<?php
/**
 * LTI 1.3 Authentication Endpoint
 * This receives the authentication request from Saltire and responds with a JWT
 */
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';

/**
 * LTI 1.3 Platform Authentication Endpoint
 * This endpoint receives authentication requests from Saltire tool
 * and responds with a JWT ID token containing the LTI launch claims
 */

/**
 * Generate RSA key pair for demo purposes
 */
function generateDemoKeyPair() {
    $keyFile = __DIR__ . '/demo_private_key.pem';

    if (!file_exists($keyFile)) {
        logMessage("Generating new RSA key pair for demo", null, 'AUTH');

        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $privateKey);
        file_put_contents($keyFile, $privateKey);

        logMessage("RSA key pair generated and saved", ['key_file' => $keyFile], 'AUTH');
    }

    return file_get_contents($keyFile);
}

/**
 * Create JWT with proper RS256 signing
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function createJWT($header, $payload) {
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    $dataToSign = $headerEncoded . '.' . $payloadEncoded;

    // Get private key
    $privateKey = generateDemoKeyPair();
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

logMessage("=== Authentication endpoint called ===", array_merge($_REQUEST, [
    'session_id' => session_id(),
    'session_user_exists' => isset($_SESSION['user']),
    'session_contents' => array_keys($_SESSION)
]), 'AUTH');

// Handle both GET and POST requests (Saltire can use either)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = $_REQUEST;  // This handles both GET and POST parameters

    // Validate required parameters
    $requiredParams = ['client_id', 'redirect_uri', 'login_hint', 'state', 'nonce'];
    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            logMessage("Missing required parameter: $param", $params, 'AUTH');
            http_response_code(400);
            echo "Bad Request: Missing required parameter $param";
            exit;
        }
    }

    // Validate client_id
    if ($params['client_id'] !== SALTIRE_CLIENT_ID) {
        logMessage("Invalid client_id", ['received' => $params['client_id'], 'expected' => SALTIRE_CLIENT_ID], 'AUTH');
        http_response_code(400);
        echo "Bad Request: Invalid client_id";
        exit;
    }

    // Try to get user from session first
    $user = $_SESSION['user'] ?? null;

    // If user not in session, recreate from login_hint (dummy user for demo)
    if (!$user) {
        logMessage("User not found in session, creating dummy user from login_hint", [
            'login_hint' => $params['login_hint'],
            'session_id' => session_id()
        ], 'AUTH');

        // Create a dummy user based on login_hint for demonstration
        // In production, you'd look up the real user from your database
        $user = [
            'user_id' => $params['login_hint'],
            'name' => 'Demo User',
            'given_name' => 'Demo',
            'family_name' => 'User',
            'email' => 'demo.user@example.edu',
            'role' => 'learner',
            'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner']
        ];

        // Store in session for potential future use
        $_SESSION['user'] = $user;
    }

    logMessage("Creating LTI launch JWT token", [
        'user' => $user,
        'client_id' => $params['client_id'],
        'redirect_uri' => $params['redirect_uri'],
        'saltire_state' => $params['state'],
        'saltire_nonce' => $params['nonce']
    ], 'AUTH');

    try {
        // Create JWT header - RS256 as required by LTI 1.3
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => 'demo-key-1'
        ];

        // Create JWT payload with LTI 1.3 claims
        $now = time();
        $payload = [
            // Standard JWT claims
            'iss' => PLATFORM_ISSUER,
            'aud' => $params['client_id'],
            'sub' => $user['user_id'],
            'exp' => $now + 3600, // 1 hour
            'iat' => $now,
            'nonce' => $params['nonce'], // Use the nonce from Saltire's request

            // LTI specific claims
            'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
            'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
            'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => DEFAULT_DEPLOYMENT_ID,
            'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => $params['redirect_uri'],

            // User information
            'name' => $user['name'],
            'given_name' => $user['given_name'],
            'family_name' => $user['family_name'],
            'email' => $user['email'],
            'https://purl.imsglobal.org/spec/lti/claim/roles' => $user['roles'],

            // Resource link
            'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
                'id' => 'resource-' . bin2hex(random_bytes(8)),
                'title' => 'Sample Learning Resource',
                'description' => 'A sample learning resource launched from our PHP LTI Platform'
            ],

            // Context (course)
            'https://purl.imsglobal.org/spec/lti/claim/context' => [
                'id' => 'course-' . bin2hex(random_bytes(8)),
                'label' => 'CS101',
                'title' => 'Introduction to Computer Science',
                'type' => ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering']
            ],

            // Platform instance
            'https://purl.imsglobal.org/spec/lti/claim/tool_platform' => [
                'guid' => PLATFORM_DOMAIN,
                'name' => 'PHP LTI Platform',
                'version' => '1.0',
                'product_family_code' => 'php-lti-platform'
            ],

            // Launch presentation
            'https://purl.imsglobal.org/spec/lti/claim/launch_presentation' => [
                'document_target' => 'iframe',
                'height' => 800,
                'width' => 1200,
                'return_url' => PLATFORM_RETURN_URL
            ],

            // LTI message hint (if provided)
            'https://purl.imsglobal.org/spec/lti/claim/lti1p1' => [
                'user_id' => $user['user_id']
            ]
        ];

        // Add LTI message hint if available
        if (isset($_SESSION['lti_message_hint'])) {
            $payload['https://purl.imsglobal.org/spec/lti/claim/lti_message_hint'] = $_SESSION['lti_message_hint'];
        } elseif (isset($params['lti_message_hint'])) {
            $payload['https://purl.imsglobal.org/spec/lti/claim/lti_message_hint'] = $params['lti_message_hint'];
        }

        // Create the JWT token
        $idToken = createJWT($header, $payload);

        logMessage("JWT token created successfully", [
            'token_length' => strlen($idToken),
            'algorithm' => $header['alg'],
            'payload_summary' => [
                'iss' => $payload['iss'],
                'aud' => $payload['aud'],
                'sub' => $payload['sub'],
                'user_name' => $payload['name']
            ]
        ], 'AUTH');

        // Respond with form post to Saltire
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>LTI 1.3 Launch - Redirecting to Saltire Tool</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
                    text-align: center;
                    max-width: 500px;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>🚀 Launching Learning Tool</h2>
                <div class="spinner"></div>
                <p>Redirecting to Saltire Learning Tool...</p>
                <p><small>If you are not redirected automatically, please click the button below.</small></p>

                <form id="launchForm" method="POST" action="<?php echo htmlspecialchars($params['redirect_uri']); ?>">
                    <input type="hidden" name="id_token" value="<?php echo htmlspecialchars($idToken); ?>">
                    <input type="hidden" name="state" value="<?php echo htmlspecialchars($params['state']); ?>">
                    <button type="submit" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; margin-top: 20px;">
                        Continue to Tool
                    </button>
                </form>
            </div>

            <script>
                // Auto-submit the form after a short delay
                setTimeout(function() {
                    document.getElementById('launchForm').submit();
                }, 2000);
            </script>
        </body>
        </html>
        <?php

    } catch (Exception $e) {
        logMessage("JWT creation failed", ['error' => $e->getMessage()], 'AUTH');
        http_response_code(500);
        echo "JWT creation failed: " . htmlspecialchars($e->getMessage());
    }

} else {
    logMessage("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']], 'AUTH');
    http_response_code(405);
    echo "Method not allowed";
}