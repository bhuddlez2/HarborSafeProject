{{--
    Event preview card — rendered inside the wizard's final "Preview" step.

    All variables are passed in from EventResource's Placeholder::content()
    closure, which reads them from the Livewire form state via $get(). Because
    this is just a Blade template (not a Livewire component), it does not
    update live as the user types — it renders once when the step is opened.
    Going back and changing a value then returning to Preview will re-render it.

    Styling uses inline styles throughout so the card works without depending
    on Tailwind compilation. The purple brand colour (#5C0F8B) matches the
    rest of the panel.
--}}
<div style="font-family: 'Nunito', sans-serif; max-width: 680px; margin: 0 auto;">

    {{--
        Image section — three possible states:
          1. $imageUrl is set: the image is already saved in content_files
             (editing an existing event), so we can show a real <img> tag
             using the staff file route.
          2. $hasImage is true but $imageUrl is null: a file was selected in
             the Image step but not yet saved (creating a new event). Livewire
             holds it as a temporary upload that has no database row yet, so
             there is no URL to use — show the placeholder instead.
          3. Neither: no image at all, render nothing here.
        The card body's border-radius adjusts based on whether anything sits
        above it, so the corners always look right.
    --}}
    @if($imageUrl)
    <img src="{{ $imageUrl }}" alt="{{ $title }}"
         style="width: 100%; height: 200px; object-fit: cover; border-radius: 12px 12px 0 0; display: block;">
    @elseif($hasImage)
    <div style="height: 160px; background: #ede3f5; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: center; gap: 10px; color: #5C0F8B; margin-bottom: 0;">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>
        <span style="font-size: 13px; font-weight: 600;">Image attached — visible after saving</span>
    </div>
    @endif

    {{-- Card body — the main content area --}}
    <div style="border: 1px solid #e5e7eb; border-radius: {{ ($imageUrl || $hasImage) ? '0 0 12px 12px' : '12px' }}; padding: 28px; background: #fff;">

        {{--
            Badges row — only rendered when there is something to show.
            Category comes from looking up the category_id FK in the closure.
            Cancelled and Draft are shown here rather than just at the bottom
            so they are immediately visible alongside the title.
        --}}
        @if($categoryName || $isCancelled || !$isPublished)
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;">
            @if($categoryName)
            <span style="background: #ede3f5; color: #5C0F8B; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 999px;">{{ $categoryName }}</span>
            @endif
            @if($isCancelled)
            <span style="background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 999px;">Cancelled</span>
            @endif
            @if(!$isPublished)
            <span style="background: #f3f4f6; color: #6b7280; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 999px;">Draft</span>
            @endif
        </div>
        @endif

        {{-- Title --}}
        <h2 style="font-size: 22px; font-weight: 700; color: #111827; margin: 0 0 16px;">
            {{ filled($title) ? $title : '(No title yet)' }}
        </h2>

        {{--
            Date and time — $startsAt/$endsAt are Carbon instances already
            converted to Eastern time by the closure, so ->format() gives the
            correct wall-clock time without any further conversion here.
        --}}
        @if($startsAt)
        <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; color: #374151; font-size: 14px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#5C0F8B" stroke-width="2" style="flex-shrink:0; margin-top:1px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            <span>
                @if($allDay)
                    {{ $startsAt->format('F j, Y') }}{{ $endsAt ? ' – ' . $endsAt->format('F j, Y') : '' }} &middot; All day
                @else
                    {{ $startsAt->format('F j, Y') }} &middot; {{ $startsAt->format('g:i A') }}{{ $endsAt ? ' – ' . $endsAt->format('g:i A') : '' }} ET
                @endif
            </span>
        </div>
        @endif

        {{-- Location — shown for both physical and virtual events --}}
        @if($locationIsVirtual || filled($locationName) || filled($locationAddress))
        <div style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 16px; color: #374151; font-size: 14px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#5C0F8B" stroke-width="2" style="flex-shrink:0; margin-top:1px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
            </svg>
            <span>
                @if($locationIsVirtual)
                    Virtual event{{ filled($locationVirtualNote) ? ' · ' . $locationVirtualNote : '' }}
                @else
                    {{-- collect()->filter()->implode() drops any null/empty fields
                         so we don't get a stray " · " when only one is filled --}}
                    {{ collect([$locationName, $locationAddress])->filter()->implode(' · ') }}
                @endif
            </span>
        </div>
        @endif

        {{-- Summary — the one-line teaser shown in the event list on the website --}}
        @if(filled($summary))
        <p style="font-size: 15px; color: #374151; margin: 0 0 14px; line-height: 1.6;">{{ $summary }}</p>
        @endif

        {{--
            Description paragraphs — the description field stores a JSON array
            of paragraph strings, but here $description is the raw textarea
            value (before dehydrateStateUsing converts it on save). We split on
            blank lines manually to match the same logic the form uses.
        --}}
        @php
            $paragraphs = filled($description)
                ? collect(preg_split('/\R{2,}/', trim($description)))->filter()->values()
                : collect();
        @endphp
        @foreach($paragraphs as $paragraph)
        <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px; line-height: 1.6;">{{ $paragraph }}</p>
        @endforeach

        {{-- Registration button — suppressed when the event is cancelled --}}
        @if(filled($registrationUrl) && !$isCancelled)
        <div style="margin-top: 20px;">
            <span style="display: inline-block; background: #5C0F8B; color: #fff; font-size: 14px; font-weight: 600; padding: 10px 22px; border-radius: 8px;">
                {{ filled($registrationLabel) ? $registrationLabel : 'Register now' }} →
            </span>
        </div>
        @endif

        @if(!filled($title) && !$startsAt)
        <p style="font-size: 13px; color: #9ca3af; margin-top: 8px;">Fill in the earlier steps to see the preview update.</p>
        @endif
    </div>

    {{--
        Draft warning — shown below the card when is_published is off.
        Separated from the Draft badge inside the card so it is harder to miss.
    --}}
    @if(!$isPublished)
    <div style="margin-top: 12px; padding: 10px 16px; background: #fef9c3; border-radius: 8px; font-size: 13px; color: #854d0e; display: flex; gap: 8px; align-items: center;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        This event is set to <strong>Draft</strong> — it won't appear on the website until Published is turned on.
    </div>
    @endif

</div>
