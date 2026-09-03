import { apiRequest } from "./client";

export async function submitLawEnforcementAssessment({
    submittedBy,
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

    const assessmentPayload = {
        submitted_by:                submittedBy,
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
        AssessmentDocID:             assessmentDocID,
    };

    // Unlike /api/assessments, this endpoint returns the created record
    // directly, not wrapped in { data: ... }.
    return apiRequest('/api/law-enforcement-assessments', {
        method: 'POST',
        body: assessmentPayload,
        errorMessage: 'Failed to save law enforcement assessment',
    });
}
