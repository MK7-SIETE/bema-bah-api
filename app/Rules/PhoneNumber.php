<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

// A phone number the way the website checks it: any real number in the world, local Zambian numbers included.
//   "0977 123 456", "977123456" and "+260 977-123-456" are all read as Zambian (the default country).
//   A number that starts with + is read in its own country.
// Use it in a rule list:   'phone' => ['required', 'string', 'max:30', new PhoneNumber],
// Then save the clean form:   $data['phone'] = PhoneNumber::normalize($data['phone']);   // +260977123456
class PhoneNumber implements ValidationRule
{
    public const DEFAULT_REGION = 'ZM';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Please enter a valid phone number.');
        }
    }

    // The number in international form (+260977123456), or null when it is not a real phone number.
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        // Only digits, spaces, - ( ) . and one leading + (no letters: the library would turn 1-800-FLOWERS into digits).
        if ($value === '' || strlen($value) > 40 || ! preg_match('/^\+?[\d\s\-().]+$/', $value)) {
            return null;
        }

        try {
            $util   = PhoneNumberUtil::getInstance();
            $number = $util->parse($value, self::DEFAULT_REGION);

            return $util->isValidNumber($number)
                ? $util->format($number, PhoneNumberFormat::E164)
                : null;
        } catch (NumberParseException) {
            return null;
        }
    }
}
