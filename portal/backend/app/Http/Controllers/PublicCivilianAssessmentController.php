<?php

namespace App\Http\Controllers;

use App\Enums\FollowUpKind;
use App\Enums\NewSubmission;
use App\Http\Requests\Public\CivilianAssessmentStoreRequest;
use App\Models\AssessmentAnswers;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;
use App\Services\FollowUps;
use App\Services\SubmissionAlerts;
use Illuminate\Support\Facades\DB;

class PublicCivilianAssessmentController extends Controller
{
    /*
    POST /api/public/civilian-assessments

    The website's whole civilian assessment in one request, written in one
    transaction through the restricted PortalPublic connection (INSERT-only on
    these three tables), so a failure part-way leaves nothing behind.

    Every column is mapped by hand from validated input, and the ids are
    generated here (HasUuids), never taken from the request. The response is a
    bare confirmation: no ids and none of what was submitted, so nothing about
    the record can be read back through this endpoint.
    */
    public function store(CivilianAssessmentStoreRequest $request, SubmissionAlerts $alerts, FollowUps $followUps)
    {
        $input = $request->validated();
        $identified = $request->submitterIdentified();

        $private = DB::connection('PortalPublic')->transaction(function () use ($input, $identified): PrivateAssessment {
            $answers = [];
            foreach (range(1, CivilianAssessmentStoreRequest::RISK_INDICATORS) as $n) {
                $answers["RiskIndicator{$n}"] = (bool) $input["RiskIndicator{$n}"];
            }

            $assessment = AssessmentAnswers::on('PortalPublic')->create($answers);

            $submitter = $identified
                ? SubmitterInfo::on('PortalPublic')->create([
                    'SubmitterFirstName'   => $input['SubmitterFirstName'],
                    'SubmitterLastName'    => $input['SubmitterLastName'],
                    'SubmitterEmail'       => $input['SubmitterEmail'] ?? null,
                    'SubmitterPhoneNumber' => $input['SubmitterPhoneNumber'] ?? null,
                ])
                : null;

            return PrivateAssessment::on('PortalPublic')->create([
                'VictimFirstName'                 => $input['VictimFirstName'],
                'VictimLastName'                  => $input['VictimLastName'],
                'VictimSex'                       => $input['VictimSex'],
                'VictimDOB'                       => $input['VictimDOB'] ?? null,
                'VictimSafePhoneNumber'           => $input['VictimSafePhoneNumber'],
                'OffenderFirstName'               => $input['OffenderFirstName'],
                'OffenderLastName'                => $input['OffenderLastName'],
                'OffenderSex'                     => $input['OffenderSex'],
                'OffenderDOB'                     => $input['OffenderDOB'] ?? null,
                'OffenderVictimRelationship'      => $input['OffenderVictimRelationship'],
                'OffenderVictimRelationshipOther' => $input['OffenderVictimRelationshipOther'] ?? null,
                'AssessmentDocID'                 => $assessment->AssessmentDocID,
                'SubmissionID'                    => $submitter?->SubmissionID,
            ]);
        });

        DB::disconnect('PortalPublic');

        // Both written through Portal, not the INSERT-only PortalPublic user,
        // which can't read users to find who to alert. Harbor Safe follows up
        // every submission, so each one opens a follow-up.
        $followUps->open(FollowUpKind::CivilianAssessment, $private->DocumentID);
        $alerts->raise(NewSubmission::CivilianAssessment);

        return response()->json(['message' => 'Assessment received'], 201);
    }
}
