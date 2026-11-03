{{-- One Analytics card (App\Support\Analytics\Chart). Bars are plain CSS:
     the width or height is the value's share of the card's largest. Each
     chart is one image to a screen reader, named by its summary, which holds
     every number; hovering a bar shows its own. --}}
<div @class([
    'flex min-w-0 flex-col gap-4 rounded-xl border border-gray-200 bg-white px-5 py-[18px]',
    'xl:col-span-2' => $card['wide'],
])>
    <div class="flex flex-col gap-0.5">
        <h3 class="font-montserrat text-base font-bold text-gray-900">{{ $card['title'] }}</h3>
        @if (filled($card['note']))
            <p class="text-[13px] text-gray-500">{{ $card['note'] }}</p>
        @endif
    </div>

    @if ($card['type'] === 'horizontal')
        <div role="img" aria-label="{{ $card['summary'] }}" class="flex flex-col gap-1.5">
            @foreach ($card['bars'] as $bar)
                {{-- On a phone: label and number on one line, the bar full
                     width under them. From sm up: label | bar | number. --}}
                <div title="{{ $bar['tip'] }}" aria-hidden="true" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 sm:grid-cols-[minmax(6rem,14rem)_minmax(0,1fr)_auto]">
                    <span class="order-1 truncate text-sm text-gray-700">{{ $bar['label'] }}</span>
                    <span class="order-3 col-span-2 block h-[18px] sm:order-2 sm:col-span-1">
                        <span class="block h-full rounded-r bg-[#5C0F8B] hover:bg-[#3F0A60]" style="width: {{ $bar['pct'] }}%"></span>
                    </span>
                    <span class="order-2 min-w-14 text-right text-sm font-bold text-gray-900 tabular-nums sm:order-3">{{ $bar['display'] }}</span>
                </div>
            @endforeach
        </div>
    @elseif ($card['type'] === 'vertical')
        <div role="img" aria-label="{{ $card['summary'] }}" class="flex flex-col gap-1.5">
            <div aria-hidden="true" class="flex h-48 items-end gap-1.5 border-b border-gray-300">
                @foreach ($card['bars'] as $bar)
                    <div title="{{ $bar['tip'] }}" class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                        @if ($card['showValues'])
                            <span class="text-xs text-gray-600 tabular-nums">{{ $bar['display'] }}</span>
                        @endif
                        {{-- 82% at most, leaving room for the number above. --}}
                        <span class="block w-full max-w-13 rounded-t bg-[#5C0F8B] hover:bg-[#3F0A60]" style="height: {{ round($bar['pct'] * 0.82) }}%"></span>
                    </div>
                @endforeach
            </div>
            <div aria-hidden="true" class="flex gap-1.5">
                {{-- Every other label on a phone once there are too many to fit. --}}
                @foreach ($card['bars'] as $bar)
                    <span @class([
                        'min-w-0 flex-1 truncate text-center text-xs text-gray-500',
                        'invisible sm:visible' => count($card['bars']) > 8 && $loop->odd === false,
                    ])>{{ $bar['label'] }}</span>
                @endforeach
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm tabular-nums">
                <thead>
                    <tr class="border-b border-gray-300 text-gray-700">
                        <th scope="col" class="py-2 pr-3 text-left font-bold">{{ $card['firstColumn'] }}</th>
                        @foreach ($card['columns'] as $column)
                            <th scope="col" class="px-2 py-2 text-right font-bold">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($card['rows'] as $row)
                        <tr class="border-b border-gray-100 last:border-0">
                            <th scope="row" class="py-2.5 pr-3 text-left font-semibold text-gray-900">{{ $row['label'] }}</th>
                            @foreach ($row['values'] as $value)
                                <td @class(['px-2 py-2.5 text-right', 'font-bold' => $loop->last])>{{ number_format($value) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if (filled($card['footnote']))
        <p class="text-[13px] text-gray-500">{{ $card['footnote'] }}</p>
    @endif
</div>
