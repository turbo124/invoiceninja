<?php

namespace App\Http\ValidationRules;


use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('ninja.cloudflare.turnstile.secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        

if ($response->failed()) {

$fail("Captcha failed");

}

        

    }

    public function message()
    {
        return 'The verification failed. Please try again.';
    }
}
