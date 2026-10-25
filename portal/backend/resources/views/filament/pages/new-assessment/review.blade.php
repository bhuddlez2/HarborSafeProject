{{--
    Complete phase: review & submit (team plan task 23). Every detail and every
    answer, so the officer checks the whole assessment before submitting; the
    safe-number checkbox (NewAssessment::reviewStep()) follows below.

    The same layout as the civilian review (website/frontend/src/app/assessment):
    an Edit button on each section and a Change button on each answer. Edit
    opens that step, whose Continue then reads "Return to review" and comes
    straight back (NewAssessment::backToReview()); Change opens that one
    question, and answering it comes straight back (answerQuestion()). Unlike
    the civilian review, yes answers stay highlighted and counted: officers act
    on them. goToStep() and getSteps() belong to Filament's wizard Alpine
    component, which this view sits inside.
--}}
@php
    use App\Enums\OffenderRelationship;
    use App\Support\AssessmentFields;

    $sex = fn (?string $code): string => AssessmentFields::SEX_OPTIONS[$code] ?? '—';

    // Date-only values: shown as entered, never time-zone converted.
    $date = fn (?string $value): string => filled($value) ? \Carbon\Carbon::parse($value)->format('M j, Y') : '—';

    $name = fn (string $who): string => trim(($data[$who.'FirstName'] ?? '').' '.($data[$who.'LastName'] ?? '')) ?: '—';

    $answer = fn (int $id): ?bool => array_key_exists("RiskIndicator{$id}", $data) && $data["RiskIndicator{$id}"] !== null
        ? (bool) $data["RiskIndicator{$id}"]
        : null;

    $yesCount = collect(array_keys($questions))->filter(fn (int $id): bool => $answer($id) === true)->count();

    $sections = [
        'Victim' => [
            'step' => $victimStep,
            'editLabel' => "Edit the victim's details",
            'fields' => [
                'Name' => $name('Victim'),
                'Sex' => $sex($data['VictimSex'] ?? null),
                'Date of birth' => $date($data['VictimDOB'] ?? null),
                'Safe phone number' => $data['VictimSafePhoneNumber'] ?? '—',
            ],
        ],
        'Offender' => [
            'step' => $offenderStep,
            'editLabel' => "Edit the offender's details",
            'fields' => [
                'Name' => $name('Offender'),
                'Sex' => $sex($data['OffenderSex'] ?? null),
                'Date of birth' => filled($data['OffenderDOB'] ?? null) ? $date($data['OffenderDOB']) : 'Not given',
                'Relationship to victim' => OffenderRelationship::display(
                    $data['OffenderVictimRelationship'] ?? null,
                    $data['OffenderVictimRelationshipOther'] ?? null,
                ) ?? '—',
            ],
        ],
    ];

    $editButton = 'text-sm font-semibold text-[#5C0F8B] underline underline-offset-2 rounded px-1 hover:text-[#4C0B74] focus:outline-none focus:ring-2 focus:ring-[#5C0F8B]/40';
@endphp

<div>
    <h1 class="text-3xl font-semibold text-gray-900 mb-2">
        Review &amp; Submit
    </h1>
    <p class="text-gray-600 mb-6">
        Check every detail and answer below. Use Edit or Change to fix anything before you submit.
    </p>

    <div class="flex flex-col gap-4">
        @foreach ($sections as $title => $section)
            <section class="rounded-xl bg-[#F5F0FB] p-4 md:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-[#5C0F8B]">{{ $title }}</h2>
                    <button
                        type="button"
                        x-on:click="goToStep(getSteps()[{{ $section['step'] }}])"
                        aria-label="{{ $section['editLabel'] }}"
                        class="{{ $editButton }}"
                    >
                        Edit
                    </button>
                </div>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ($section['fields'] as $label => $value)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-[#9068B8]">{{ $label }}</dt>
                            <dd class="font-semibold text-gray-900 wrap-break-word">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        <section class="overflow-hidden rounded-xl border-2 border-[#C9C1D6]">
            <div class="flex items-center justify-between gap-3 bg-[#EDE3F5] px-4 py-3">
                <h2 class="text-xs font-bold uppercase tracking-widest text-[#5C0F8B]">Answers</h2>
                <span class="rounded-full px-3 py-0.5 text-sm font-bold {{ $yesCount >= 4 ? 'bg-red-50 text-red-700' : ($yesCount >= 1 ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-600') }}">
                    {{ $yesCount }} of {{ count($questions) }} yes
                </span>
            </div>
            <ol class="divide-y divide-[#EDE3F5]">
                @foreach (array_values($questions) as $position => $question)
                    @php
                        $id = array_keys($questions)[$position];
                        $value = $answer($id);
                    @endphp
                    <li class="flex flex-wrap items-start gap-3 px-4 py-3 {{ $value === true ? 'bg-red-50/60' : '' }}">
                        <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $value === true ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }}">{{ $id }}</span>
                        <span class="min-w-48 flex-1 text-sm leading-relaxed text-gray-700">{{ $question }}</span>
                        <span class="ml-auto flex shrink-0 items-center gap-2">
                            <span class="rounded-md px-2 py-0.5 text-xs font-bold tracking-wide {{ $value === true ? 'bg-red-100 text-red-700' : ($value === false ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700') }}">
                                {{ $value === null ? 'NOT ANSWERED' : ($value ? 'YES' : 'NO') }}
                            </span>
                            <button
                                type="button"
                                x-on:click="$wire.editAnswer({{ $position }}).then(() => goToStep(getSteps()[{{ $questionsStep }}]))"
                                aria-label="Change answer to question {{ $id }}"
                                class="{{ $editButton }}"
                            >
                                Change
                            </button>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    @if ($submitError)
        <p class="text-red-600 text-sm mt-6">{{ $submitError }}</p>
    @endif
</div>
