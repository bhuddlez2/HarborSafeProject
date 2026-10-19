{{--
Sends an idle screen to the sign-in page once the session has expired.

The server ends a session after SESSION_LIFETIME minutes without a request,
but only notices on the next one, so until somebody clicks, an unattended
screen keeps showing whatever was open - victim details included. This
reloads the page shortly after the server's deadline has passed; the expired
session then redirects to /login, and signing in returns to the same page.

The clock restarts on every server request (page loads and Livewire calls),
not on mouse movement or typing, because only a request extends the session.
The time of the last request is shared between tabs through localStorage, so
a tab left open does not reload while the officer is working in another one.
Rendered for signed-in users only (StaffPanelProvider, SCRIPTS_AFTER).
--}}
<script>
    (() => {
        const deadlineMs = {{ (int) $minutes }} * 60 * 1000 + 5000;
        const key = 'hs-last-server-request';
        let last = Date.now();

        const touch = () => {
            last = Date.now();
            try { localStorage.setItem(key, String(last)); } catch (e) {}
        };

        const lastRequest = () => {
            let shared = 0;
            try { shared = Number(localStorage.getItem(key)) || 0; } catch (e) {}
            return Math.max(last, shared);
        };

        const check = () => {
            if (Date.now() - lastRequest() >= deadlineMs) {
                window.location.reload();
            }
        };

        const watchLivewire = () => window.Livewire.interceptRequest(({ onSuccess }) => onSuccess(touch));

        touch();

        if (window.Livewire) {
            watchLivewire();
        } else {
            document.addEventListener('livewire:init', watchLivewire, { once: true });
        }

        setInterval(check, 15000);
        document.addEventListener('visibilitychange', () => document.hidden || check());
    })();
</script>
