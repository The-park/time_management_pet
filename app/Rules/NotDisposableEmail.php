<?php

namespace App\Rules;

use App\Models\DisposableEmailDomain;
use Illuminate\Contracts\Validation\Rule;

class NotDisposableEmail implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        $email = (string) $value;
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        if ($domain === '') {
            return false;
        }

        if (! checkdnsrr($domain, 'MX')) {
            return false;
        }

        return ! DisposableEmailDomain::where('domain', $domain)->exists();
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Temporary or disposable email addresses are not allowed.';
    }
}
