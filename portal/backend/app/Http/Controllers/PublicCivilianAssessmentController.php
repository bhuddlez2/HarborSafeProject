<?php

namespace App\Http\Controllers;

use App\Enums\NewSubmission;
use App\Http\Requests\Public\CivilianAssessmentStoreRequest;
use App\Models\AssessmentAnswers;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;
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
    public function store(CivilianAssessmentStoreRequest $request, SubmissionAlerts $alerts)
    {
        $input = $request->validated();
        $identified = $request->submitterIdentified();

        DB::connection('PortalPublic')->transaction(function () use ($input, $identified) {
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

            PrivateAssessment::on('PortalPublic')->create([
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

        // Written through Portal, not the INSERT-only PortalPublic user, which
        // can't read users to find who to alert.
        $alerts->raise(NewSubmission::CivilianAssessment);

        return response()->json(['message' => 'Assessment received'], 201);
    }
}
