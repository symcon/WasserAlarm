<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class WasserAlarmValidationTest extends TestCaseSymconValidation
{
    public function testValidateWasserAlarm(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateWasserAlarmModule(): void
    {
        $this->validateModule(__DIR__ . '/../WasserAlarm');
    }
}