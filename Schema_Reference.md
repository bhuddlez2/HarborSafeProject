# HarborSafe Portal Schema — Reference

Human-readable view of the `Portal` MySQL connection, generated because migration PHP files don't give a good at-a-glance picture of the schema. Regenerate with the `SHOW CREATE TABLE` query documented in `CLAUDE.md` after future migrations. Originally compared against `Original_Schema_design.md` (the pre-build design doc) — see the gap analysis at the bottom for what changed and why.

## Current live schema

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email
        string password
        string role "law_enforcement / secretary / admin"
        bool is_active
        text two_factor_secret
        text two_factor_recovery_codes
    }
    agencies {
        bigint id PK
        string name
    }
    law_enforcement_agents {
        bigint user_id PK_FK
        string badge_number
        bigint agency_id FK
    }
    law_enforcement_assessment {
        uuid DocumentID PK
        timestamp DateCreated
        bigint submitted_by FK
        string OffenderFirstName
        string OffenderLastName
        string OffenderSex
        date OffenderDOB
        string OffenderVictimRelationship
        string VictimFirstName
        string VictimLastName
        string VictimSex
        date VictimDOB
        string VictimSafePhoneNumber
        uuid AssessmentDocID FK
    }
    assessment_change_log {
        uuid ChangeLogID PK
        uuid DocumentID FK
        string ChangeField
        string PreviousValue
        string NewValue
        bigint ChangedBy FK
        timestamp TimeStamp
    }
    assessment_answer_change_log {
        uuid LogID PK
        uuid AssessmentDocID FK
        string ChangeField
        bool PreviousValue
        bool NewValue
        bigint ChangedBy FK
        timestamp TimeStamp
    }
    _assessment_answers {
        uuid AssessmentDocID PK
        bool RiskIndicator1
        bool RiskIndicator2
        bool RiskIndicator3
        bool RiskIndicator4
        bool RiskIndicator5
        bool RiskIndicator6
        bool RiskIndicator7
        bool RiskIndicator8
        bool RiskIndicator9
        bool RiskIndicator10
        bool RiskIndicator11
    }
    _submitter_info {
        uuid SubmissionID PK
        string SubmitterEmail
        string SubmitterPhoneNumber
        string SubmitterFirstName
        string SubmitterLastName
    }
    _private_assessment {
        uuid DocumentID PK
        timestamp DateCreated
        string OffenderFirstName
        string OffenderLastName
        string OffenderSex
        date OffenderDOB
        string OffenderVictimRelationship
        string VictimFirstName
        string VictimLastName
        string VictimSex
        date VictimDOB
        string VictimSafePhoneNumber
        uuid SubmissionID FK
        uuid AssessmentDocID FK
    }

    users ||--o| law_enforcement_agents : "1:1, law_enforcement accounts only"
    law_enforcement_agents }o--o| agencies : "agency_id"
    users ||--o{ law_enforcement_assessment : "submitted_by"
    law_enforcement_assessment }o--|| _assessment_answers : "AssessmentDocID"
    law_enforcement_assessment ||--o{ assessment_change_log : "DocumentID"
    _assessment_answers ||--o{ assessment_answer_change_log : "AssessmentDocID"
    _private_assessment }o--|| _assessment_answers : "AssessmentDocID"
    _private_assessment }o--o| _submitter_info : "SubmissionID (nullable = anonymous)"
```

Framework/Sanctum tables also present but not shown above (no app-specific structure worth diagramming): `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `password_reset_tokens`, `personal_access_tokens`.

### Table detail

| Table | Purpose | Key columns | Notes |
|---|---|---|---|
| `users` | Every account, any role | `id` (PK), `role`, `is_active` | `role` is a native PHP enum (`App\Enums\UserRole`) cast, not a raw string comparison. Two-factor columns are Fortify-compatible and currently unused (login not built yet). |
| `agencies` | Lookup table for law-enforcement agency types | `id` (PK), `name` | |
| `law_enforcement_agents` | Badge/agency data, only for `role = law_enforcement` accounts | `user_id` (PK, FK → `users.id`) | 1:1 profile extension — secretary/admin accounts simply have no row here, rather than null columns on `users`. |
| `law_enforcement_assessment` | LE-submitted offender/victim record | `DocumentID` (PK, uuid), `submitted_by` (FK → `users.id`, not nullable) | `submitted_by` is the ownership column — this is what will make "law enforcement sees only their own submissions" enforceable once policies are built. |
| `assessment_change_log` | Audit trail for edits to `law_enforcement_assessment` | `ChangeLogID` (PK), `DocumentID` (FK), `ChangedBy` (FK → `users.id`) | |
| `assessment_answer_change_log` | Audit trail for edits to `_assessment_answers` | `LogID` (PK), `AssessmentDocID` (FK), `ChangedBy` (FK → `users.id`) | |
| `_assessment_answers` | The 11-question risk-indicator answers | `AssessmentDocID` (PK, uuid) | Shared by both `law_enforcement_assessment` and `_private_assessment` |
| `_submitter_info` | Optional submitter contact info for public/anonymous submissions | `SubmissionID` (PK, uuid) | |
| `_private_assessment` | Public/anonymous-capable offender/victim record | `DocumentID` (PK, uuid), `SubmissionID` (FK, nullable) | No `submitted_by` — there's no authenticated account behind a public submission. Unchanged by the schema-completion pass. |

**Connections:** every table above lives on the `Portal` MySQL connection (see `config/database.php`), including `users` — this was a discovered inconsistency (the default `mariadb` connection is what `users`/`sessions`/etc. were originally intended for, but migrations were actually run with `--database=Portal`). `App\Models\User` now explicitly declares `protected $connection = 'Portal'` to match reality.

---

## Gap analysis vs. `Original_Schema_design.md` — resolved

| Original table | Status | What was built |
|---|---|---|
| `tblAssessmentAnswers` | ✅ Built (`_assessment_answers`) | Matches almost field-for-field |
| `tblPrivateAssessment` | ✅ Built (`_private_assessment`) | Submitter fields split into `_submitter_info` per the original design's own note; `IncidentCounty` intentionally not added (dropped per client request) |
| `tblLawEnforcementAssessment` | ✅ Built (`law_enforcement_assessment`), as its own table | Kept separate from `_private_assessment` per your call. Uses `submitted_by` (FK → `users.id`) instead of the original's inline `SubmitterEmail`, since real accounts now exist. No Incident fields (same client decision). |
| `tblUserCredentials` | ✅ Superseded by `users` | Kept Laravel's auto-increment `id` rather than the original's email-as-PK design (Sanctum/Eloquent convention); added a 3-value `role` enum instead of the original's binary `Admin` boolean, since `secretary` was added to the requirements after the original doc was written. |
| `tblLawEnforcementAgents` | ✅ Built (`law_enforcement_agents`) | Kept as its own table per your call, rather than folding badge/agency into `users` — avoids null columns on non-law-enforcement accounts. Keyed by `user_id` instead of email. |
| `tblAgency` | ✅ Built (`agencies`) | Added a `name` column — the original design was a bare UUID primary key with no label, which wouldn't function as a usable lookup table. |
| `tblAssessmentChangeLog` | ✅ Built (`assessment_change_log`) | Scoped to `law_enforcement_assessment` only (not the public/anonymous `_private_assessment` — no authenticated editor exists for those records). `ChangedBy` is a real FK to `users.id` instead of a loose string. Dropped the original's `AssessmentChange` boolean column (unclear purpose). Fixed an apparent mislabeling: the original called its record-reference column `AssessmentDocID`, but it clearly meant the assessment's own document id, not `_assessment_answers`' key — renamed to `DocumentID` here. |
| `tblAssessmentAnswerChangeLog` | ✅ Built (`assessment_answer_change_log`) | Scoped to `_assessment_answers`. Same `ChangedBy` FK treatment. |

### Naming drift (cosmetic, not functional)
- `OffenderRelationship` (original) → `OffenderVictimRelationship` (current, both tables) — same field, clarified name.
- The three original tables carry a leading underscore (`_assessment_answers`, `_private_assessment`, `_submitter_info`) for reasons lost to history; the five tables added in this pass use plain snake_case instead of propagating that convention. Normalizing all of them to one style is a possible future cleanup, not done here.

### Requirements captured in the original design notes — not yet implemented (this was a schema-only pass)
- Password policy: minimum 15 characters, maximum 64, checked against a common-password list — this is application-layer validation, not a schema concern; the `password` column already comfortably fits any hash Laravel produces.
- Two-factor authentication — the `users` table now has Fortify-compatible columns for this, but no 2FA logic exists yet.
- Authorization rule (admins see everything, law-enforcement see only their own) — the schema now has what's needed to enforce this (`submitted_by`), but no policies/middleware exist yet. That's the next phase: building the actual login flow, not schema.
