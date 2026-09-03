import { apiRequest } from "./client";

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

    const answersData = await apiRequest('/api/assessments', {
        method: 'POST',
        body: answersPayload,
        errorMessage: 'Failed to save assessment answers',
    });
    const assessmentDocID = answersData.data.AssessmentDocID;

    let submitterID = null;

    if (forWhom === "other" && anonymous === false) {
        const submitterPayload = {
            SubmitterFirstName:   firstName,
            SubmitterLastName:    lastName,
            SubmitterEmail:       email || null,
            SubmitterPhoneNumber: phone || null
        };

        const submitterData = await apiRequest('/api/submitter-info', {
            method: 'POST',
            body: submitterPayload,
            errorMessage: 'Failed to save submitter information',
        });
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

    return apiRequest('/api/private-assessments', {
        method: 'POST',
        body: personalInfoPayload,
        errorMessage: 'Failed to save personal information',
    });
}
