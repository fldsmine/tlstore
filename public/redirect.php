<?php
$ref = $_GET['ref'] ?? '';
$path = $_GET['path'] ?? 'register';

// Detect device
$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isAndroid = strpos($userAgent, 'android') !== false;
$isIOS = strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false;

// App scheme URL
$appUrl = "fakelife://{$path}" . ($ref ? "?ref={$ref}" : '');

// Store URLs (customize these)
$playStoreUrl = 'https://play.google.com/store/apps/details?id=pynith.views.fakelives';
$appStoreUrl = 'https://apps.apple.com/app/fakelife/id1234567890';

// Fallback URL (web version)
$webUrl = "http://" . $_SERVER['HTTP_HOST'] . "/{$path}" . ($ref ? "?ref={$ref}" : '');
$appStoreUrl = $webUrl;
$playStoreUrl = "http://" . $_SERVER['HTTP_HOST'] ."/download/android";

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to Fake Life...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #6C5CE7, #00CEC9);
            color: white;
            padding: 20px;
        }
        .card {
            background: white;
            color: #333;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .icon {
            font-size: 60px;
            margin-bottom: 20px;
        }
        h1 {
            margin: 0 0 10px;
            color: #333;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #6C5CE7;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #5849C2;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: transparent;
            color: #6C5CE7;
            border: 2px solid #6C5CE7;
        }
        .loading {
            color: #999;
            font-size: 14px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚀</div>
        <h1>Opening Fake Life...</h1>
        <p>If the app doesn't open automatically, download it below.</p>
        
        <?php if ($isAndroid): ?>
            <a href="<?php echo htmlspecialchars($appUrl); ?>" class="btn">Open App</a>
            <br>
            <a href="<?php echo $playStoreUrl; ?>" class="btn btn-secondary">Download from Play Store</a>
        <?php elseif ($isIOS): ?>
            <a href="<?php echo htmlspecialchars($appUrl); ?>" class="btn">Open App</a>
            <br>
            <a href="<?php echo $appStoreUrl; ?>" class="btn btn-secondary">Download from App Store</a>
        <?php else: ?>
            <a href="<?php echo htmlspecialchars($webUrl); ?>" class="btn">Open in Browser</a>
            <br>
            <a href="<?php echo $playStoreUrl; ?>" class="btn btn-secondary">Get Android App</a>
            <a href="<?php echo $appStoreUrl; ?>" class="btn btn-secondary">Get iOS App</a>
        <?php endif; ?>
        
        <?php if ($ref): ?>
            <div class="loading">
                Referral code: <strong><?php echo htmlspecialchars($ref); ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Try to open the app
        <?php if ($isAndroid || $isIOS): ?>
            setTimeout(function() {
                window.location.href = "<?php echo $appUrl; ?>";
            }, 500);
            
            // Fallback to store if app doesn't open
            setTimeout(function() {
                <?php if ($isAndroid): ?>
                    //window.location.href = "<?php echo $playStoreUrl; ?>";
                <?php else: ?>
                    //window.location.href = "<?php echo $appStoreUrl; ?>";
                <?php endif; ?>
            }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
