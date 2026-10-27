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
        $contacts = Contact::query()
            ->search($request->query('q'))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('email') === 'with', fn ($q) => $q->whereNotNull('email'))
            ->when($request->query('email') === 'without', fn ($q) => $q->whereNull('email'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Contact::count(),
            'with_email' => Contact::whereNotNull('email')->count(),
            'without_email' => Contact::whereNull('email')->count(),
            'imports' => ContactImport::count(),
        ];

        return view('contacts.index', [
            'contacts' => $contacts,
            'stats' => $stats,
            'statuses' => Contact::STATUSES,
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
