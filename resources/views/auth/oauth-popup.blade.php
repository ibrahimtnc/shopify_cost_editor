<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opening Shopify Login...</title>
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
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #6366f1;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <p>Opening Shopify login in a new window...</p>
        <p style="font-size: 0.875rem; color: #6b7280; margin-top: 0.5rem;">
            Please complete the login in the new window, then return here.
        </p>
    </div>
    
    <script>
        // OAuth URL'ini yeni sekmede aç
        const oauthUrl = '{{ $installationUrl }}';
        const callbackUrl = '{{ config("shopify.redirect_uri") }}';
        
        // Yeni pencere aç
        const newWindow = window.open(oauthUrl, 'shopify_oauth', 'width=800,height=600,scrollbars=yes,resizable=yes');
        
        if (!newWindow) {
            // Popup blocker varsa, yeni sekmede aç
            alert('Please allow popups for this site, or click the button below to open login in a new tab.');
            document.body.innerHTML = `
                <div class="container">
                    <p>Please click the button below to open Shopify login:</p>
                    <button onclick="window.open('${oauthUrl}', '_blank')" 
                            style="padding: 0.75rem 1.5rem; background: #6366f1; color: white; border: none; border-radius: 0.5rem; cursor: pointer; font-size: 1rem;">
                        Open Shopify Login
                    </button>
                </div>
            `;
        } else {
            // Yeni pencere açıldı, callback'i dinle
            let checkCallback;
            
            // Popup'tan mesaj dinle (callback sayfası postMessage gönderecek)
            window.addEventListener('message', function(event) {
                // Güvenlik: Sadece kendi domain'inden gelen mesajları kabul et
                if (event.data && event.data.type === 'shopify-oauth-success') {
                    clearInterval(checkCallback);
                    const embeddedUrl = event.data.embeddedUrl;
                    if (embeddedUrl) {
                        // Parent frame'de embedded app URL'ine yönlendir
                        if (window.top && window.top !== window.self) {
                            window.top.location.href = embeddedUrl;
                        } else {
                            window.location.href = embeddedUrl;
                        }
                        // Popup'ı kapat
                        if (newWindow && !newWindow.closed) {
                            newWindow.close();
                        }
                    }
                }
            });
            
            // Pencere kapandıysa kontrol et
            checkCallback = setInterval(function() {
                if (newWindow.closed) {
                    clearInterval(checkCallback);
                    // Pencere kapandı, embedded app URL'ine yönlendir
                    setTimeout(function() {
                        try {
                            const shop = '{{ session("oauth_shop") }}';
                            if (shop) {
                                const shopDomain = shop.includes('.myshopify.com') ? shop : shop + '.myshopify.com';
                                const apiKey = '{{ config("shopify.api_key") }}';
                                const embeddedUrl = `https://${shopDomain}/admin/apps/${apiKey}`;
                                
                                if (window.top && window.top !== window.self) {
                                    window.top.location.href = embeddedUrl;
                                } else {
                                    window.location.href = embeddedUrl;
                                }
                            }
                        } catch (e) {
                            console.error('Error redirecting:', e);
                        }
                    }, 500);
                }
            }, 500);
            
            // Sayfa unload olduğunda temizle
            window.addEventListener('beforeunload', function() {
                if (checkCallback) {
                    clearInterval(checkCallback);
                }
            });
        }
    </script>
</body>
</html>

