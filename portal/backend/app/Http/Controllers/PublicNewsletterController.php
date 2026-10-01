<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicNewsletterResource;
use App\Models\Newsletter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/*
GET /api/public/newsletters - the published newsletter list for the website.

Same arrangement as PublicEventController: the restricted `ContentPublic`
connection, no pagination, newest issue first. No join is needed here, since a
newsletter has no lookup relation - the stored file is reached by id through
Newsletter::fileUrl().
*/
class PublicNewsletterController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $newsletters = Newsletter::on('ContentPublic')
            ->where('is_published', true)
            ->orderByDesc('issue_date')
            ->get();

        return PublicNewsletterResource::collection($newsletters);
    }
}
