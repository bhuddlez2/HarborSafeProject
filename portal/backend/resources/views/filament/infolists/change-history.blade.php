{{--
    Change history of a law-enforcement assessment: one card per edit, newest
    first. Data comes from App\Filament\Assessments\ChangeHistory - already
    labelled and formatted, so nothing here interprets raw values. Rendered two
    ways: as the "Change log" button's pop-up ($edits passed in) and as an
    infolist entry on the Change log page ($getState()).
--}}
@php
    $edits ??= isset($getState) ? ($getState() ?? []) : [];
@endphp

<div class="flex flex-col gap-3">
    @forelse ($edits as $edit)
        <article class="rounded-xl border border-gray-200 bg-white p-4">
            <header class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p class="text-sm font-semibold text-gray-900">{{ $edit['editor'] }}</p>
                <p class="text-sm text-gray-500">{{ $edit['when'] }}</p>
            </header>

            <p class="mt-1 text-sm text-gray-700">
                <span class="font-semibold">Reason:</span> {{ $edit['reason'] }}
            </p>

            <dl class="mt-3 divide-y divide-gray-100 border-t border-gray-100">
                @foreach ($edit['changes'] as $change)
                    <div class="grid gap-1 py-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] sm:gap-4">
                        <dt class="text-sm text-gray-600">{{ $change['label'] }}</dt>
                        <dd class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-gray-500 line-through decoration-gray-300">{{ $change['from'] }}</span>
                            <span aria-hidden="true" class="text-gray-400">&rarr;</span>
                            <span class="sr-only">changed to</span>
                            <span class="font-semibold text-gray-900">{{ $change['to'] }}</span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </article>
    @empty
        <p class="text-sm text-gray-500">No edits since submission.</p>
    @endforelse
</div>
