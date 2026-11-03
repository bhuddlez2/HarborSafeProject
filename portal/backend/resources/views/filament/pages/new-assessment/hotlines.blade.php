{{--
    Hotline numbers on the "Assessment submitted" screen (team plan task 12),
    for the officer to share with the victim. Same numbers as the public
    website's crisis box (website/frontend/src/app/page.js) - change them there
    too. The website's "call 911" line is left out here on purpose: the people
    using this screen are the police (team plan item 7). Each line is a card
    laid out like Harbor Safe's - name, note, full-width Call and Text buttons -
    with Harbor Safe's alone filled purple so it stays first. Every number is
    shown as text and is a working call or text link on a phone.
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
        Numbers to Share with the Victim
    </h2>

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

    @foreach ($others as $line)
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <p class="text-sm font-semibold text-[#5C0F8B]">{{ $line['name'] }}</p>
            <p class="mb-3 text-xs text-gray-600">{{ $line['note'] }}</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <a
                    href="tel:{{ $line['tel'] }}"
                    class="rounded-lg border-2 border-[#5C0F8B] bg-white py-2.5 text-center text-sm font-semibold text-[#5C0F8B] transition hover:bg-[#5C0F8B] hover:text-white"
                >
                    Call {{ $line['phone'] }}
                </a>
                <a
                    href="{{ $line['sms']['href'] }}"
                    class="rounded-lg border-2 border-gray-300 bg-white py-2.5 text-center text-sm font-semibold text-gray-700 transition hover:border-[#5C0F8B] hover:text-[#5C0F8B]"
                >
                    {{ $line['sms']['label'] }}
                </a>
            </div>
        </div>
    @endforeach
</section>
