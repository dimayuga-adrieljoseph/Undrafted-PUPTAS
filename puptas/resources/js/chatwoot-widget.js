/**
 * Chatwoot Widget Integration with Identity Validation
 * 
 * This script loads the Chatwoot widget with identity validation enabled.
 * It fetches the user's identity hash from the backend to ensure secure conversations.
 */

export async function initializeChatwootWidget() {
    try {
        // Fetch widget configuration with identity validation from backend
        const response = await fetch('/api/chatwoot/widget-config', {
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${getAuthToken()}` // Your auth token method
            }
        });

        if (!response.ok) {
            console.error('Failed to fetch Chatwoot config');
            return;
        }

        const config = await response.json();

        // Initialize Chatwoot widget
        window.chatwootSettings = {
            hideMessageBubble: false,
            position: 'right',
            locale: 'en',
            type: 'expanded_bubble',
        };

        // Load Chatwoot script
        (function(d, t) {
            var BASE_URL = config.baseUrl;
            var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
            g.src = BASE_URL + "/packs/js/sdk.js";
            g.defer = true;
            g.async = true;
            s.parentNode.insertBefore(g, s);
            
            g.onload = function() {
                window.chatwootSDK.run({
                    websiteToken: config.websiteToken,
                    baseUrl: BASE_URL
                });

                // Set user identity with validation
                if (config.user) {
                    window.$chatwoot.setUser(config.user.identifier, {
                        email: config.user.email,
                        name: config.user.name,
                        identifier_hash: config.user.identifier_hash // HMAC hash for validation
                    });
                }
            };
        })(document, "script");

    } catch (error) {
        console.error('Error initializing Chatwoot widget:', error);
    }
}

// Helper function to get auth token (adjust based on your auth implementation)
function getAuthToken() {
    // Example: return token from localStorage, cookie, or meta tag
    return document.querySelector('meta[name="api-token"]')?.content || '';
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChatwootWidget);
} else {
    initializeChatwootWidget();
}
