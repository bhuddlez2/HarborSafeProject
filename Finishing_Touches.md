# Finishing Touches

Small fixes and polish left to do before hand-off. Each item names where it is, what needs doing, and who owns it.

> **The live list is the [Harbor Safe Finishing Touches tracker](https://claude.ai/artifact/AzScPxrANpHLKwGXw8VL4o).** Add items and update statuses there, not in this file. This file is a snapshot of the tracker, refreshed now and then (last refreshed 2026-10-27), so it may be behind.

**Columns**

- **Location**: the screen, page or file where the change goes (for example "Staff panel → Follow-ups" or `website/frontend/src/app/assessment/`).
- **Item**: what needs to change, in one or two sentences.
- **Responsible**: the person who will make the change.
- **Status**: `Open` while the item is waiting, `In progress` once someone starts, `Done` when it is finished. Finished items stay in the list, so it also records what was done.

Numbers never change once given, so a removed item leaves a gap (item 3 was replaced by a more specific item).

| # | Location | Item | Responsible | Status |
|---|----------|------|-------------|--------|
| 1 | `portal/backend/app/Services/AssessmentPdf.php`, `portal/backend/app/Support/RecordPdf.php`, `portal/backend/app/Services/ServiceFeedbackExport.php` | Change PDF export so it no longer uses Browsershot, which the shared hosting server does not support. | Beau | Done |
| 2 | Staff panel → Logs (`/logs`), every admin role that sees it | Merge the Access log and Change log tabs into one tab. Move the Change log's filters into the new tab, keeping their current style: folded behind the Filters button at the top of the page. Keep the Show dropdown and the search bar above the list. | Connor | Done |
| 4 | Account page (`EditProfile`) for every role: the Account tab for officers and police admins, and the profile link for super admins, admins and secretaries | Lock the Account page so no one can change their own details, including first name, last name and email address. The only things left editable are the password (every role) and the badge number (officers only). The password field will later become an emailed link, once email is set up. | Connor | Done |
| 5 | Officer Portal → Home (`/police`), the landing page for officers and police admins | Make the icons smaller so the whole page fits on one screen, and confirm it at iPhone SE size in the browser's dev tools. Make the Start New Lethality Assessment card dark purple. On the police admin page, make the other two cards (View Assessments and Personnel Management) light purple with a purple outline. On the officer page, change the white "looking for a past assessment" box to the same light purple as the police admin's assessment card. | Tyler | Done |
| 6 | Officer Portal → New assessment (the officer assessment form) | Replace the calendar pop-out on date inputs with something easier to use on a phone. | Tyler | In progress |
| 7 | Officer Portal → New assessment (the officer assessment form) | Remove the "immediate danger, call 911" notice, since the people using this form are the police. Reformat the National Domestic Violence Hotline (NDVH) and 988 entries. | Tyler | Done |
| 8 | Officer Portal → New assessment (the officer assessment form) | Add a Close button next to the Start New Assessment button, so users can go straight back to their Home page. | Tyler | Done |
| 9 | Staff panel → every assessment list (My Assessments, Assessment review, Civilian assessments) and every content management table | Make the tables easier to use on a phone: show fewer columns and simplify the layout. | Ashley | Open |
| 10 | Staff panel → landing page for super admins, admins and secretaries (`App\Filament\StaffLanding`) | Build a real landing (home) page for these users. They currently land on the Follow-ups queue after signing in. | Ashley | Open |
| 11 | Website → home page (`website/frontend`), the "We are here to help you" header section | On narrow screens the header text and the rest of the section sit off to one side, with only the grey and black image filling the other side, so it looks unbalanced. Add another picture to balance it, centre the header, or both. | Trevor | Open |
| 12 | Website → footer (every page) | Everything in the footer is left-aligned. Right-align some of it so the footer looks evenly spread across the page. | Trevor | Open |
| 13 | Staff panel → Civilian assessments (`/civilian-assessments`) | Add filters to the civilian assessment list that match the ones on the police assessment tables (My Assessments and Assessment review). | Ashley | Done |
| 14 | Database (`portal/backend`) | Hash sensitive data before it is saved to the database. | Ashley | Open |
| 15 | Staff panel → Content → Events → Create event (`EventResource`, the Description box and the Preview step) | The event description has no length limit, so the preview can run on without end. Cap the description at 500 characters. | Ashley | Done |
| 16 | Website → Events & News page, event cards | Check that an event card still looks right when its description is very long. | Trevor | Open |
| 17 | Website → civilian assessment (`website/frontend/src/app/assessment/`) | The form accepts impossible dates. Reject them on the page and on the server (`CivilianAssessmentStoreRequest`). | Ashley | Done |
| 18 | Website → civilian assessment (`website/frontend/src/app/assessment/`), the Confidentiality Notice | After going Back and then Forward in the browser, the notice's "I understand, continue" button stops working. The notice can't be closed any other way, so the visitor is stuck. | Trevor | Open |
| 19 | Website → Esc key Safe Exit (every page) | Check the Esc key's Safe Exit, which should leave the site entirely. | Trevor | Open |
| 20 | Website → footer (every page) | Connect the footer's social media links to Harbor Safe's social media accounts. | Trevor | Open |
| 21 | Website → Events & News page | When there are no events, the page shows no message under the heading. Add a short message such as "Check back soon". | Trevor | Open |
| 22 | Staff panel → resource requests: Submissions → Resource requests View pop-up and the request's follow-up page (`resource-request-record.blade.php`) | Make the requester's email address a link that opens a new email to them, so staff can reply more easily. | Ashley | Done |
| 23 | Password setup | Make passwords meet a minimum standard (for example a special symbol, a number and a capital letter). | Ashley | Done |
