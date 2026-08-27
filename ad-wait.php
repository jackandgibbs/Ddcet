<?php
require_once __DIR__ . '/config.php';
// BUG-027 fix: ensure only authenticated users can access the ad waiting room.
$user = requireAuth();
$pageTitle = 'Unlocking Test...';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Please Wait — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: white; 
            font-family: 'DM Sans', sans-serif; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            overflow: hidden;
        }
        .wait-container {
            background: white;
            border-radius: 20px;
            padding: 60px 80px;
            text-align: center;
            max-width: 500px;
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        p {
            color: #6c757d;
            font-size: 15px;
            margin-bottom: 40px;
        }
        .countdown {
            font-size: 80px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 20px;
            font-variant-numeric: tabular-nums;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .progress-fill {
            height: 100%;
            background: #000000;
            transition: width 1s linear;
        }
        .info {
            font-size: 13px;
            color: #adb5bd;
        }
        @media (max-width: 600px) {
            .wait-container { padding: 40px 24px; border-radius: 16px; }
            h1 { font-size: 22px; }
            .countdown { font-size: 60px; }
        }
    </style>
</head>
<body>
    <div class="wait-container">
        <h1>Please Wait</h1>
        <p>Loading your dashboard...</p>
        <div class="countdown" id="countdown">30</div>
        <div class="progress-bar">
            <div class="progress-fill" id="progress"></div>
        </div>
        <div class="info">You will be redirected automatically</div>
    </div>

    <script>
        // Disable right-click
        document.addEventListener('contextmenu', (e) => e.preventDefault());

        let timeLeft = 30;
        const countdownEl = document.getElementById('countdown');
        const progressEl = document.getElementById('progress');
        
        progressEl.style.width = '100%';

        const timer = setInterval(() => {
            timeLeft--;
            countdownEl.textContent = timeLeft;
            progressEl.style.width = ((timeLeft / 30) * 100) + '%';
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                window.location.href = '<?= BASE_PATH ?>dashboard.php';
            }
        }, 1000);
    </script>
</body>
</html>
