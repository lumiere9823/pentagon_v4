<?php

namespace App\Domains\Forms\Services;

use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\Website;
use Illuminate\Validation\ValidationException;

class FormSubmissionService
{
    public const MAX_PAYLOAD_BYTES = 65536; // 64 KB

    /**
     * Process a form submission.
     *
     * @param  Website  $website  Target website.
     * @param  string  $formName  Name of the form (e.g. 'contact').
     * @param  array<string, mixed>  $rawPayload  Incoming input array.
     * @param  string|null  $ipAddress  Client IP address.
     * @param  string|null  $userAgent  Client User Agent string.
     * @param  Page|null  $page  Optional associated page.
     * @return array{is_spam: bool, submission: FormSubmission|null}
     *
     * @throws ValidationException
     */
    public function submit(
        Website $website,
        string $formName,
        array $rawPayload,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?Page $page = null,
    ): array {
        // 1. Honeypot Verification: Thwart automated bots
        $honeypot = $rawPayload['_hp_verification'] ?? null;
        if (! empty($honeypot)) {
            // Silently drop spam submission without persisting to DB
            return [
                'is_spam' => true,
                'submission' => null,
            ];
        }

        // 2. Normalize and prune internal keys (starting with _)
        $cleanPayload = [];
        foreach ($rawPayload as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue;
            }
            $cleanPayload[$key] = $this->sanitizeValue($value);
        }

        if (empty($cleanPayload)) {
            throw ValidationException::withMessages([
                'form' => 'The submission payload cannot be empty.',
            ]);
        }

        // 3. Payload size check (< 64KB)
        $encoded = json_encode($cleanPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw ValidationException::withMessages([
                'payload' => 'Submission payload exceeds maximum permitted size of 64KB.',
            ]);
        }

        // 4. Sanitize form name
        $sanitizedFormName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $formName) ?: 'contact';
        if (strlen($sanitizedFormName) > 64) {
            $sanitizedFormName = substr($sanitizedFormName, 0, 64);
        }

        // 5. Persist submission
        /** @var FormSubmission $submission */
        $submission = FormSubmission::create([
            'website_id' => $website->id,
            'page_id' => $page?->id,
            'form_name' => $sanitizedFormName,
            'payload' => $cleanPayload,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
            'status' => 'new',
        ]);

        return [
            'is_spam' => false,
            'submission' => $submission,
        ];
    }

    /**
     * Recursively sanitize values to strip raw HTML tags and trim strings.
     */
    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $k => $v) {
                $sanitized[$k] = $this->sanitizeValue($v);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            $withoutScripts = preg_replace('/<(script|style)\b[^>]*>(.*?)<\/\1>/is', '', $value) ?? $value;

            return trim(strip_tags($withoutScripts));
        }

        return $value;
    }
}
