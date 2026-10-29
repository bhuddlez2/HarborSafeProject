{{--
On every panel page, for signed-in users: the rules for an unfinished
assessment kept in this tab (App\Support\AssessmentDraft) that must hold
wherever the person is, not only on New assessment.

- Sign out wipes it, from the user menu or the idle warning's Sign out now
  (both are forms posting to the logout route). Being signed out by the idle
  timeout does not: keeping the form through that is the point.
- A form saved by someone else, or older than its lifetime, is dropped as
  soon as any page opens, and checked again every minute.

Reads only the saved form's user id and save time, never its contents; it
holds no key and could not decrypt them.
--}}
<script>
    (() => {
        const storageKey = @js(\App\Support\AssessmentDraft::STORAGE_KEY);
        const userId = @js((string) \Filament\Facades\Filament::auth()->id());
        const lifetimeMs = @js(\App\Support\AssessmentDraft::LIFETIME_MINUTES * 60 * 1000);
        const logoutPath = new URL(@js(filament()->getLogoutUrl()), window.location.href).pathname;

        const remove = () => {
            try { sessionStorage.removeItem(storageKey); } catch (e) {}
        };

        const check = () => {
            let saved = null;

            try { saved = JSON.parse(sessionStorage.getItem(storageKey)); } catch (e) { remove(); }

            if (saved && (saved.userId !== userId || ! (Date.now() - saved.savedAt < lifetimeMs))) {
                remove();
            }
        };

        document.addEventListener('submit', (event) => {
            const action = event.target?.getAttribute?.('action');

            if (action && new URL(action, window.location.href).pathname === logoutPath) {
                // Tell an open wizard to stop saving first: leaving the page
                // would otherwise save the form straight back.
                window.dispatchEvent(new CustomEvent('hs-lap-draft-stop'));
                remove();
            }
        }, true);

        check();
        setInterval(check, 60 * 1000);
    })();
</script>
