<?php

namespace App\Models;

/*
A scan of a service-feedback form, bytes and all, attached when an admin or
secretary fills the form in on the panel - typically one that arrived on paper
(ServiceFeedback::recordStaffEntry()).

Everything about storing and reading the bytes is inherited from ContentFile -
the global scope that keeps `contents` out of every multi-row query, contents()
for the bytes of one row, storeUpload()/store() - so read that class first.
Only where the rows live differs:

  - On `Feedback`, beside the feedback itself, not in `content_files`. That
    table backs the public website's content, and its staff route
    (/files/{file}) serves any file to anyone who can open the panel, officers
    included, with public caching. A handwritten form from a member of the
    public gets neither: scans are served only by FeedbackScanController, to
    admins and secretaries, uncached.
  - FeedbackPublic, the website's restricted user, has no grant on this table,
    so nothing on the public side can read a scan back.

A scan is deleted with the entry it belongs to (ServiceFeedback's deleted
hook), so removing a mistyped paper entry removes its scan too.
*/
class FeedbackScan extends ContentFile
{
    protected $connection = 'Feedback';

    protected $table = 'feedback_scans';
}
