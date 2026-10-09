/*
The API origin. NEXT_PUBLIC_API_URL is the ORIGIN by convention across this
repo - every call site below appends its own `/api/...` path, as
website/frontend's forms.js and content.js do.

A trailing slash is stripped because `${API_URL}/api/...` would otherwise
produce a double slash. A trailing `/api` is deliberately NOT stripped: if the
backend is ever mounted under a sub-path, that segment is legitimately part of
the origin, and removing it would break that deployment.

What is guarded instead is the mistake that actually happened: a .env set to
"http://localhost:8000/api", which made every request
http://localhost:8000/api/api/assessments and failed the whole civilian
assessment with a 404 that read as the API being missing. See .env.example.
*/
const API_URL = (process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000').replace(/\/+$/, '');

// Development-only, and loud: a silent 404 on submit is extremely expensive to
// diagnose, and the symptom points at the backend rather than at this value.
if (process.env.NODE_ENV !== 'production' && /\/api$/.test(API_URL)) {
    console.error(
        `[api] NEXT_PUBLIC_API_URL is "${API_URL}", which ends in /api. ` +
        'Requests will go to /api/api/... and return 404. It should be the ' +
        'origin only, e.g. http://localhost:8000 - see .env.example.'
    );
}

/*
POSTs JSON and returns the parsed body, or throws with something a developer
can act on.

The three call sites below each used to do their own `await response.json()`
inside the !ok branch, which has two failure modes. A response that is not
JSON - an HTML error page, a proxy's 502, an empty body - makes that parse
throw a SyntaxError, so the real status is lost and the message becomes
"Unexpected token '<'". And a 404 reported only as its body gives no hint that
the URL itself was wrong, which is exactly what made the /api/api/... mistake
above hard to place.
*/
async function postJson(path, payload, failureMessage) {
    const url = `${API_URL}${path}`;

    let response;

    try {
        response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    } catch (cause) {
        // fetch only rejects on a network-level failure, which almost always
        // means the backend is not running.
        throw new Error(
            `${failureMessage}: could not reach the server at ${API_URL}. Is the backend running?`,
            { cause },
        );
    }

    const body = await response.text();

    let parsed = null;
    try {
        parsed = body ? JSON.parse(body) : null;
    } catch {
        // Left null on purpose - the status and the raw body below are more
        // useful than a parse error.
    }

    if (!response.ok) {
        if (response.status === 404) {
            throw new Error(
                `${failureMessage}: ${url} returned 404. The route does not exist - ` +
                'check NEXT_PUBLIC_API_URL is the origin only, with no trailing /api.',
            );
        }

        throw new Error(
            parsed?.message || `${failureMessage} (HTTP ${response.status})`,
        );
    }

    return parsed;
}

/*
The "Relationship to victim" options, from the backend's single list
(App\Enums\OffenderRelationship via GET /api/public/relationships), so this
form never keeps its own copy. [{ value: "spouse", label: "Spouse" }, ...];
the form sends `value`. Throws when the list can't be loaded, so the caller
can show a retry rather than an empty dropdown.
*/
export async function fetchRelationshipOptions() {
    let response;

    try {
        response = await fetch(`${API_URL}/api/public/relationships`, {
            headers: { 'Accept': 'application/json' },
        });
    } catch {
        throw new Error(`Could not load the relationship options: could not reach the server at ${API_URL}.`);
    }

    if (!response.ok) {
        throw new Error(`Could not load the relationship options (HTTP ${response.status}).`);
    }

    return await response.json();
}

export async function submitAssessment({
    anonymous,
    forWhom,
    firstName,
    lastName,
    email,
    phone,
    victimFirstName,
    victimLastName,
    victimDob,
    victimSex,
    victimPhone,
    offenderFirstName,
    offenderLastName,
    offenderDob,
    offenderSex,
    offenderRelationship,
    offenderRelationshipOther,
    answers,
}) {
    const answersPayload = {
        RiskIndicator1:  answers[1]  ?? false,
        RiskIndicator2:  answers[2]  ?? false,
        RiskIndicator3:  answers[3]  ?? false,
        RiskIndicator4:  answers[4]  ?? false,
        RiskIndicator5:  answers[5]  ?? false,
        RiskIndicator6:  answers[6]  ?? false,
        RiskIndicator7:  answers[7]  ?? false,
        RiskIndicator8:  answers[8]  ?? false,
        RiskIndicator9:  answers[9]  ?? false,
        RiskIndicator10: answers[10] ?? false,
        RiskIndicator11: answers[11] ?? false,
    };

    const answersData = await postJson(
        '/api/assessments',
        answersPayload,
        'Failed to save assessment answers',
    );

    // /api/assessments wraps the created record in { data: ... }; note that
    // /api/law-enforcement-assessments does not. See CLAUDE.md.
    const assessmentDocID = answersData.data.AssessmentDocID;

    let submitterID = null;

    if (forWhom === "other" && anonymous === false) {
        const submitterPayload = {
            SubmitterFirstName:   firstName,
            SubmitterLastName:    lastName,
            SubmitterEmail:       email || null,
            SubmitterPhoneNumber: phone || null
        };

        const submitterData = await postJson(
            '/api/submitter-info',
            submitterPayload,
            'Failed to save submitter information',
        );

        submitterID = submitterData.data.SubmissionID;
    }

    const personalInfoPayload = {
        OffenderFirstName:           offenderFirstName,
        OffenderLastName:            offenderLastName,
        OffenderSex:                 offenderSex || null,
        OffenderDOB:                 offenderDob || null,
        // A code from the list; the typed detail only goes with "other"
        // (the server refuses it otherwise).
        OffenderVictimRelationship:  offenderRelationship || null,
        OffenderVictimRelationshipOther: offenderRelationship === 'other'
            ? (offenderRelationshipOther?.trim() || null)
            : null,
        VictimFirstName:             victimFirstName,
        VictimLastName:              victimLastName,
        VictimSex:                   victimSex || null,
        VictimDOB:                   victimDob || null,
        VictimSafePhoneNumber:       victimPhone || null,
        SubmissionID:                submitterID,
        AssessmentDocID:             assessmentDocID,
    };

    return await postJson(
        '/api/private-assessments',
        personalInfoPayload,
        'Failed to save personal information',
    );
}
