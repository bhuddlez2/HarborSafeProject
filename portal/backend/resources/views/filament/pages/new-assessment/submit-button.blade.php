<button
    type="button"
    wire:click="create"
    wire:loading.attr="disabled"
    wire:target="create"
    {{-- Enabled only once the safe number is confirmed; create() checks it too. --}}
    x-bind:disabled="! $wire.data?.SafePhoneConfirmed"
    class="{{ $class }} disabled:opacity-50 disabled:cursor-not-allowed"
>
    <span wire:loading.remove wire:target="create">Submit assessment</span>
    <span wire:loading wire:target="create">Submitting...</span>
</button>
