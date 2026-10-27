<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    private function linkedInCsv(array $rows): UploadedFile
    {
        $csv = "\xEF\xBB\xBFNotes:\n"
            ."\"When exporting your connection data, you may notice that some of the email addresses are missing.\"\n"
            ."\n"
            ."First Name,Last Name,URL,Email Address,Company,Position,Connected On\n";

        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\n";
        }

        return UploadedFile::fake()->createWithContent('Connections.csv', $csv);
    }

    public function test_pages_render(): void
    {
        $this->get(route('contacts.index'))->assertOk()->assertSee('No contacts yet');
        $this->get(route('contacts.import.form'))->assertOk()->assertSee('Import LinkedIn contacts');
    }

    public function test_linkedin_export_is_imported(): void
    {
        $file = $this->linkedInCsv([
            ['Asha', 'Verma', 'https://www.linkedin.com/in/asha-verma/', 'Asha@Example.com', 'Acme Ltd', 'CTO', '27 Oct 2026'],
            ['Rahul', 'Singh', 'https://www.linkedin.com/in/rahul-singh', '', 'Beta, Inc.', 'Founder', '01 Jan 2025'],
            ['', '', '', '', '', '', ''],
        ]);

        $this->post(route('contacts.import'), ['file' => $file])
            ->assertRedirect(route('contacts.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, Contact::count());

        $asha = Contact::where('first_name', 'Asha')->first();
        $this->assertSame('asha@example.com', $asha->email);
        $this->assertSame('linkedin', $asha->email_source);
        $this->assertSame('https://www.linkedin.com/in/asha-verma', $asha->linkedin_url);
        $this->assertSame('2026-10-27', $asha->connected_on->toDateString());
        $this->assertSame(Contact::STATUS_IMPORTED, $asha->status);

        $rahul = Contact::where('first_name', 'Rahul')->first();
        $this->assertNull($rahul->email);
        $this->assertSame('Beta, Inc.', $rahul->company);

        $this->get(route('contacts.index', ['q' => 'asha verma']))->assertSee('Acme Ltd')->assertDontSee('Beta, Inc.');
    }

    public function test_reupload_updates_without_duplicates_or_wiping_data(): void
    {
        $this->post(route('contacts.import'), ['file' => $this->linkedInCsv([
            ['Asha', 'Verma', 'https://www.linkedin.com/in/asha-verma', 'asha@example.com', 'Acme Ltd', 'CTO', '27 Oct 2026'],
        ])]);

        $this->post(route('contacts.import'), ['file' => $this->linkedInCsv([
            ['Asha', 'Verma', 'http://www.linkedin.com/in/asha-verma/', '', 'NewCo', 'CEO', '27 Oct 2026'],
        ])])->assertSessionHas('success', fn ($msg) => str_contains($msg, '0 new, 1 updated'));

        $this->assertSame(1, Contact::count());
        $asha = Contact::first();
        $this->assertSame('NewCo', $asha->company);
        $this->assertSame('asha@example.com', $asha->email);
    }

    public function test_non_linkedin_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('random.csv', "foo,bar\n1,2\n");

        $this->post(route('contacts.import'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Contact::count());
    }
}
