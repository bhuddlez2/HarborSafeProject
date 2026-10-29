{{--
    Intro phase. The second paragraph says when the assessment is saved, in
    the civilian assessment's meaning (#31): only at Submit. It says "close
    this tab" rather than "leave the page", because an unfinished form is kept
    in the tab and offered back (AssessmentDraft); closing the tab loses it.
--}}
<div>
    <h1 class="text-3xl font-semibold text-gray-900 mb-6">
        Lethality Assessment
    </h1>
    <p class="text-gray-700 text-lg mb-4 leading-relaxed">
        This screen contains {{ $total }} yes/no questions. Ask each question exactly as written.
    </p>
    <p class="text-gray-700 text-lg leading-relaxed">
        Nothing is saved to the assessment record until you press Submit at the end.
        If you close this tab before then, your answers are gone.
    </p>
</div>
