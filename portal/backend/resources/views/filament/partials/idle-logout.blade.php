{{--
Warns before an idle session ends, then sends the idle screen to sign-in.

The server ends a session after SESSION_LIFETIME minutes without a request,
but only notices on the next one, so until somebody clicks, an unattended
screen keeps showing whatever was open - victim details included. This
reloads the page shortly after the server's deadline has passed; the expired
session then redirects to /login, and signing in returns to the same page.

Two minutes before the deadline it shows a warning with a countdown and two
choices: Stay signed in, which sends one request (KeepSessionAliveController)
and so restarts the session's clock, or Sign out now. The warning can't be
closed by clicking outside or pressing Esc, so nobody believes they chose when
they didn't. Nothing extends the session on its own: only that button, a page
load or a Livewire call does, so an unattended screen still signs out.

The clock restarts on every server request (page loads and Livewire calls),
not on mouse movement or typing, because only a request extends the session.
The time of the last request is shared between tabs through localStorage (a
timestamp only, nothing from the page), so working in one tab, or choosing
Stay signed in there, closes the warning in the others.

An unfinished assessment is kept through the logout: the reload waits for it
to save itself to the tab (App\Support\AssessmentDraft), and the warning says
so whenever one is saved.

Rendered for signed-in users only (StaffPanelProvider, SCRIPTS_AFTER).
SessionTimeoutTest pins it.
--}}
@php
    $lifetimeMs = (int) $minutes * 60 * 1000;
    // Two minutes' notice, or half the lifetime if that is shorter.
    $warningMs = min(2 * 60 * 1000, intdiv($lifetimeMs, 2));
@endphp

<x-filament::modal
    id="hs-idle-warning"
    alert
    icon="heroicon-o-clock"
    icon-color="warning"
    heading="You'll be signed out soon"
    width="md"
    :close-button="false"
    :close-by-clicking-away="false"
    :close-by-escaping="false"
>
    <p class="text-sm text-gray-600">
        For safety, you'll be signed out in
        <strong class="text-gray-950" data-hs-idle-countdown>2:00</strong>
        because there has been no activity for a while. Anything you haven't
        saved will be lost.
    </p>

    {{-- Only for those who fill in the wizard (AssessmentDraft); nobody else has one. --}}
    @if (\App\Filament\Pages\NewAssessment::canAccess())
        <p class="hidden mt-3 text-sm text-gray-600" data-hs-idle-draft>
            Your unfinished assessment is kept in this browser tab. Sign back in
            here to resume it.
        </p>
    @endif

    <p class="hidden text-sm text-danger-600" data-hs-idle-error role="alert">
        We couldn't reach the server. Check your connection and try again.
    </p>

    <form method="POST" action="{{ route('filament.staff.auth.logout') }}" id="hs-idle-logout-form" class="hidden">
        @csrf
    </form>

    <x-slot name="footer">
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" autofocus data-hs-idle-stay>
                Stay signed in
            </x-filament::button>

            <x-filament::button type="submit" color="gray" form-id="hs-idle-logout-form">
                Sign out now
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>

<script>
    (() => {
        const lifetimeMs = {{ $lifetimeMs }};
        const warningMs = {{ $warningMs }};
        const deadlineMs = lifetimeMs + 5000;
        const keepAliveUrl = @js(route('staff.session.keep-alive'));
        const csrfToken = @js(csrf_token());
        const key = 'hs-last-server-request';
        const modalId = 'hs-idle-warning';
        let last = Date.now();
        let warning = false;
        let staying = false;

        const touch = () => {
            last = Date.now();
            try { localStorage.setItem(key, String(last)); } catch (e) {}
        };

        const lastRequest = () => {
            let shared = 0;
            try { shared = Number(localStorage.getItem(key)) || 0; } catch (e) {}
            return Math.max(last, shared);
        };

        const el = (selector) => document.querySelector(selector);

        const showError = (shown) => el('[data-hs-idle-error]')?.classList.toggle('hidden', ! shown);

        const setWarning = (shown) => {
            if (shown === warning) {
                return;
            }

            warning = shown;
            showError(false);

            let hasDraft = false;
            try { hasDraft = sessionStorage.getItem(@js(\App\Support\AssessmentDraft::STORAGE_KEY)) !== null; } catch (e) {}
            el('[data-hs-idle-draft]')?.classList.toggle('hidden', ! (shown && hasDraft));
            window.dispatchEvent(new CustomEvent(shown ? 'open-modal' : 'close-modal', { detail: { id: modalId } }));
        };

        const showCountdown = (leftMs) => {
            const seconds = Math.max(0, Math.ceil(leftMs / 1000));
            const text = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
            const countdown = el('[data-hs-idle-countdown]');

            if (countdown && countdown.textContent !== text) {
                countdown.textContent = text;
            }
        };

        {{-- Kept as a Blade comment: this partial is on a secretary's pages too,
             which must never mention assessments (FollowUpNewRequestTest). --}}
        // Before reloading, give the officer wizard a moment to save its
        // unfinished form to the tab (its draft script), at most 3 seconds.
        let reloading = false;

        const reload = () => {
            if (reloading) {
                return;
            }

            reloading = true;

            const pending = [];
            window.dispatchEvent(new CustomEvent('hs-before-idle-reload', {
                detail: { waitUntil: (promise) => pending.push(promise) },
            }));

            Promise.race([
                Promise.allSettled(pending),
                new Promise((resolve) => setTimeout(resolve, 3000)),
            ]).then(() => window.location.reload());
        };

        const check = () => {
            const idleMs = Date.now() - lastRequest();

            if (idleMs >= deadlineMs) {
                reload();

                return;
            }

            const leftMs = lifetimeMs - idleMs;

            if (leftMs <= warningMs) {
                showCountdown(leftMs);
                setWarning(true);
            } else {
                setWarning(false);
            }
        };

        // One request restarts the server's clock. Anything but a 204 means
        // the session has gone (the sign-in redirect) or the page is stale,
        // and a reload shows whichever it is.
        const stay = async () => {
            if (staying) {
                return;
            }

            staying = true;
            showError(false);

            try {
                const response = await fetch(keepAliveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    redirect: 'manual',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });

                if (response.status === 204) {
                    touch();
                    check();
                } else {
                    reload();
                }
            } catch (e) {
                showError(true);
            } finally {
                staying = false;
            }
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-hs-idle-stay]')) {
                stay();
            }
        });

        const watchLivewire = () => window.Livewire.interceptRequest(({ onSuccess }) => onSuccess(touch));

        touch();

        if (window.Livewire) {
            watchLivewire();
        } else {
            document.addEventListener('livewire:init', watchLivewire, { once: true });
        }

        setInterval(check, 1000);
        document.addEventListener('visibilitychange', () => document.hidden || check());
        // Another tab made a request: close this tab's warning straight away.
        window.addEventListener('storage', (event) => event.key === key && check());
    })();
</script>
