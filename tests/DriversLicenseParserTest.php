<?php
declare(strict_types=1);

namespace blasto333\Tests;

use PHPUnit\Framework\TestCase;
use blasto333\DriversLicenseParser;

final class DriversLicenseParserTest extends TestCase
{
    public function testParsesTypicalPayload(): void
    {
        $raw = "ANSI 636026080102DLDAQS0000000\nDCSDOE\nDACJOHN\nDADQ\nDBD05202023\nDBB19900102\nDBC1\nDAG123 MAIN ST\nDAHAPT 2\nDAIROCHESTER\nDAJNY\nDAK146092341\nDCGUSA\n";

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('JOHN', $parsed['first_name']);
        $this->assertSame('DOE', $parsed['last_name']);
        $this->assertSame('123 MAIN ST', $parsed['address_1']);
        $this->assertSame('APT 2', $parsed['address_2']);
        $this->assertSame('ROCHESTER', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('14609-2341', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('S0000000', $parsed['license_number']);
        $this->assertSame('1990-01-02', $parsed['dob_iso']);
    }

    public function testNonDLReturnsFalse(): void
    {
        $parsed = DriversLicenseParser::parse('just a random string that is not a DL');

        $this->assertFalse($parsed);
    }

    public function testDobParsedWhenFieldsConcatenated(): void
    {
        $raw = <<<'DATA'
@ANSI 636001100402EN00410323ZN03640120ENDCAD   DCBNONE      DCDNONE DBA09092031DCSMORGAN                      DACALEX
             DADTAYLOR                   DBD08102023DBB09091986DBC1DAYBRODAU600   DAG42 SAMPLE RD            DAISAMPLETOWN
     DAJNYDAK999990000  DAQ000000001DCFDATACODE01DCGUSADDEUDDFUDDGUDDAFDDB03072022DDD0ZNZNAMORGAN@ALEX@TAYLOR  ZNBEXAMPLEDATAALPHA12345
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('ALEX', $parsed['first_name']);
        $this->assertSame('TAYLOR', $parsed['middle_name']);
        $this->assertSame('MORGAN', $parsed['last_name']);
        $this->assertSame('42 SAMPLE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('SAMPLETOWN', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('99999-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000001', $parsed['license_number']);
        $this->assertSame('1986-09-09', $parsed['dob_iso']);
    }

    public function testDobParsedWhenFieldsConcatenatedWithDifferentName(): void
    {
        $raw = <<<'DATA'
@ANSI 636001100402EN00410323ZN03640120ENDCAD   DCBNONE      DCDNONE DBA09092031DCSCAMPBELL                      DACJORDAN
       DADSAGE                   DBD08102023DBB09091986DBC1DAYBRODAU600   DAG42 SAMPLE RD            DAISAMPLETOWN           DAJNY
    DAK999990000  DAQ000000002DCFDATACODE02DCGUSADDEUDDFUDDGUDDAFDDB03072022DDD0ZNZNACAMPBELL@JORDAN@SAGE  ZNBEXAMPLEDATABETA67890
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('JORDAN', $parsed['first_name']);
        $this->assertSame('SAGE', $parsed['middle_name']);
        $this->assertSame('CAMPBELL', $parsed['last_name']);
        $this->assertSame('42 SAMPLE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('SAMPLETOWN', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('99999-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000002', $parsed['license_number']);
        $this->assertSame('1986-09-09', $parsed['dob_iso']);
    }

    public function testDobParsedWhenMultiLinePayloadHasDelimiters(): void
    {
        $raw = <<<'DATA'
@
ANSI 636001100402EN00410323ZN03640120ENDCAD   
DCBNONE      
DCDNONE 
DBA09092031
DCSREED                      
DACPHOENIX                  
DADQUINN                   
DBD08102023
DBB09091986
DBC1
DAYBRO
DAU506   
DAG42 SAMPLE RD            
DAISAMPLETOWN           
DAJNY
DAK999990000  
DAQ000000003
DCFDATACODE03
DCGUSA
DDEU
DDFU
DDGU
DDAF
DDB03072022
DDD0
ZNZNAREED@PHOENIX@QUINN  
ZNBEXAMPLEDATAGAMMA24680 
@
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('PHOENIX', $parsed['first_name']);
        $this->assertSame('QUINN', $parsed['middle_name']);
        $this->assertSame('REED', $parsed['last_name']);
        $this->assertSame('42 SAMPLE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('SAMPLETOWN', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('99999-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000003', $parsed['license_number']);
        $this->assertSame('1986-09-09', $parsed['dob_iso']);
    }

    public function testDobParsedWhenDelimitedPayloadIncludesPeriods(): void
    {
        $raw = <<<'DATA'
@
ANSI 636001100402DL00410323ZN03640120DLDCAD.  
DCBB.        
DCDNONE 
DBA07162031
DCSRIVERS.                  
DACMORGAN.             
DADK.                       
DBD06122023
DBB07161986
DBC1
DAYHAZ
DAU509.   
DAG21 WEDMORE RD.           
DAIFAIRPORT.            
DAJNY
DAK144500000. 
DAQ000000004
DCFDATACODE04
DCGUSA
DDEU
DDFU
DDGU
DDAN
DDB03072022
DDD0
ZNZNARIVERS@MORGAN@K
ZNBEXAMPLEDATADELTA13579
@
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('MORGAN', $parsed['first_name']);
        $this->assertSame('K', $parsed['middle_name']);
        $this->assertSame('RIVERS', $parsed['last_name']);
        $this->assertSame('21 WEDMORE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('FAIRPORT', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('14450-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000004', $parsed['license_number']);
        $this->assertSame('1986-07-16', $parsed['dob_iso']);
    }

    public function testDobParsedWhenDelimitedPayloadHasDifferentSurname(): void
    {
        $raw = <<<'DATA'
@
ANSI 636001100402DL00410323ZN03640120DLDCAD.  
DCBB.        
DCDNONE 
DBA07162031
DCSCARTER.                  
DACELLIOT.             
DADK.                       
DBD06122023
DBB07161986
DBC1
DAYHAZ
DAU509.   
DAG21 WEDMORE RD.           
DAIFAIRPORT.            
DAJNY
DAK144500000. 
DAQ000000005
DCFDATACODE05
DCGUSA
DDEU
DDFU
DDGU
DDAN
DDB03072022
DDD0
ZNZNACARTER@ELLIOT@K
ZNBEXAMPLEDATAEPSILON97531
@
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('ELLIOT', $parsed['first_name']);
        $this->assertSame('K', $parsed['middle_name']);
        $this->assertSame('CARTER', $parsed['last_name']);
        $this->assertSame('21 WEDMORE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('FAIRPORT', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('14450-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000005', $parsed['license_number']);
        $this->assertSame('1986-07-16', $parsed['dob_iso']);
    }

    public function testDobParsedWhenDelimitedPayloadHasKorzeniewskiSurname(): void
    {
        $raw = <<<'DATA'
@
ANSI 636001100402DL00410323ZN03640120DLDCAD.
DCBB.
DCDNONE 
DBA07162031
DCSKORZENIEWSKI.
DACKYLE.
DADW.
DBD06122023
DBB07161986
DBC1
DAYHAZ
DAU509.
DAG21 WEDMORE RD.
DAIFAIRPORT.
DAJNY
DAK144500000.
DAQ000000006
DCFDATACODE06
DCGUSA
DDEU
DDFU
DDGU
DDAN
DDB03072022
DDD0
ZNZNAKORZENIEWSKI@KYLE@W
ZNBEXAMPLEDATAETA11223
@
DATA;

        $parsed = DriversLicenseParser::parse($raw);

        $this->assertSame('KYLE', $parsed['first_name']);
        $this->assertSame('W', $parsed['middle_name']);
        $this->assertSame('KORZENIEWSKI', $parsed['last_name']);
        $this->assertSame('21 WEDMORE RD', $parsed['address_1']);
        $this->assertNull($parsed['address_2']);
        $this->assertSame('FAIRPORT', $parsed['city']);
        $this->assertSame('NY', $parsed['state']);
        $this->assertSame('14450-0000', $parsed['zip']);
        $this->assertSame('USA', $parsed['country']);
        $this->assertSame('000000006', $parsed['license_number']);
        $this->assertSame('1986-07-16', $parsed['dob_iso']);
    }
}
