{{-- Browser-tab and phone home-screen icons, on every panel page including sign-in.
     favicon.ico is set by ->favicon() in StaffPanelProvider; the manifest lists the
     larger icons (public/manifest.webmanifest). --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#5d2c87">
<meta name="apple-mobile-web-app-title" content="Harbor Safe">
