<?php
declare(strict_types=1);

namespace blasto333;

final class DriversLicenseParser
{
    /**
     * Single public API: parse raw DL string into normalized fields.
     * Returns an associative array with keys:
     * first_name, last_name, address_1, address_2, city, state, zip, country, license_number, dob_iso
     */
    public static function parse(?string $input): array
    {
        $result = [
            'first_name' => null,
            'last_name' => null,
            'address_1' => null,
            'address_2' => null,
            'city' => null,
            'state' => null,
            'zip' => null,
            'country' => null,
            'license_number' => null,
            'dob_iso' => null,
        ];

        if ($input === null) {
            return FALSE;
        }
        $input = trim((string)$input);
        if ($input === '') {
            return FALSE;
        }
		
	    $indicators = ['ANSI', 'DAQ', 'DL', '@', 'DBB', 'D8', 'DCS', 'DAG', 'DAA'];
	    $looks_like_dl = false;
	    foreach ($indicators as $i) {
	        if (stripos($input, $i) !== false) {
	            $looks_like_dl = true;
	            break;
	        }
	    }

	    if (!$looks_like_dl) 
		{
	        return false;
	    }
		

        // Normalize lines and separators
        $input = preg_replace("/\r\n?/", "\n", $input);
        $input = preg_replace('~[\x{2028}\x{2029}\x{0085}]~u', "\n", $input);
        $input = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', "\n", $input);
        $input = preg_replace('~[\t \x{00A0}\x{200B}]+(?=[DZ][A-Z]{2})~u', "\n", $input);
        $input = preg_replace("/\n+/", "\n", $input);

        // Soft detection that it's likely DL text; if not, bail quietly with null fields.
        $indicators = ['ANSI', 'DAQ', 'DL', '@', 'DBB', 'D8', 'DCS', 'DAG', 'DAA'];
        $looks_like_dl = false;
        foreach ($indicators as $i) {
            if (stripos($input, $i) !== false) { $looks_like_dl = true; break; }
        }
        if (!$looks_like_dl) {
            return $result;
        }

        $first_name  = self::extractField($input, ['DAC', 'DCT']);
        $middle_name = self::extractField($input, ['DAD']);
        $last_name   = self::extractField($input, ['DCS', 'DAB']);
        $full_name   = self::extractField($input, ['DAA']);

        if ($first_name && strpos($first_name, ' ') !== false && !$middle_name) {
            $parts = preg_split('/\s+/', $first_name);
            $first_name = array_shift($parts);
            if (!empty($parts)) {
                $middle_name = implode(' ', $parts);
            }
        }

        if ($full_name) {
            $parsed = self::splitFullName($full_name);
            if (!$last_name && isset($parsed['last_name']))   { $last_name   = $parsed['last_name']; }
            if (!$first_name && isset($parsed['first_name'])) { $first_name  = $parsed['first_name']; }
            if (!$middle_name && isset($parsed['middle_name'])) { $middle_name = $parsed['middle_name']; }
        }

        if (!$middle_name) {
            $given = self::extractField($input, ['DCT']);
            if ($given) {
                $parts = preg_split('/\s+/', $given);
                if (!$first_name && !empty($parts)) {
                    $first_name = array_shift($parts);
                }
                if (!empty($parts)) {
                    $middle_name = implode(' ', $parts);
                }
            }
        }

        $result['first_name'] = self::normalizeText($first_name);
        $result['last_name']  = self::normalizeText($last_name);
        $result['address_1']  = self::normalizeText(self::extractField($input, ['DAG']));
        $result['address_2']  = self::normalizeText(self::extractField($input, ['DAH']));
        $result['city']       = self::normalizeText(self::extractField($input, ['DAI']));
        $result['state']      = self::normalizeState(self::extractField($input, ['DAJ']));
        $result['zip']        = self::normalizeZip(self::extractField($input, ['DAK']));

        $country = self::extractField($input, ['DCG']);
        if ($country) {
            $country = strtoupper($country);
            if (preg_match('/^([A-Z]{2,3})(?=[DZ][A-Z]{2})/', $country, $m)) {
                $country = $m[1];
            }
        }
        $result['country'] = $country ?: null;

        $license_number = self::extractField($input, ['DAQ']);
        $result['license_number'] = self::normalizeLicenseNumber($license_number);

        $dob_raw = self::extractField($input, ['DBB', 'D8', 'D8A']);
        $dob_iso = self::normalizeDobDigits($dob_raw);
        if (!$dob_iso && $dob_raw) {
            $ts = @strtotime($dob_raw);
            if ($ts !== false) {
                $dob_iso = date('Y-m-d', $ts);
            }
        }
        $result['dob_iso'] = $dob_iso;

        return $result;
    }

    // ----------------- Helpers (private) ----------------- //

    private static function normalizeText($value)
    {
        if ($value === null) return null;
        if (!is_string($value)) return null;

        $value = str_replace(
            ["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\xA8", "\xE2\x80\xA9", "\xC2\x85"],
            ' ',
            $value
        );
        $value = preg_replace('~[\x00-\x1F\x7F]~', '', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        $value = rtrim($value, '.');

        return $value === '' ? null : $value;
    }

    private static function normalizeZip($zip)
    {
        $zip = self::normalizeText($zip);
        if ($zip === null) return null;

        if (preg_match('/^[0-9]{9}$/', $zip)) {
            return substr($zip, 0, 5) . '-' . substr($zip, 5);
        }
        return $zip;
    }

    private static function normalizeState($state)
    {
        $state = self::normalizeText($state);
        if ($state === null) return null;

        $state = strtoupper($state);
        if (strlen($state) > 2 && preg_match('/^([A-Z]{2})(?=(?:DA|DB|DC|DD|D8|Z[A-Z]))/', $state, $m)) {
            $state = $m[1];
        }
        return $state;
    }

    private static function normalizeLicenseNumber($license_number)
    {
        $license_number = self::normalizeText($license_number);
        if ($license_number === null) return null;

        $license_number = strtoupper($license_number);
        $trailing_codes = [
            'DCF','DCG','DCH','DCI','DCJ','DCK','DCL','DCM','DCN','DCO','DCP','DCQ','DCR',
            'DDA','DDB','DDC','DDD','DDE','DDF','DDG','DDH','DDI','DDJ','DDK','DDL','DDM',
            'DDN','DDO','DDP','DDQ','DDR','DDS','DDT','DDU','DDV','DDW','DDX','DDY','DDZ',
            'DAU','DAV','DAW','DAY','DAZ','ZNB','ZNC','ZND','ZNE','ZNF','ZNG','ZNH','ZNI',
        ];
        foreach ($trailing_codes as $code) {
            $pos = strpos($license_number, $code);
            if ($pos === false) continue;

            $suffix = substr($license_number, $pos + strlen($code));
            if ($suffix === '') continue;
            if (strlen($suffix) < 3) continue;

            $has_digit = preg_match('/[0-9]/', $suffix);
            $has_next  = preg_match('/(?:D|Z)[A-Z]{2}/', $suffix);
            if (!$has_digit && strpos($suffix, '@') === false && !$has_next) continue;

            $license_number = rtrim(substr($license_number, 0, $pos));
            break;
        }
        return $license_number === '' ? null : $license_number;
    }

    private static function splitFullName($full_name)
    {
        $full_name = self::normalizeText($full_name);
        $out = [];
        if ($full_name === null) return $out;

        if (strpos($full_name, ',') !== false) {
            $parts = explode(',', $full_name, 2);
            $last  = self::normalizeText($parts[0]);
            $rest  = isset($parts[1]) ? self::normalizeText($parts[1]) : null;
            if ($last !== null) $out['last_name'] = $last;

            if ($rest !== null) {
                $bits = preg_split('/\s+/', $rest);
                if (!empty($bits)) {
                    $first = self::normalizeText(array_shift($bits));
                    if ($first !== null) $out['first_name'] = $first;
                    if (!empty($bits)) {
                        $mid = self::normalizeText(implode(' ', $bits));
                        if ($mid !== null) $out['middle_name'] = $mid;
                    }
                }
            }
        } else {
            $bits = preg_split('/\s+/', $full_name);
            if (!empty($bits)) {
                $first = self::normalizeText(array_shift($bits));
                if ($first !== null) $out['first_name'] = $first;
                if (!empty($bits)) {
                    $last = self::normalizeText(array_pop($bits));
                    if ($last !== null) $out['last_name'] = $last;
                    if (!empty($bits)) {
                        $mid = self::normalizeText(implode(' ', $bits));
                        if ($mid !== null) $out['middle_name'] = $mid;
                    }
                }
            }
        }
        return $out;
    }

    private static function normalizeDobDigits($value)
    {
        if (!$value) return null;
        $digits = preg_replace('/[^0-9]/', '', (string)$value);
        if ($digits === '') return null;

        $len = strlen($digits);
        $candidates = [];

        if ($len === 8) {
            $candidates[] = [substr($digits,0,4), substr($digits,4,2), substr($digits,6,2)];
            $candidates[] = [substr($digits,4,4), substr($digits,0,2), substr($digits,2,2)];
            $candidates[] = [substr($digits,4,4), substr($digits,2,2), substr($digits,0,2)];
        } elseif ($len === 6) {
            $candidates[] = [self::convertTwoDigitYear(substr($digits,0,2)), substr($digits,2,2), substr($digits,4,2)];
            $candidates[] = [self::convertTwoDigitYear(substr($digits,4,2)), substr($digits,0,2), substr($digits,2,2)];
            $candidates[] = [self::convertTwoDigitYear(substr($digits,2,2)), substr($digits,0,2), substr($digits,4,2)];
        } else {
            return null;
        }

        foreach ($candidates as $c) {
            $y = (int)$c[0]; $m = (int)$c[1]; $d = (int)$c[2];
            if ($y > 0 && $m > 0 && $d > 0 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
        return null;
    }

    private static function convertTwoDigitYear($yy)
    {
        $y = (int)$yy;
        if ($y >= 0 && $y <= 30) return 2000 + $y;
        if ($y >= 31 && $y <= 99) return 1900 + $y;
        return $y;
    }

    private static function trimConcatenated($v)
    {
        if ($v === null) return null;
        if (!is_string($v)) return $v;
        return $v === '' ? null : $v;
    }

    private static function extractField($input, array $codes)
    {
        if (!$codes) return null;

        $normalized = str_replace(["\r\n", "\r"], "\n", $input);
        $normalized = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9", "\xC2\x85"], "\n", $normalized);
        $normalized = str_replace(["\t", "\xC2\xA0", "\xE2\x80\x8b"], ' ', $normalized);
        $normalized = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]~', "\n", $normalized);
        $normalized = preg_replace("/\n+/", "\n", $normalized);

        foreach ($codes as $code) {
            $line = '/^\s*' . preg_quote($code, '/') . '([^\n]*)$/m';
            if (preg_match($line, $normalized, $m)) {
                $value = self::trimConcatenated(self::normalizeText($m[1]));
                if ($value !== null) return $value;
            }

            $inline = '/' . preg_quote($code, '/') . '([^\r\n]*)(?=(?:\r?\n|\s*[DZ][A-Z]{2}))/';
            if (preg_match($inline, $normalized, $m)) {
                $value = self::trimConcatenated(self::normalizeText($m[1]));
                if ($value !== null) return $value;
            }
        }
        return null;
    }
}
