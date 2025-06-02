<?php
/**
 * LTI 1.3 Return Endpoint
 * Handles the return from Saltire after tool interaction
 */
session_start();

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
            <p>Your application successfully launched Saltire and the user has returned.</p>
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
            <p>This endpoint is ready to receive return data from Saltire.</p>
            <p>No return data has been received yet.</p>
        </div>
    <?php endif; ?>

    <p><a href="index.php">← Back to Launch Page</a></p>
</body>
</html>
