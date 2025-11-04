<?php
declare(strict_types=1);

namespace blasto333\Tests;

use PHPUnit\Framework\TestCase;
use blasto333\DriversLicenseParser;

final class DriversLicenseParserTest extends TestCase
{
    public function testParsesTypicalPayload(): void
    {
        $raw = "ANSI 636026080102DLDAQS1234567\nDCSDOE\nDACJOHN\nDADQ\nDBD05202023\nDBB19900102\nDBC1\nDAG123 MAIN ST\nDAHAPT 2\nDAIROCHESTER\nDAJNY\nDAK146092341\nDCGUSA\n";

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('JOHN', $parsed['first_name']);
        $this->assertSame('DOE', $parsed['last_name']);
        $this->assertSame('123 MAIN ST', $parsed['address_1']);
        $this->assertSame('APT 2', $parsed['address_2']);
        $this->assertSame('ROCHESTER', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('14609-2341', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('S1234567', $parsed['license_number']);
        $this->assertSame('1990-01-02', $parsed['dob_iso']);
    }

    public function testNonDlTextReturnsNulls(): void
    {
        $parsed = DriversLicenseParser::parse(\"just a random string that is not a DL\");
        $this->assertNull($parsed['first_name']);
        $this->assertNull($parsed['license_number']);
        $this->assertNull($parsed['dob_iso']);
    }
}
