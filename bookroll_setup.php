<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookroll LTI 1.3 Configuration</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .field-name {
            font-weight: bold;
            color: #495057;
            width: 30%;
        }
        .field-value {
            font-family: 'Courier New', monospace;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            word-break: break-all;
        }
        .notes {
            background-color: #e7f3ff;
            padding: 20px;
            border-left: 4px solid #007bff;
            margin: 20px 0;
        }
        .warning {
            background-color: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        .key-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        textarea {
            width: 100%;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        .copy-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px 0;
        }
        .copy-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Bookroll LTI 1.3 Configuration</h1>
            <p>Configuration values for integrating your LTI Platform with Bookroll</p>
        </div>

        <?php
        require_once __DIR__ . '/bookroll_config.php';

        $config = getBookrollConfig();
        ?>

        <h2>📋 Configuration Values</h2>
        <p>Copy and paste these values into your Bookroll LTI 1.3 connection form:</p>

        <table>
            <thead>
                <tr>
                    <th>Field Name</th>
                    <th>Value</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($config as $field => $value): ?>
                <tr>
                    <td class="field-name"><?= ucwords(str_replace('_', ' ', $field)) ?></td>
                    <td class="field-value" id="<?= $field ?>"><?= htmlspecialchars($value) ?></td>
                    <td>
                        <button class="copy-btn" onclick="copyToClipboard('<?= $field ?>')">Copy</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="key-section">
            <h3>🔐 Public Key</h3>
            <p>Extract your public key using the button below, then copy it to the "public key" field in Bookroll:</p>
            <a href="get_public_key.php" target="_blank" class="copy-btn">Get Public Key</a>
        </div>

        <div class="key-section">
            <h3>🔑 Private Key</h3>
            <p>Your private key is stored in: <code>demo_private_key.pem</code></p>
            <p>If you need to copy the private key content for Bookroll, you can view it here:</p>

            <?php
            $keyFile = __DIR__ . '/demo_private_key.pem';
            if (file_exists($keyFile)) {
                $privateKey = file_get_contents($keyFile);
                echo '<textarea rows="10" readonly id="private-key">' . htmlspecialchars($privateKey) . '</textarea>';
                echo '<br><button class="copy-btn" onclick="copyToClipboard(\'private-key\')">Copy Private Key</button>';
            } else {
                echo '<p style="color: red;">Private key file not found. Please generate it first by visiting your main platform.</p>';
            }
            ?>
        </div>

        <div class="notes">
            <h3>📝 Setup Instructions</h3>
            <ol>
                <li>Copy each configuration value from the table above</li>
                <li>Paste them into the corresponding fields in your Bookroll LTI 1.3 connection form</li>
                <li>For the <strong>Default Directory</strong> field, select whatever directory structure Bookroll provides</li>
                <li>Get your public key by clicking the "Get Public Key" button above</li>
                <li>Copy the private key from the textarea above if needed</li>
                <li>Save the configuration in Bookroll</li>
            </ol>
        </div>

        <div class="warning">
            <h3>⚠️ Important Notes</h3>
            <ul>
                <li>Make sure your platform is accessible at: <strong><?= PLATFORM_DOMAIN ?></strong></li>
                <li>The client ID must match exactly between your platform and Bookroll</li>
                <li>Keep your private key secure and never share it publicly</li>
                <li>Test the integration after configuration to ensure it works properly</li>
            </ul>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="color: #007bff; text-decoration: none;">← Back to Platform</a>
        </div>
    </div>

    <script>
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent || element.value;

        navigator.clipboard.writeText(text).then(function() {
            // Show success feedback
            const originalText = element.textContent || element.value;
            if (element.tagName === 'TEXTAREA') {
                // For textarea, we'll show an alert
                alert('Copied to clipboard!');
            } else {
                // For table cells, temporarily change the text
                element.style.backgroundColor = '#d4edda';
                setTimeout(() => {
                    element.style.backgroundColor = '#f8f9fa';
                }, 1000);
            }
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
            alert('Failed to copy. Please select and copy manually.');
        });
    }
    </script>
</body>
</html>