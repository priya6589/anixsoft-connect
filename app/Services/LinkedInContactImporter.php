<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports a LinkedIn "Connections.csv" export (or any CSV with the same headers).
 *
 * LinkedIn puts a few "Notes:" lines above the real header, so the header row is
 * detected by looking for the "First Name" column. Existing contacts are matched by
 * LinkedIn URL, then email, so re-uploading a newer export updates instead of duplicating.
 */
class LinkedInContactImporter
{
    /**
     * CSV header (lowercased) => contacts column.
     */
    private const COLUMN_MAP = [
        'first name' => 'first_name',
        'last name' => 'last_name',
        'url' => 'linkedin_url',
        'profile url' => 'linkedin_url',
        'linkedin url' => 'linkedin_url',
        'email address' => 'email',
        'email' => 'email',
        'company' => 'company',
        'position' => 'position',
        'connected on' => 'connected_on',
    ];

    private const HEADER_SCAN_LINES = 20;

    public function import(string $path, string $originalFilename): ContactImport
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        try {
            $columns = $this->readHeader($handle);
            $rows = $this->readRows($handle, $columns);
        } finally {
            fclose($handle);
        }

        return DB::transaction(fn () => $this->save($rows, $originalFilename));
    }

    /**
     * @return array<int, string> CSV column index => contacts column
     */
    private function readHeader($handle): array
    {
        for ($line = 0; $line < self::HEADER_SCAN_LINES && ($row = fgetcsv($handle, escape: '')) !== false; $line++) {
            $columns = [];

            foreach ($row as $index => $heading) {
                $heading = strtolower(trim($this->stripBom((string) $heading)));

                if (isset(self::COLUMN_MAP[$heading])) {
                    $columns[$index] = self::COLUMN_MAP[$heading];
                }
            }

            if (in_array('first_name', $columns, true)) {
                return $columns;
            }
        }

        throw new RuntimeException('This does not look like a LinkedIn connections export: no "First Name" column was found.');
    }

    /**
     * @param  array<int, string>  $columns
     * @return list<array<string, mixed>>
     */
    private function readRows($handle, array $columns): array
    {
        $rows = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $data = array_fill_keys(array_unique($columns), null);

            foreach ($columns as $index => $column) {
                $value = trim((string) ($row[$index] ?? ''));
                $data[$column] ??= $value === '' ? null : $value;
            }

            $rows[] = $this->normalize($data);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        if ($data['linkedin_url'] ?? null) {
            $url = preg_replace('#^http://#i', 'https://', $data['linkedin_url']);
            $data['linkedin_url'] = rtrim(strtolower($url), '/');
        }

        if ($data['email'] ?? null) {
            $email = strtolower($data['email']);
            $data['email'] = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        if ($data['connected_on'] ?? null) {
            $data['connected_on'] = $this->parseDate($data['connected_on']);
        }

        foreach (['first_name', 'last_name', 'company', 'position'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = mb_substr($data[$field], 0, 255);
            }
        }

        return $data;
    }

    private function parseDate(string $value): ?string
    {
        foreach (['d M Y', 'j M Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);

                if ($date !== false && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // Try the next format.
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function save(array $rows, string $originalFilename): ContactImport
    {
        $import = ContactImport::create([
            'original_filename' => $originalFilename,
            'total_rows' => count($rows),
        ]);

        // Load existing keys once instead of querying per row.
        $byUrl = Contact::whereNotNull('linkedin_url')->pluck('id', 'linkedin_url')->all();
        $byEmail = Contact::whereNotNull('email')->pluck('id', 'email')->all();

        $created = $updated = $skipped = 0;

        foreach ($rows as $row) {
            if (blank($row['first_name'] ?? null) && blank($row['last_name'] ?? null)) {
                $skipped++;

                continue;
            }

            $id = $byUrl[$row['linkedin_url'] ?? ''] ?? $byEmail[$row['email'] ?? ''] ?? null;

            if ($id) {
                $contact = Contact::find($id);
                // Never wipe data we already have (e.g. an email found later) with blanks.
                $contact->fill(array_filter($row, fn ($value) => $value !== null));

                if ($contact->isDirty('email')) {
                    $contact->email_source = 'linkedin';
                }

                if ($contact->isDirty()) {
                    $contact->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                $contact = $import->contacts()->create($row + [
                    'email_source' => ($row['email'] ?? null) ? 'linkedin' : null,
                    'status' => Contact::STATUS_IMPORTED,
                ]);
                $created++;
            }

            if ($contact->linkedin_url) {
                $byUrl[$contact->linkedin_url] = $contact->id;
            }

            if ($contact->email) {
                $byEmail[$contact->email] = $contact->id;
            }
        }

        $import->update([
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => $skipped,
        ]);

        return $import;
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }
}
