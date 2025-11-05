<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Successful</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .success-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h2 style="margin: 0 0 0.5rem; color: #1f2937;">Authentication Successful!</h2>
        <p style="color: #6b7280; margin: 0;">Redirecting to Shopify app...</p>
    </div>
    
    <script>
        const embeddedUrl = '{{ $embeddedUrl }}';
        
        // Popup içinde miyiz kontrol et
        if (window.opener && window.opener !== window) {
            // Popup içindeyiz - parent frame'e mesaj gönder
            try {
                window.opener.postMessage({
                    type: 'shopify-oauth-success',
                    embeddedUrl: embeddedUrl
                }, '*');
                
                // Popup'ı kapat
                setTimeout(function() {
                    window.close();
                }, 100);
            } catch (e) {
                // Cross-origin hatası - parent frame'e yönlendir
                window.opener.location.href = embeddedUrl;
                window.close();
            }
        } else {
            // Normal sayfa - embedded app URL'ine yönlendir
            window.location.href = embeddedUrl;
        }
    </script>
</body>
</html>

