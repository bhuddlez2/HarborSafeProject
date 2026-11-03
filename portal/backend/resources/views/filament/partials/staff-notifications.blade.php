{{--
The bell's "read after a look" rule (App\Livewire\StaffNotifications):
once the notification panel has been open for about five seconds, every alert
in it is marked read, so the red count clears while the alerts stay listed
for 12 hours. Closing the panel sooner leaves them unread.

Filament opens and closes the panel with window events carrying its id
("database-notifications"), and the panel carries .fi-modal-open while it is
open. Rendered only for users who have the bell (StaffPanelProvider).
--}}
<script>
    (() => {
        const MODAL_ID = 'database-notifications';
        const READ_AFTER_MS = 5000;
        let timer = null;

        window.addEventListener('open-modal', (event) => {
            if (event.detail?.id !== MODAL_ID) return;

            clearTimeout(timer);
            timer = setTimeout(() => {
                const panel = document.getElementById(MODAL_ID);
                if (! panel?.classList.contains('fi-modal-open')) return;

                // The open panel is moved to the end of <body>, outside its
                // Livewire component; the bell button stays inside it.
                const component = document.querySelector('.fi-sidebar-database-notifications-btn')?.closest('[wire\\:id]');
                if (component) window.Livewire.find(component.getAttribute('wire:id'))?.call('markAllNotificationsAsRead');
            }, READ_AFTER_MS);
        });

        window.addEventListener('close-modal', (event) => {
            if (event.detail?.id === MODAL_ID) clearTimeout(timer);
        });
    })();
</script>
