<?php

namespace App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages;

use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\ServiceFeedbackResource;
use App\Filament\Concerns\BreadcrumbNamesThePage;
use App\Models\ServiceFeedback;
use App\Services\ServiceFeedbackExport;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
Online feedback arrives from the public form. The one create action here,
"New feedback form", lets an admin or secretary fill a form in themselves -
typically one that arrived on paper - so it sits in the database alongside the
online ones, marked Source = staff (ServiceFeedback::recordStaffEntry()). The
wording deliberately doesn't mention paper: to the person entering it, it is
just a feedback form.

Export (team plan task 34) downloads the list as Excel or PDF for grant
reports: every form the table's applied filters and search leave - all pages,
not just the one on screen - in the table's sort order, one form per row
(ServiceFeedbackExport). The filters are set first, in the table's own filter
menu (date submitted and service, plus the others); the button reads the
table's query rather than taking filters of its own, so the file always
matches the list. The file holds only service, rating and comment - no
filters, dates or names - and exports are not logged.

ListRecords rather than ManageRecords so this stays the only create button -
ManageRecords would add its own. Resource requests have no equivalent: they
come only from the website.
*/
class ListServiceFeedback extends ListRecords
{
    use BreadcrumbNamesThePage;

    protected static string $resource = ServiceFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportExcel')
                    ->label('Excel (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->action(fn (ServiceFeedbackExport $export): ?StreamedResponse => $this->download(
                        'xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        fn (Collection $feedback): string => $export->xlsx($feedback),
                    )),
                Action::make('exportPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->action(fn (ServiceFeedbackExport $export): ?StreamedResponse => $this->download(
                        'pdf',
                        'application/pdf',
                        fn (Collection $feedback): string => $export->pdf($feedback),
                    )),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button(),

            CreateAction::make()
                ->label('New feedback form')
                ->icon('heroicon-o-document-plus')
                ->modalHeading('New feedback form')
                ->modalSubmitActionLabel('Save form')
                ->createAnother(false)
                ->using(fn (array $data): ServiceFeedback => ServiceFeedback::recordStaffEntry($data, Filament::auth()->user()))
                ->successNotificationTitle('Feedback form saved'),
        ];
    }

    // The forms the table shows, across every page, then the file. An empty
    // list downloads nothing and says why.
    private function download(string $extension, string $contentType, \Closure $render): ?StreamedResponse
    {
        $feedback = $this->getFilteredSortedTableQuery()->with('service')->get();

        if ($feedback->isEmpty()) {
            Notification::make()
                ->title('No feedback matches these filters')
                ->body('Change or reset the filters, then export again.')
                ->warning()
                ->send();

            return null;
        }

        $bytes = $render($feedback);
        $name = 'service-feedback-'.now(Timezones::DISPLAY)->format('Y-m-d').'.'.$extension;

        return response()->streamDownload(
            function () use ($bytes): void {
                echo $bytes;
            },
            $name,
            ['Content-Type' => $contentType],
        );
    }
}
