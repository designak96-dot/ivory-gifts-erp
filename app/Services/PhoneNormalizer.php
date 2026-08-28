<?php
namespace App\Services;
use InvalidArgumentException;
class PhoneNormalizer
{
    public function normalize(?string $phone): ?string
    {
        if (!$phone) return null;
        $number=preg_replace('/\D+/', '', $phone);
        if (str_starts_with($number,'00')) $number=substr($number,2);
        if (str_starts_with($number,'0') && strlen($number)===10) $number='971'.substr($number,1);
        // Same UAE-local heuristic as the line above, for the case where
        // the leading 0 was simply omitted rather than typed — a 9-digit
        // number starting with 5 (05X mobile prefixes minus the 0) is
        // essentially unambiguous as a UAE mobile number in this
        // context, and this exact format was explicitly required to
        // match its +971-prefixed equivalent for search and duplicate
        // detection.
        if (preg_match('/^5\d{8}$/', $number)) $number='971'.$number;
        if (strlen($number)<8 || strlen($number)>15) throw new InvalidArgumentException('Enter a valid international phone number including country code.');
        // A number that still starts with "0" after the checks above is
        // never valid in E.164 format — no country code begins with 0.
        // This is exactly the shape of a real malformed value seen in
        // production (an 11-digit local-looking number with a typo, which
        // doesn't match the specific 10-digit UAE pattern this class
        // auto-corrects) — previously fell through and was silently
        // stored as "+0...". Now rejected with a clear, actionable error
        // instead of a malformed number reaching the database.
        if (str_starts_with($number,'0')) {
            throw new InvalidArgumentException('This does not look like a valid phone number — please check the digit count and include the country code (e.g. 971 for UAE) if dialling internationally.');
        }
        return '+'.$number;
    }
}
