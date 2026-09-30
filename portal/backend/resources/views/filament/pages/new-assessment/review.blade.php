{{-- Complete phase: review & submit. --}}
<div>
    <h1 class="text-3xl font-semibold text-gray-900 mb-6">
        Review &amp; submit
    </h1>

    <div class="divide-y divide-gray-100">
        <div class="py-4">
            <p class="text-sm font-medium text-gray-500 mb-1">Victim</p>
            <p class="text-gray-900">{{ $data['VictimFirstName'] ?? '' }} {{ $data['VictimLastName'] ?? '' }}</p>
        </div>
        <div class="py-4">
            <p class="text-sm font-medium text-gray-500 mb-1">Offender</p>
            <p class="text-gray-900">{{ $data['OffenderFirstName'] ?? '' }} {{ $data['OffenderLastName'] ?? '' }}</p>
        </div>
        <div class="py-4">
            <p class="text-sm font-medium text-gray-500 mb-1">Questions answered</p>
            <p class="text-gray-900">{{ $total }} of {{ $total }}</p>
        </div>
    </div>

    @if ($submitError)
        <p class="text-red-600 text-sm mt-6">{{ $submitError }}</p>
    @endif
</div>
