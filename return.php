<?php
/**
 * LTI 1.3 Return Endpoint
 * Handles the return from LTI tools (BookRoll, Analysis) after tool interaction
 */
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

// Load configurations to get tool information
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

// Determine which tool the user returned from
$toolName = 'Unknown Tool';
$toolDomain = '';

if (isset($_SESSION['target_tool'])) {
    switch ($_SESSION['target_tool']) {
        case 'bookroll':
            $toolName = 'BookRoll';
            $toolDomain = BOOKROLL_TOOL_DOMAIN;
            break;
        case 'analysis':
            $toolName = 'Analysis';
            $toolDomain = ANALYSIS_TOOL_DOMAIN;
            break;
    }
} else {
    // Try to determine from client_id in return data
    $clientId = $_GET['client_id'] ?? $_POST['client_id'] ?? '';
    if ($clientId === BOOKROLL_CLIENT_ID) {
        $toolName = 'BookRoll';
        $toolDomain = BOOKROLL_TOOL_DOMAIN;
    } elseif ($clientId === ANALYSIS_CLIENT_ID) {
        $toolName = 'Analysis';
        $toolDomain = ANALYSIS_TOOL_DOMAIN;
    }
}

// Log the return for debugging
if (!empty($_GET) || !empty($_POST)) {
    logMessage("User returned from LTI tool", [
        'tool_name' => $toolName,
        'tool_domain' => $toolDomain,
        'get_data' => $_GET,
        'post_data' => $_POST,
        'session_user' => $_SESSION['user'] ?? null,
        'session_target_tool' => $_SESSION['target_tool'] ?? null
    ], 'TOOL_RETURN');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LTI 1.3 Launch Return</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .success { color: green; }
        .error { color: red; }
        .info { background: #f0f0f0; padding: 1rem; margin: 1rem 0; }
        pre { background: #f8f8f8; padding: 1rem; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>LTI 1.3 Launch Return</h1>

    <?php if (!empty($_GET) || !empty($_POST)): ?>
        <div class="success">
            <h2>✓ Launch Successful!</h2>
            <p>Your application successfully launched <strong><?php echo htmlspecialchars($toolName); ?></strong> and the user has returned.</p>
            <?php if ($toolDomain): ?>
                <p><small>Tool Domain: <code><?php echo htmlspecialchars($toolDomain); ?></code></small></p>
            <?php endif; ?>
        </div>

        <div class="info">
            <h3>Return Data</h3>

            <?php if (!empty($_GET)): ?>
                <h4>GET Parameters:</h4>
                <pre><?php print_r($_GET); ?></pre>
            <?php endif; ?>

            <?php if (!empty($_POST)): ?>
                <h4>POST Parameters:</h4>
                <pre><?php print_r($_POST); ?></pre>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user'])): ?>
        <div class="info">
            <h3>Session Information</h3>
            <p><strong>User:</strong> <?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'Unknown'); ?></p>
            <p><strong>Tool:</strong> <?php echo htmlspecialchars($toolName); ?></p>
            <p><strong>Launch Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        <?php endif; ?>

        <div class="info">
            <h3>Implementation Notes</h3>
            <p>In a production application, you would:</p>
            <ul>
                <li>Process any return data from the tool</li>
                <li>Update user progress or grades if applicable</li>
                <li>Log the interaction for analytics</li>
                <li>Show appropriate success/completion messages</li>
                <li>Redirect user back to the main application</li>
            </ul>
        </div>

    <?php else: ?>
        <div class="info">
            <h2>Return Endpoint Ready</h2>
            <p>This endpoint is ready to receive return data from LTI tools (BookRoll, Analysis).</p>
            <p>No return data has been received yet.</p>
        </div>
    <?php endif; ?>

    <p><a href="index.php">← Back to Launch Page</a></p>
</body>
</html>
