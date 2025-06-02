<?php
/**
 * LTI 1.3 Launch - Platform initiates login to Tool (Saltire)
 * This acts as the Platform sending a login initiation request to Saltire
 */
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';

/* ====== LOGGING FUNCTION ====== */
function logMessage($message, $data = null, $type = 'LAUNCH') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $type: $message";
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data);
    }
    $logEntry .= "\n";
    file_put_contents('launch.log', $logEntry, FILE_APPEND | LOCK_EX);
}

logMessage("=== LTI 1.3 Launch Endpoint Called ===", null, 'LAUNCH');
logMessage("Request method: " . $_SERVER['REQUEST_METHOD'], null, 'LAUNCH');
logMessage("Request parameters", $_REQUEST, 'LAUNCH');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = $_REQUEST;
    logMessage("POST Launch request received", array_keys($params), 'LAUNCH');

    // Check if this is the JWT token launch (step 2)
    if (isset($params['id_token'])) {
        logMessage("=== Step 2: Launch Request with JWT Token ===", null, 'LAUNCH');

        // Basic security checks
        if (!isset($_SESSION['state']) || !isset($params['state']) || $_SESSION['state'] !== $params['state']) {
            logMessage("State validation failed", [
                'session_state' => $_SESSION['state'] ?? 'missing',
                'request_state' => $params['state'] ?? 'missing'
            ], 'LAUNCH');
            http_response_code(400);
            echo "Invalid state parameter";
            exit;
        }

        logMessage("State validation passed", null, 'LAUNCH');

        // In a real implementation, you would:
        // 1. Decode and validate the JWT token
        // 2. Verify the nonce
        // 3. Extract user and context information
        // 4. Create the user session
        // 5. Display the tool interface

        $jwtToken = $params['id_token'];
        logMessage("JWT Token received", ['token_length' => strlen($jwtToken)], 'LAUNCH');

        // For demo purposes, let's show a simple tool interface
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>LTI 1.3 Tool - Success!</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
                .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; }
                .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin-top: 20px; }
                pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
            </style>
        </head>
        <body>
            <h1>🎉 LTI 1.3 Tool Launch Successful!</h1>

            <div class="success">
                <h3>✅ Authentication Complete</h3>
                <p>Your LTI 1.3 tool has successfully received and processed the launch request from Saltire platform.</p>
            </div>

            <div class="info">
                <h3>Session Information</h3>
                <p><strong>Login Hint:</strong> <?php echo htmlspecialchars($_SESSION['login_hint'] ?? 'Not available'); ?></p>
                <p><strong>Issuer:</strong> <?php echo htmlspecialchars($_SESSION['iss'] ?? 'Not available'); ?></p>
                <p><strong>Client ID:</strong> <?php echo htmlspecialchars($_SESSION['client_id'] ?? 'Not available'); ?></p>
                <p><strong>Deployment ID:</strong> <?php echo htmlspecialchars($_SESSION['lti_deployment_id'] ?? 'Not available'); ?></p>
            </div>

            <div class="info">
                <h3>Configuration Info</h3>
                <p><strong>Tool Domain:</strong> <?php echo TOOL_DOMAIN; ?></p>
                <p><strong>Launch URL:</strong> <?php echo TOOL_LAUNCH_URL; ?></p>
                <p><strong>Platform:</strong> <?php echo SALTIRE_PLATFORM; ?></p>
            </div>

            <div class="info">
                <h3>Next Steps</h3>
                <p>In a real LTI tool, this is where you would:</p>
                <ul>
                    <li>Decode and validate the JWT token</li>
                    <li>Extract user information (name, email, roles)</li>
                    <li>Extract context information (course, assignment)</li>
                    <li>Create or update user session</li>
                    <li>Display your tool's main interface</li>
                    <li>Handle grade passback if applicable</li>
                </ul>
            </div>

            <div class="info">
                <h3>JWT Token Info</h3>
                <p><strong>Token Length:</strong> <?php echo strlen($jwtToken); ?> characters</p>
                <p><strong>Token Preview:</strong> <?php echo htmlspecialchars(substr($jwtToken, 0, 50)); ?>...</p>
                <p><em>Full token validation would happen here in production.</em></p>
            </div>
        </body>
        </html>
        <?php

        logMessage("Tool interface displayed successfully", null, 'LAUNCH');

    } else {
        logMessage("POST request received but missing id_token", [
            'available_params' => array_keys($params),
            'session_data' => [
                'state' => $_SESSION['state'] ?? 'missing',
                'nonce' => $_SESSION['nonce'] ?? 'missing',
                'login_hint' => $_SESSION['login_hint'] ?? 'missing'
            ]
        ], 'LAUNCH');

        // Show debug info instead of just an error
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>LTI 1.3 Debug - Missing Token</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
                .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; }
                .debug { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px; }
                pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
            </style>
        </head>
        <body>
            <h1>🔍 LTI 1.3 Debug - Missing id_token</h1>

            <div class="error">
                <h3>❌ Missing id_token in POST request</h3>
                <p>The launch endpoint received a POST request but no id_token was provided.</p>
            </div>

            <div class="debug">
                <h3>Debug Information</h3>
                <p><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></p>
                <p><strong>Current Domain:</strong> <?php echo TOOL_DOMAIN; ?></p>
                <p><strong>Parameters Received:</strong></p>
                <pre><?php echo htmlspecialchars(print_r($params, true)); ?></pre>

                <p><strong>Session Data:</strong></p>
                <pre><?php echo htmlspecialchars(print_r($_SESSION, true)); ?></pre>
            </div>

            <div class="debug">
                <h3>Expected Flow</h3>
                <ol>
                    <li>Saltire should call: <code><?php echo TOOL_LOGIN_URL; ?></code></li>
                    <li>Then redirect to Saltire auth</li>
                    <li>Then Saltire posts back to: <code><?php echo TOOL_LAUNCH_URL; ?></code> with id_token</li>
                </ol>
            </div>
        </body>
        </html>
        <?php
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    logMessage("GET request received - showing configuration", null, 'LAUNCH');

    // Handle GET requests - show configuration info
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>LTI 1.3 Tool Configuration</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .config { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .endpoint { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px; }
            code { background: #e9ecef; padding: 2px 4px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <h1>LTI 1.3 Tool Configuration</h1>

        <div class="config">
            <h2>🔧 Tool Endpoints (Dynamic)</h2>
            <p>Configure these URLs in Saltire platform:</p>

            <div class="endpoint">
                <strong>Login Initiation URL:</strong><br>
                <code><?php echo TOOL_LOGIN_URL; ?></code>
            </div>

            <div class="endpoint">
                <strong>Launch URL (Redirect URI):</strong><br>
                <code><?php echo TOOL_LAUNCH_URL; ?></code>
            </div>

            <div class="endpoint">
                <strong>JWKS URL:</strong><br>
                <code><?php echo TOOL_JWKS_URL; ?></code>
            </div>
        </div>

        <div class="config">
            <h2>🏛️ Platform Configuration</h2>
            <p><strong>Platform Issuer:</strong> <?php echo SALTIRE_PLATFORM; ?></p>
            <p><strong>Client ID:</strong> <?php echo SALTIRE_CLIENT_ID; ?></p>
            <p><strong>Current Domain:</strong> <?php echo TOOL_DOMAIN; ?></p>
        </div>

        <div class="config">
            <h2>📋 Instructions</h2>
            <ol>
                <li>Go to <a href="<?php echo SALTIRE_PLATFORM; ?>/platform" target="_blank">Saltire Platform</a></li>
                <li>Update your Tool configuration with the endpoints above</li>
                <li>Set LTI version to 1.3</li>
                <li>Save and test the configuration</li>
            </ol>
        </div>

        <div class="config">
            <h2>🐛 Troubleshooting</h2>
            <p>If you're seeing "Missing id_token" errors, check:</p>
            <ul>
                <li>Saltire Tool Details are pointing to YOUR URLs (not Saltire URLs)</li>
                <li>Check the launch.log file for detailed request information</li>
                <li>Ensure the login flow starts at <?php echo TOOL_LOGIN_URL; ?>, not <?php echo TOOL_LAUNCH_URL; ?></li>
            </ul>
        </div>
    </body>
    </html>
    <?php

} else {
    logMessage("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']], 'LAUNCH');
    http_response_code(405);
    echo "Method not allowed";
}

/**
 * LTI 1.3 Platform Launch Success Page
 * This page shows confirmation that the launch to Saltire was successful
 */

logMessage("=== Launch confirmation page accessed ===", $_REQUEST, 'LAUNCH_SUCCESS');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTI 1.3 Launch Success</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .success-icon {
            font-size: 4em;
            color: #28a745;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #666;
            font-size: 1.2em;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .details-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            border: 2px solid #e9ecef;
            text-align: left;
        }

        .details-section h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: bold;
            color: #495057;
        }

        .detail-value {
            color: #6c757d;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .actions {
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 5px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .info-box h4 {
            color: #0c5460;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #0c5460;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">🎉</div>

        <div class="header">
            <h1>Launch Successful!</h1>
            <p>Your LTI 1.3 Platform has successfully initiated a launch to the Saltire Learning Tool. The user should now be interacting with Application B.</p>
        </div>

        <div class="details-section">
            <h3>📋 Launch Details</h3>

            <div class="detail-item">
                <span class="detail-label">Platform Domain:</span>
                <span class="detail-value"><?php echo PLATFORM_DOMAIN; ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Target Tool:</span>
                <span class="detail-value"><?php echo SALTIRE_TOOL_DOMAIN; ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Client ID:</span>
                <span class="detail-value"><?php echo SALTIRE_CLIENT_ID; ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Deployment ID:</span>
                <span class="detail-value"><?php echo DEFAULT_DEPLOYMENT_ID; ?></span>
            </div>

            <?php if (isset($_SESSION['user'])): ?>
            <div class="detail-item">
                <span class="detail-label">User:</span>
                <span class="detail-value"><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Unknown'); ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? 'Not provided'); ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Role:</span>
                <span class="detail-value"><?php echo htmlspecialchars(ucfirst($_SESSION['user']['role'] ?? 'Unknown')); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="info-box">
            <h4>🔗 What Happened?</h4>
            <p><strong>1.</strong> Generated random user identity and security tokens</p>
            <p><strong>2.</strong> Initiated OIDC login flow to Saltire Tool</p>
            <p><strong>3.</strong> Created and signed JWT token with LTI 1.3 claims</p>
            <p><strong>4.</strong> Successfully launched user to external learning tool</p>
        </div>

        <div class="actions">
            <a href="index.php" class="btn btn-primary">🔄 Launch Another Session</a>
            <a href="logs.php" class="btn btn-secondary">📋 View Launch Logs</a>
        </div>
    </div>
</body>
</html>
?>
