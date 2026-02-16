<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance - ReviewFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --secondary: #a29bfe;
            --accent: #fd79a8;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-gradient);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
            overflow: hidden;
        }
        .maintenance-container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            animation: float 3s ease-in-out infinite;
        }
        .maintenance-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .maintenance-text {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.8;
        }
        .progress-bar-container {
            background: rgba(255,255,255,0.2);
            border-radius: 50px;
            padding: 4px;
            margin: 2rem auto;
            max-width: 400px;
        }
        .progress-bar-inner {
            height: 8px;
            border-radius: 50px;
            background: linear-gradient(90deg, #fd79a8, #fdcb6e, #00cec9);
            animation: progress 2s ease-in-out infinite;
            width: 60%;
        }
        .contact-info {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        .contact-info a {
            color: #fdcb6e;
            text-decoration: none;
        }
        .contact-info a:hover {
            text-decoration: underline;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        @keyframes progress {
            0% { width: 20%; }
            50% { width: 80%; }
            100% { width: 20%; }
        }
        .gear {
            position: fixed;
            opacity: 0.05;
            font-size: 15rem;
            animation: spin 10s linear infinite;
        }
        .gear-1 { top: -5rem; right: -5rem; }
        .gear-2 { bottom: -5rem; left: -5rem; animation-direction: reverse; }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <i class="bi bi-gear-fill gear gear-1"></i>
    <i class="bi bi-gear-fill gear gear-2"></i>

    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="bi bi-tools"></i>
        </div>
        <h1 class="maintenance-title">We'll Be Right Back!</h1>
        <p class="maintenance-text">
            We're currently performing scheduled maintenance to improve your experience.
            <br>Our team is working hard to bring you new features and improvements.
        </p>
        <div class="progress-bar-container">
            <div class="progress-bar-inner"></div>
        </div>
        <p class="maintenance-text" style="font-size: 0.95rem;">
            <i class="bi bi-clock"></i> Estimated downtime: A few minutes
        </p>
        <div class="contact-info">
            <p><i class="bi bi-envelope"></i> Need help? Contact us at
                <a href="mailto:support@reviewflow.com">support@reviewflow.com</a>
            </p>
        </div>
    </div>
</body>
</html>
