<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    {{-- CAP_CUSTOM_WASM_URL doit être défini avant le chargement du module --}}
    <script>window.CAP_CUSTOM_WASM_URL = {!! json_encode(asset('vendor/cap/cap_wasm_bg.wasm')) !!};</script>
    <script type="module" src="{{ asset('vendor/cap/cap-widget.js') }}"></script>
</head>
<body>
    <script type="module">
    const cap = new Cap({ apiEndpoint: {!! json_encode(config('cap.endpoint')) !!} });

    window.addEventListener('message', async (e) => {
        if (e.origin !== window.location.origin) return;
        if (!e.data || e.data.type !== 'cap:start') return;
        try {
            const { token } = await cap.solve();
            window.parent.postMessage({ type: 'cap:token', token }, window.location.origin);
        } catch {
            window.parent.postMessage({ type: 'cap:error' }, window.location.origin);
        }
    });
    </script>
</body>
</html>
