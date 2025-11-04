# blasto333/drivers-license-parser

A tiny, dependency‑free PHP library to parse AAMVA‑style driver’s license data (PDF417, magstripe dumps, or pasted text).  
Single public API: `DriversLicenseParser::parse(?string $input): array`

## Install

```bash
composer require blasto333/drivers-license-parser
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use blasto333\DriversLicenseParser;

$raw = "ANSI 636026080102DLDAQS1234567DCSDOE DACJOHN DADQ DBD05202023 DBB19900102 DBC1
DAG123 MAIN ST DAHAPT 2 DAIROCHESTER DAJNY DAK146092341 DCGUSA";

$parsed = DriversLicenseParser::parse($raw);

/*
$parsed = [
  'first_name'     => 'JOHN',
  'last_name'      => 'DOE',
  'address_1'      => '123 MAIN ST',
  'address_2'      => 'APT 2',
  'city'           => 'ROCHESTER',
  'state'          => 'NY',
  'zip'            => '14609-2341',
  'country'        => 'USA',
  'license_number' => 'S1234567',
  'dob_iso'        => '1990-01-02',
];
*/
```

### What it does

- Normalizes weird whitespace/control characters often found in PDF417 scans.
- Extracts common AAMVA fields: name, address, city/state/zip/country, license number, DOB.
- Handles `DAA` full-name and splits `LAST, FIRST MIDDLE` or `FIRST MIDDLE LAST` forms.
- Accepts 6- or 8-digit birthdates and sensible format permutations.

### What it doesn’t do

- Validation against state‑specific formats.
- Imaging/decoding barcodes (expect raw text input).

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Versioning & PHP support

- PHP >= 7.3
- Semantic-ish versioning

## License

MIT