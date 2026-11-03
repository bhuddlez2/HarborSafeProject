{{--
Keeps an unfinished assessment through the idle logout (App\Support\AssessmentDraft).

The form is saved, encrypted, to this tab's sessionStorage whenever it
changes: typing, an answer, a step, a server round trip, the tab being hidden,
and just before the idle logout reloads the page (it waits for this save; see
idle-logout.blade.php). Opening New assessment again in the same tab, signed
in as the same person, offers Resume or Discard. Submit, Discard and Sign out
wipe it (Sign out in assessment-draft-guard.blade.php, which also drops a
form that belongs to someone else or is past its time).

Encrypted with AES-GCM under this person's key, which only this page carries;
the user id and save time are bound in as additional data, so a saved form
can't be passed off as another person's or made to last longer. Without
Web Crypto (a page not served over https) nothing is saved at all, rather than
saving it unencrypted.
--}}
@php
    $draftUser = \Filament\Facades\Filament::auth()->user();
@endphp

<x-filament::modal
    id="hs-assessment-draft"
    icon="heroicon-o-document-text"
    heading="Resume unfinished assessment?"
    width="md"
    :close-button="false"
    :close-by-clicking-away="false"
    :close-by-escaping="false"
>
    <p class="text-sm text-gray-600">
        You started an assessment at
        <strong class="text-gray-950" data-hs-draft-time></strong>
        that wasn't submitted. It was kept in this browser tab only.
    </p>

    <x-slot name="footer">
        <div class="flex flex-wrap gap-3">
            <x-filament::button type="button" autofocus data-hs-draft-resume>
                Resume
            </x-filament::button>

            <x-filament::button type="button" color="gray" data-hs-draft-discard>
                Discard it
            </x-filament::button>
        </div>
    </x-slot>
</x-filament::modal>

@script
<script>
    const storageKey = @js(\App\Support\AssessmentDraft::STORAGE_KEY);
    const userId = @js((string) $draftUser->getKey());
    const lifetimeMs = @js(\App\Support\AssessmentDraft::LIFETIME_MINUTES * 60 * 1000);
    const fields = @js(\App\Support\AssessmentDraft::fields());
    const rawKey = @js(\App\Support\AssessmentDraft::keyFor($draftUser));
    const modalId = 'hs-assessment-draft';
    const subtle = window.isSecureContext ? window.crypto?.subtle : null;

    const fromBase64 = (text) => Uint8Array.from(atob(text), (c) => c.charCodeAt(0));
    const toBase64 = (bytes) => btoa(String.fromCharCode(...bytes));
    const fromHex = (text) => Uint8Array.from(text.match(/../g), (pair) => parseInt(pair, 16));
    const boundTo = (savedAt) => new TextEncoder().encode(`${userId}|${savedAt}`);

    let keyPromise = null;
    const key = () => keyPromise ??= subtle.importKey('raw', fromHex(rawKey), 'AES-GCM', false, ['encrypt', 'decrypt']);

    const read = () => {
        try { return JSON.parse(sessionStorage.getItem(storageKey)); } catch (e) { return null; }
    };

    const remove = () => {
        try { sessionStorage.removeItem(storageKey); } catch (e) {}
    };

    const modal = (open) => window.dispatchEvent(new CustomEvent(open ? 'open-modal' : 'close-modal', { detail: { id: modalId } }));

    const wizard = () => {
        const el = $wire.$el.querySelector('.hs-lap-wizard');
        const data = el && window.Alpine ? window.Alpine.$data(el) : null;

        return data?.$refs?.stepsData ? data : null;
    };

    // While Resume / Discard is on screen, nothing is saved, so the form
    // waiting to be resumed is never overwritten by the empty one.
    let choosing = read() !== null;
    let found = null;

    // Set on Sign out (assessment-draft-guard): nothing more is saved here.
    let stopped = false;

    // The wizard's state as it stands in the browser (typed values included,
    // even before they reach the server), or null when nothing is filled in.
    const snapshot = () => {
        const steps = wizard();
        const data = $wire.get('data') ?? {};
        const kept = {};

        for (const field of fields) {
            const value = data[field];

            if (value !== undefined && value !== null && value !== '') {
                kept[field] = value;
            }
        }

        if (! steps || Object.keys(kept).length === 0) {
            return null;
        }

        return {
            data: kept,
            step: steps.getStepIndex(steps.step),
            questionIndex: $wire.get('questionIndex'),
            reviewReached: $wire.get('reviewReached'),
        };
    };

    const save = async () => {
        if (! subtle || choosing || stopped) {
            return;
        }

        if ($wire.get('submitted')) {
            remove();

            return;
        }

        const state = snapshot();

        if (! state) {
            return;
        }

        const savedAt = Date.now();
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const sealed = await subtle.encrypt(
            { name: 'AES-GCM', iv, additionalData: boundTo(savedAt) },
            await key(),
            new TextEncoder().encode(JSON.stringify(state)),
        );

        // Submitted, signed out, or Resume offered, while this was being encrypted.
        if (choosing || stopped || $wire.get('submitted')) {
            return;
        }

        try {
            sessionStorage.setItem(storageKey, JSON.stringify({
                userId,
                savedAt,
                iv: toBase64(iv),
                data: toBase64(new Uint8Array(sealed)),
            }));
        } catch (e) {}
    };

    // One save at a time, in order.
    let queue = Promise.resolve();
    const saveNow = () => (queue = queue.then(save, save));

    let timer = null;
    const saveSoon = () => {
        clearTimeout(timer);
        timer = setTimeout(saveNow, 400);
    };

    const load = async () => {
        const saved = read();

        if (! saved) {
            return null;
        }

        if (! subtle || saved.userId !== userId || ! (Date.now() - saved.savedAt < lifetimeMs)) {
            remove();

            return null;
        }

        try {
            const plain = await subtle.decrypt(
                { name: 'AES-GCM', iv: fromBase64(saved.iv), additionalData: boundTo(saved.savedAt) },
                await key(),
                fromBase64(saved.data),
            );

            return { savedAt: saved.savedAt, state: JSON.parse(new TextDecoder().decode(plain)) };
        } catch (e) {
            remove();

            return null;
        }
    };

    const resume = async () => {
        modal(false);

        const step = found ? await $wire.restoreDraft(found.state) : null;
        const steps = wizard();

        choosing = false;
        found = null;

        if (step === null || ! steps) {
            remove();

            return;
        }

        steps.goToNextStep(steps.getSteps()[step]);
        saveNow();
    };

    const discard = () => {
        modal(false);
        remove();
        choosing = false;
        found = null;
    };

    $wire.$el.addEventListener('input', saveSoon);
    $wire.$el.addEventListener('change', saveSoon);
    $wire.$el.addEventListener('click', (event) => {
        if (event.target.closest('[data-hs-draft-resume]')) {
            resume();
        } else if (event.target.closest('[data-hs-draft-discard]')) {
            discard();
        } else {
            saveSoon();
        }
    });

    // After every round trip: Continue, an answer, a live field.
    $wire.$interceptMessage(({ onFinish }) => onFinish(saveSoon));

    // Sent by create() once the assessment is saved. A server dispatch reaches
    // the window, not this component's element, so it is heard there.
    window.Livewire.on('hs-assessment-draft-clear', remove);

    window.addEventListener('hs-lap-draft-stop', () => {
        stopped = true;
        clearTimeout(timer);
        remove();
    });

    // A phone may kill a hidden tab; save before it can.
    document.addEventListener('visibilitychange', () => document.hidden && saveNow());

    // The idle logout waits for this before reloading to the sign-in page.
    window.addEventListener('hs-before-idle-reload', (event) => event.detail?.waitUntil(saveNow()));

    load().then((draft) => {
        if (! draft || $wire.get('submitted')) {
            choosing = false;

            return;
        }

        found = draft;

        const time = $wire.$el.querySelector('[data-hs-draft-time]');

        if (time) {
            time.textContent = new Date(draft.savedAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }

        // On a slow page the pop-up may not be listening yet when it is first
        // asked to open, so ask again until it shows (asking twice is harmless).
        const promptShown = () => [...$wire.$el.querySelectorAll('[data-hs-draft-resume]')]
            .some((button) => button.getClientRects().length > 0);

        const openPrompt = (tries) => {
            modal(true);
            setTimeout(() => found && ! promptShown() && tries > 0 && openPrompt(tries - 1), 250);
        };

        openPrompt(20);
    });
</script>
@endscript
