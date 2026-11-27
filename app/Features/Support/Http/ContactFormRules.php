<?php

namespace App\Features\Support\Http;

use Illuminate\Validation\Rule;

/**
 * Input contract for the public contact form, shared by the mobile/web API and
 * the storefront page so both accept exactly the same payload.
 */
final class ContactFormRules
{
    public const CATEGORIES = ['general', 'account', 'payments', 'subscriptions', 'app_reviews', 'ad_shares', 'vas', 'other'];

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['nullable', 'string', Rule::in(self::CATEGORIES)],
            // Honeypot: rendered hidden in HTML and never filled by a human.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name so we know who we are replying to.',
            'email.required' => 'We need an email address to reply to you.',
            'subject.required' => 'Please add a short subject.',
            'subject.min' => 'Your subject is a little short — a few more words help us route it.',
            'message.min' => 'Please give us a little more detail (at least 10 characters).',
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'subject' => 'subject',
            'message' => 'message',
        ];
    }
}
