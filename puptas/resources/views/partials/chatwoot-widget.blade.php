<style>
    /* Push Chatwoot bubble above the Sienna accessibility widget */
    .woot-widget-bubble,
    .woot--bubble-holder {
        bottom: 90px !important;
        z-index: 99999 !important;
    }

    @media (max-width: 480px) {
        .woot-widget-bubble,
        .woot--bubble-holder {
            bottom: 100px !important;
        }
    }
</style>

<script>
    (function(d,t) {
        var BASE_URL = "https://chatwoot-production-49b7.up.railway.app";
        var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
        g.src = BASE_URL + "/packs/js/sdk.js";
        g.async = true;
        s.parentNode.insertBefore(g, s);
        g.onload = function() {
            window.chatwootSDK.run({
                websiteToken: 'AsD5tPpqEd2z5As2jh2nwwGD',
                baseUrl: BASE_URL
            });

            @auth
            window.addEventListener('chatwoot:ready', function() {
                window.$chatwoot.setUser('{{ auth()->id() }}', {
                    name: '{{ addslashes(auth()->user()->name) }}',
                    email: '{{ auth()->user()->email }}',
                });
            });
            @endauth
        };
    })(document, "script");
</script>
