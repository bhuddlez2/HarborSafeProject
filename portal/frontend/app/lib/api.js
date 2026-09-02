const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000';

export async function getAssessments() {
    const response = await fetch(`${API_URL}/api/assessments`);
    if (!response.ok) throw new Error('Failed to fetch assessments');
    return response.json();
}

export async function getAssessment(id) {
    const response = await fetch(`${API_URL}/api/assessments/${id}`);
    if (!response.ok) throw new Error('Failed to fetch assessment');
    return response.json();
}

export async function createAssessment(data) {
    const response = await fetch(`${API_URL}/api/assessments`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error('Failed to create assessment');
    return response.json();
}

export async function updateAssessment(id, data) {
    const response = await fetch(`${API_URL}/api/assessments/${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    });
    if (!response.ok) throw new Error('Failed to update assessment');
    return response.json();
}

export async function deleteAssessment(id) {
    const response = await fetch(`${API_URL}/api/assessments/${id}`, {
        method: 'DELETE',
    });
    if (!response.ok) throw new Error('Failed to delete assessment');
    return response.json();
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

    const answersResponse = await fetch(`${API_URL}/api/assessments`, {
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

    let submitterID = null;

    if (forWhom === "other" && anonymous === false) {
        const submitterPayload = {
            SubmitterFirstName:   firstName,
            SubmitterLastName:    lastName,
            SubmitterEmail:       email || null,
            SubmitterPhoneNumber: phone || null
        };

        const submitterResponse = await fetch(`${API_URL}/api/submitter-info`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'Accept': 'application/json' },
            body: JSON.stringify(submitterPayload),
        });

        if (!submitterResponse.ok) {
            const error = await submitterResponse.json();
            throw new Error(error.message || 'Failed to save submitter information');
        }

        const submitterData = await submitterResponse.json();
        submitterID = submitterData.data.SubmissionID;
    }

    const personalInfoPayload = {
        OffenderFirstName:           offenderFirstName,
        OffenderLastName:            offenderLastName,
        OffenderSex:                 offenderSex || null,
        OffenderDOB:                 offenderDob || null,
        OffenderVictimRelationship:  offenderRelationship || null,
        VictimFirstName:             victimFirstName,
        VictimLastName:              victimLastName,
        VictimSex:                   victimSex || null,
        VictimDOB:                   victimDob || null,
        VictimSafePhoneNumber:       victimPhone || null,
        SubmissionID:                submitterID,
        AssessmentDocID:             assessmentDocID,
    };

    const infoResponse = await fetch(`${API_URL}/api/private-assessments`, {
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