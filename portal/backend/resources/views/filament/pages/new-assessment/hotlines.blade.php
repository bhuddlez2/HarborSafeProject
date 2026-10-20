{{--
    Hotline numbers on the "Assessment submitted" screen (team plan task 12),
    for the officer to share with the victim. Same numbers and layout as the
    public website's crisis box (website/frontend/src/app/page.js) - change
    them there too. Every number is shown as text and is a working call or
    text link on a phone.
--}}
@php
    $others = [
        [
            'name' => 'National Domestic Violence Hotline',
            'note' => 'Call 24/7 · Text START to 88788',
            'phone' => '1-800-799-7233',
            'tel' => '18007997233',
            'sms' => ['label' => 'Text 88788', 'href' => 'sms:88788?&body=START'],
        ],
        [
            'name' => '988 Suicide & Crisis Lifeline',
            'note' => 'Call or text, 24/7',
            'phone' => '988',
            'tel' => '988',
            'sms' => ['label' => 'Text 988', 'href' => 'sms:988'],
        ],
    ];
@endphp

<section aria-labelledby="hs-hotlines-heading" class="flex flex-col gap-3">
    <h2 id="hs-hotlines-heading" class="text-lg font-semibold text-gray-900">
        Numbers to share with the victim
    </h2>

    <a
        href="tel:911"
        class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 hover:underline"
    >
        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 fill-none stroke-red-700 stroke-2" aria-hidden="true">
            <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        In immediate danger, call 911
    </a>

    <div class="rounded-xl bg-[#F5F0FB] p-4">
        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-[#5C0F8B]">24/7 Crisis Line</p>
        <p class="mb-3 text-sm font-semibold text-[#5C0F8B]">Harbor Safe House &amp; Advocacy Center</p>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <a
                href="tel:423-476-3886"
                class="rounded-lg border-2 border-[#5C0F8B] bg-[#5C0F8B] py-2.5 text-center text-sm font-semibold text-white transition hover:bg-[#4C0B74]"
            >
                Call (423) 476-3886
            </a>
            <a
                href="sms:423-715-9614"
                class="rounded-lg border-2 border-[#5C0F8B] bg-white py-2.5 text-center text-sm font-semibold text-[#5C0F8B] transition hover:bg-[#5C0F8B] hover:text-white"
            >
                Text (423) 715-9614
            </a>
        </div>
    </div>

    <div class="divide-y divide-gray-100 rounded-xl border border-gray-200">
        @foreach ($others as $line)
            <div class="flex flex-col items-start gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <div>
                    <p class="text-sm font-semibold text-[#5C0F8B]">{{ $line['name'] }}</p>
                    <p class="text-xs text-gray-600">{{ $line['note'] }}</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <a
                        href="tel:{{ $line['tel'] }}"
                        class="whitespace-nowrap rounded-full border border-[#5C0F8B] px-3 py-1 text-sm font-semibold text-[#5C0F8B] transition hover:bg-[#5C0F8B] hover:text-white"
                    >
                        Call {{ $line['phone'] }}
                    </a>
                    <a
                        href="{{ $line['sms']['href'] }}"
                        class="whitespace-nowrap rounded-full border border-gray-300 px-3 py-1 text-sm font-semibold text-gray-700 transition hover:border-[#5C0F8B] hover:text-[#5C0F8B]"
                    >
                        {{ $line['sms']['label'] }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</section>
