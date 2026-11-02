<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Hunter.io Email Finder + Email Verifier (https://hunter.io/api-documentation/v2).
 *
 * Hunter charges no credit when no email is found. HTTP 451 means the person
 * asked Hunter not to process their data, so they must not be contacted.
 */
class HunterEmailFinder
{
    private const BASE_URL = 'https://api.hunter.io/v2/';

    public function isConfigured(): bool
    {
        return filled(config('outreach.hunter_key'));
    }

    /**
     * @return array{email: ?string, status: ?string}|null null when nothing was found
     */
    public function find(Contact $contact): ?array
    {
        $name = $contact->first_name && $contact->last_name
            ? ['first_name' => $contact->first_name, 'last_name' => $contact->last_name]
            : ['full_name' => $contact->full_name];

        $response = $this->request()->get('email-finder', $name + [
            'company' => $contact->company,
            'max_duration' => 15,
        ]);

        if ($response->status() === 451) {
            return ['email' => null, 'status' => 'opted_out'];
        }

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        $email = $response->json('data.email');

        return $email
            ? ['email' => strtolower($email), 'status' => $response->json('data.verification.status')]
            : null;
    }

    /**
     * Hunter verifier status (valid, invalid, accept_all, webmail, disposable, unknown,
     * or opted_out for 451), or null while Hunter is still checking (HTTP 202).
     */
    public function verify(string $email): ?string
    {
        $response = $this->request()->get('email-verifier', ['email' => $email]);

        return match ($response->status()) {
            202 => null,
            222 => 'unknown',
            451 => 'opted_out',
            default => $response->throw()->json('data.status', 'unknown'),
        };
    }

    /**
     * Searches left this billing period, cached for 10 minutes. Null if unknown.
     */
    public function remainingSearches(): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember('hunter.remaining_searches', 600, function () {
            // Shown on the dashboard, so never let a slow or down Hunter break the page.
            try {
                $response = $this->request()->connectTimeout(3)->timeout(5)->get('account');
            } catch (ConnectionException) {
                return null;
            }

            if ($response->failed()) {
                return null;
            }

            $remaining = $response->json('data.requests.credits.remaining')
                ?? $response->json('data.requests.searches.remaining');

            return $remaining === null ? null : (int) $remaining;
        });
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withHeaders(['X-API-KEY' => config('outreach.hunter_key')])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(40);
    }
}
