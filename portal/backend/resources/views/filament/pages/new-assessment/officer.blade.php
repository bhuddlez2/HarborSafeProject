{{-- Info phase: officer identity, read-only, from the signed-in account. --}}
<div>
    <h1 class="text-3xl font-semibold text-gray-900 mb-2">
        Submitting officer
    </h1>
    <p class="text-sm text-gray-500 mb-10">
        This information is pulled from your account and cannot be edited here.
    </p>

    <div class="flex gap-4 mb-6">
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700 mb-1">Name</p>
            <div class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
                @if (filled($officer['name']))
                    {{ $officer['name'] }}
                @else
                    <span class="italic">Auto-filled from account</span>
                @endif
            </div>
        </div>
        <div class="flex-1">
            <p class="text-sm font-medium text-gray-700 mb-1">Badge number</p>
            <div class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
                @if (filled($officer['badge']))
                    {{ $officer['badge'] }}
                @else
                    <span class="italic">Auto-filled from account</span>
                @endif
            </div>
        </div>
    </div>

    <div>
        <p class="text-sm font-medium text-gray-700 mb-1">Agency</p>
        <div class="w-full border-2 border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-400 select-none">
            @if (filled($officer['agency']))
                {{ $officer['agency'] }}
            @else
                <span class="italic">Auto-filled from account</span>
            @endif
        </div>
    </div>
</div>
