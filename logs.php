<?php
/**
 * Simple log viewer for debugging LTI integration
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTI 1.3 Debug Logs</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            line-height: 1.6;
        }
        .log-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .log-content {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
        }
        .clear-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-left: 1rem;
        }
        .refresh-btn {
            background: #007cba;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .empty { color: #666; font-style: italic; }
        .error { color: #ff6b6b; }
        .success { color: #51cf66; }
        h1 { text-align: center; }
        .actions { text-align: center; margin: 1rem 0; }
    </style>
    <script>
        function clearLog(logFile) {
            if (confirm('Are you sure you want to clear the ' + logFile + ' log?')) {
                fetch('logs.php?clear=' + logFile, {method: 'POST'})
                    .then(() => location.reload());
            }
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</head>
<body>
    <h1>🔍 LTI 1.3 Debug Logs</h1>

    <div class="actions">
        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
        <small style="margin-left: 1rem; color: #666;">Auto-refreshes every 30 seconds</small>
    </div>

    <?php
    // Handle log clearing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['clear'])) {
        $logFile = $_GET['clear'];
        $allowedLogs = ['launch', 'auth'];

        if (in_array($logFile, $allowedLogs)) {
            $fileName = __DIR__ . '/' . $logFile . '.log';
            if (file_exists($fileName)) {
                file_put_contents($fileName, '');
                echo "<div class='success'>✓ {$logFile}.log cleared</div>";
            }
        }
        exit;
    }

    function displayLog($logName, $fileName) {
        echo "<div class='log-section'>";
        echo "<h2>{$logName} Log";
        echo "<button class='clear-btn' onclick='clearLog(\"" . strtolower(str_replace(' ', '', $logName)) . "\")'>Clear Log</button>";
        echo "</h2>";

        if (file_exists($fileName)) {
            $content = file_get_contents($fileName);
            if (empty(trim($content))) {
                echo "<div class='log-content empty'>No log entries yet.</div>";
            } else {
                // Highlight errors and success messages
                $content = htmlspecialchars($content);
                $content = preg_replace('/(\[.*?\] .*?ERROR.*?$)/m', '<span class="error">$1</span>', $content);
                $content = preg_replace('/(\[.*?\] .*?success.*?$)/im', '<span class="success">$1</span>', $content);

                echo "<div class='log-content'>" . $content . "</div>";
            }
        } else {
            echo "<div class='log-content empty'>Log file not found. It will be created when the first log entry is made.</div>";
        }

        echo "</div>";
    }

    // Display launch log
    displayLog('Launch', __DIR__ . '/launch.log');

    // Display auth log
    displayLog('Auth', __DIR__ . '/auth.log');
    ?>

    <div class="log-section">
        <h2>🔧 Troubleshooting Steps</h2>
        <ol>
            <li><strong>Check Launch Log:</strong> Verify the launch initiation parameters</li>
            <li><strong>Check Auth Log:</strong> See if Saltire is calling your auth endpoint</li>
            <li><strong>Verify Saltire Config:</strong> Ensure all URLs match in Saltire platform settings</li>
            <li><strong>Test JWKS:</strong> <a href="jwks.php" target="_blank">Test your JWKS endpoint</a></li>
            <li><strong>Network Access:</strong> Ensure your server is reachable from the internet</li>
        </ol>

        <h3>Expected Flow:</h3>
        <ol>
            <li>User clicks launch → Launch log shows redirect to Saltire</li>
            <li>Saltire calls back → Auth log shows incoming authentication request</li>
            <li>JWT created → Auth log shows successful JWT generation</li>
            <li>User redirected to Saltire tool interface</li>
        </ol>
    </div>

    <div class="log-section">
        <h2>📋 Current Configuration</h2>
        <ul>
            <li><strong>Platform Issuer:</strong> <code>https://412a-133-3-201-44.ngrok-free.app</code></li>
            <li><strong>Auth Endpoint:</strong> <code>https://412a-133-3-201-44.ngrok-free.app/auth.php</code></li>
            <li><strong>JWKS Endpoint:</strong> <code>https://412a-133-3-201-44.ngrok-free.app/jwks.php</code></li>
            <li><strong>Deployment ID:</strong> <code>cLWwj9cbmkSrCNsckEFBmA</code></li>
            <li><strong>Key ID:</strong> <code>f7mdvdmmni</code></li>
        </ul>

        <p><strong>⚠️ Important:</strong> These values must match exactly in your Saltire platform configuration!</p>
    </div>
</body>
</html>