const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://backend.test';

export async function submitAssessment({
    anonymous,
    forWhom,
    firstName,
    lastName,
    relationship,
    phone,
    subjectFirstName,
    subjectLastName,
    subjectAge,
    subjectSex,
    abuserFirstName,
    abuserLastName,
    abuserAge,
    abuserSex,
    answers,
}) {
    // Step 1 — save the assessment answers, get back the AssessmentDocID
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

    const answersResponse = await fetch(`${API_BASE_URL}/api/assessments`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':        'application/json',
        },
        body: JSON.stringify(answersPayload),
    });

    if (!answersResponse.ok) {
        const error = await answersResponse.json();
        throw new Error(error.message || 'Failed to save assessment answers');
    }

    const answersData = await answersResponse.json();
    const assessmentDocID = answersData.data.AssessmentDocID;

    // Step 2 — save the personal info, linked via AssessmentDocID
    const personalInfoPayload = {
        OffenderFirstName:           abuserFirstName,
        OffenderLastName:            abuserLastName,
        OffenderSex:                 abuserSex ? abuserSex.charAt(0).toUpperCase() : null,
        OffenderDOB:                 null, // not collected in current form (only age)
        OffenderVictimRelationship:  relationship || null,

        VictimFirstName:             subjectFirstName,
        VictimLastName:              subjectLastName,
        VictimSex:                   subjectSex ? subjectSex.charAt(0).toUpperCase() : null,
        VictimDOB:                   null, // not collected in current form (only age)
        VictimSafePhoneNumber:       phone || null,

        AssessmentDocID:             assessmentDocID,
    };

    const infoResponse = await fetch(`${API_BASE_URL}/api/private-assessments`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':        'application/json',
        },
        body: JSON.stringify(personalInfoPayload),
    });

    if (!infoResponse.ok) {
        const error = await infoResponse.json();
        throw new Error(error.message || 'Failed to save personal information');
    }

    return await infoResponse.json();
}