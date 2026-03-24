@if (config('app.sienna_widget.enabled'))
    <script
        src="https://cdn.jsdelivr.net/npm/sienna-accessibility@latest/dist/sienna-accessibility.umd.js"
        defer
        data-asw-position="{{ config('app.sienna_widget.position', 'bottom-left') }}"
        data-asw-offset="{{ config('app.sienna_widget.offset', '20,20') }}"
    ></script>
@endif