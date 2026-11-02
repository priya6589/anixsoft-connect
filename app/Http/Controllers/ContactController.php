<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Services\LinkedInContactImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $status = $this->filter($request, 'status', array_keys(Contact::STATUSES));
        $category = $this->filter($request, 'category', [...array_keys(Contact::CATEGORIES), 'unsorted']);
        // "with" / "without" an email, or an email lookup result such as "found" or "not_found".
        $email = $this->filter($request, 'email', ['with', 'without', ...array_keys(Contact::EMAIL_STATUSES)]);

        $contacts = Contact::query()
            ->search($this->searchTerm($request))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($category === 'unsorted', fn ($q) => $q->whereNull('classified_at'))
            ->when($category && $category !== 'unsorted', fn ($q) => $q->where('category', $category))
            ->when($email === 'with', fn ($q) => $q->whereNotNull('email'))
            ->when($email === 'without', fn ($q) => $q->whereNull('email'))
            ->when($email && isset(Contact::EMAIL_STATUSES[$email]), fn ($q) => $q->where('email_status', $email))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('contacts.index', [
            'contacts' => $contacts,
            'stats' => Contact::stats(),
            'statuses' => Contact::STATUSES,
            'categories' => Contact::CATEGORIES,
            'emailStatuses' => Contact::EMAIL_STATUSES,
            // Only the filters actually applied, so the form never echoes a malformed value.
            'filters' => array_filter(['q' => $this->searchTerm($request), 'status' => $status, 'category' => $category, 'email' => $email]),
        ]);
    }

    public function importForm(): View
    {
        return view('contacts.import', [
            'imports' => ContactImport::latest()->take(10)->get(),
        ]);
    }

    public function import(Request $request, LinkedInContactImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Please upload the LinkedIn Connections.csv file.',
        ]);

        $file = $request->file('file');

        try {
            $import = $importer->import($file->getRealPath(), $file->getClientOriginalName());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('contacts.index')->with('success', sprintf(
            'Import complete: %d new, %d updated, %d skipped/unchanged (out of %d rows).',
            $import->created_count,
            $import->updated_count,
            $import->skipped_count,
            $import->total_rows,
        ));
    }
}
