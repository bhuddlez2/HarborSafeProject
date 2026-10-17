<?php

namespace Database\Seeders;

use App\Models\ResourceRequestForm;
use App\Models\ServiceFeedback;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
Service feedback and resource requests for exercising the Submissions screens:
every rating, comments missing, short and long, two forms entered by staff
(the "Staff" badge), and requests with email only, phone only, both, no last
name, no county and zero to several resources of interest. Values stay inside
the public forms' own validation rules. Local development only, not registered
in DatabaseSeeder. Run LocalStaffUserSeeder first (staff entries need the
secretary and admin accounts).

  php artisan db:seed --class=LocalSubmissionSeeder --database=Portal

Dates are fixed rather than relative to today, so a re-run recognises its own
rows: one is skipped when a row with the same submission date (and, for
feedback, service and rating; for requests, first name) already exists. Never
deletes anything. Dates are UTC, as stored.
*/
class LocalSubmissionSeeder extends Seeder
{
    // [submitted (UTC), service id, rating, comment, entered by email or null for online]
    private const FEEDBACK = [
        ['2026-10-14 15:42:00', 2, 5, 'The counselor I met with was patient and never made me feel rushed. Thank you.', null],
        ['2026-10-13 02:10:00', 6, 5, 'Called at 2am and someone picked up right away.', null],
        ['2026-10-11 18:05:00', 3, 4, null, null],
        ['2026-10-09 13:30:00', 1, 4, 'Shelter was clean and staff were kind. Hard to sleep with the noise but I understand.', null],
        ['2026-10-07 20:47:00', 4, 3, 'The advocate was helpful but I waited a long time at the courthouse before we could talk.', null],
        ['2026-10-03 16:00:00', 1, 5, null, 'secretary@harborsafe.test'],
        ['2026-09-29 11:12:00', 5, 2, 'The workshop felt too general. I hoped for more about what to do if a friend tells you something.', null],
        ['2026-09-24 22:31:00', 6, 1, 'I was put on hold for a long time and then the call dropped. I did not call back.', null],
        ['2026-09-18 14:20:00', 2, 4, 'Wrote this on the card at the front desk: my counselor helped me make a plan I can actually follow.', 'admin@harborsafe.test'],
        ['2026-09-10 17:55:00', 3, 5, "I didn't think a group would help, but hearing from people who have been through the same thing changed how I see what happened to me. I've been coming every week since August and I finally feel like I am not the only one. The facilitator keeps it safe and nobody is pushed to share before they are ready. I would tell anyone thinking about it to just come once and see. Please keep the Tuesday evening time; it is the only one I can make around work and my kids' schedule.", null],
        ['2026-08-27 09:03:00', 4, 2, null, null],
        ['2026-08-15 19:40:00', 5, 3, 'Good information. The room was too small for the number of people.', null],
    ];

    // [submitted (UTC), first, last, email, phone, county id, resource type ids, message]
    private const REQUESTS = [
        ['2026-10-14 21:18:00', 'Monica', 'Reyes', 'monica.reyes@example.test', '423-555-0190', 1, [1, 2], 'I need somewhere to stay for me and my two kids as soon as possible.'],
        ['2026-10-12 12:45:00', 'Kayla', null, null, '423-555-0191', 2, [3, 5], 'How do I get an order of protection? Please only call, do not text.'],
        ['2026-10-10 03:27:00', 'Sam', 'Whitaker', 'sam.w@example.test', null, null, [], null],
        ['2026-10-06 16:09:00', 'Brenda', 'Lowe', 'blowe@example.test', '(423) 555-0192', 1, [4], 'Looking for counseling for my teenage son who witnessed abuse at home.'],
        ['2026-10-01 10:33:00', 'Destiny', 'Tran', null, '423.555.0193', 2, [6, 7, 1], 'I left with nothing. I need a ride to the shelter and some clothes for work.'],
        ['2026-09-22 23:58:00', 'Linda', 'Fairchild', 'linda.fairchild@example.test', null, 1, [1, 2, 3, 4, 5, 6, 7, 8], "I am not sure what I need yet, so I checked everything. My husband controls all of our money and I do not have a car. He reads my texts, so please email instead of calling or texting. I can usually check email during my lunch break at work between noon and one. I have been thinking about leaving for a long time and I want to understand what my options are before I do anything. Thank you for having this form."],
        ['2026-09-08 14:14:00', 'J', null, 'j.anon@example.test', null, null, [8], 'Something else - I want to talk to someone about whether what is happening counts as abuse.'],
        ['2026-08-30 08:41:00', 'Rosa', 'Medina', null, '+1 423 555 0194', 2, [2], null],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalSubmissionSeeder only runs when APP_ENV=local.');
        }

        $staffEmails = array_unique(array_filter(array_column(self::FEEDBACK, 4)));
        $staff = User::whereIn('email', $staffEmails)->get()->keyBy('email');
        $missing = array_diff($staffEmails, $staff->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException(
                'LocalSubmissionSeeder needs '.implode(' and ', $missing)
                .'. Run LocalStaffUserSeeder first: php artisan db:seed --class=LocalStaffUserSeeder --database=Portal'
            );
        }

        foreach (self::FEEDBACK as [$submitted, $serviceId, $rating, $comment, $enteredBy]) {
            $exists = ServiceFeedback::where('SubmissionDate', $submitted)
                ->where('ServiceID', $serviceId)
                ->where('Rating', $rating)
                ->exists();

            if ($exists) {
                continue;
            }

            $data = ['ServiceID' => $serviceId, 'Rating' => $rating, 'Comment' => $comment];

            if ($enteredBy !== null) {
                ServiceFeedback::recordStaffEntry([...$data, 'SubmissionDate' => $submitted], $staff[$enteredBy]);

                continue;
            }

            // What the public endpoint stores, with the date set rather than
            // left to the column's useCurrent().
            (new ServiceFeedback)->forceFill([
                ...$data,
                'SubmissionDate' => $submitted,
                'Source' => ServiceFeedback::SOURCE_ONLINE,
            ])->save();
        }

        foreach (self::REQUESTS as [$submitted, $first, $last, $email, $phone, $countyId, $resourceIds, $message]) {
            $exists = ResourceRequestForm::where('SubmissionDate', $submitted)
                ->where('FirstName', $first)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::connection('Feedback')->transaction(function () use ($submitted, $first, $last, $email, $phone, $countyId, $resourceIds, $message): void {
                $request = (new ResourceRequestForm)->forceFill([
                    'FirstName' => $first,
                    'LastName' => $last,
                    'EmailAddress' => $email,
                    'SafePhoneNumber' => $phone,
                    'CountyID' => $countyId,
                    'Message' => $message,
                    'SubmissionDate' => $submitted,
                ]);

                $request->save();
                $request->resourceTypes()->attach($resourceIds);
            });
        }
    }
}
