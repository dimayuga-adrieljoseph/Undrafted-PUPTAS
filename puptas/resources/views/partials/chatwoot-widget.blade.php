@if (config('services.chatwoot.url') && config('services.chatwoot.token'))
<script>
    window.chatwootSettings = {
        hideMessageBubble: false,
        position: 'right',
        locale: '{{ str_replace('_', '-', app()->getLocale()) }}',
        type: 'standard',
        darkMode: 'auto',
    };

    (function (d, t) {
        var BASE_URL = "{{ rtrim(config('services.chatwoot.url'), '/') }}";
        var g = d.createElement(t), s = d.getElementsByTagName(t)[0];
        g.src = BASE_URL + "/packs/js/sdk.js";
        g.defer = true;
        g.async = true;
        s.parentNode.insertBefore(g, s);
        g.onload = function () {
            window.chatwootSDK.run({
                websiteToken: '{{ config('services.chatwoot.token') }}',
                baseUrl: BASE_URL,
            });

            @auth
            window.addEventListener('chatwoot:ready', function () {
                window.$chatwoot.setUser('{{ auth()->id() }}', {
                    name: '{{ addslashes(auth()->user()->name) }}',
                    email: '{{ auth()->user()->email }}',
                });
            });
            @endauth
        };
    })(document, "script");
</script>
@endif
