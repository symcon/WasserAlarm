<?php

declare(strict_types=1);

define('TYPE_WATER', 0);
define('TYPE_ELECTICITY', 1);
define('TYPE_GAS', 2);

class VerbraucherAlarm extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger('MeterID', 0);
        $this->RegisterPropertyInteger('LeakInterval', 1);
        $this->RegisterPropertyInteger('BurstInterval', 5);
        $this->RegisterPropertyInteger('flowType', TYPE_WATER);
        $this->RegisterPropertyInteger('AlarmThresholder', 6);

        //Timer
        $this->RegisterTimer('UpdateLeak', 0, 'WAA_CheckAlert($_IPS[\'TARGET\'], "LeakThreshold", "LeakBuffer");');
        $this->RegisterTimer('UpdateBurst', 0, 'WAA_CheckAlert($_IPS[\'TARGET\'], "BurstThreshold", "BurstBuffer");');

        //Variablenprofile
        //AlertLevel
        if (!IPS_VariableProfileExists('WAA.LeakLevel')) {
            IPS_CreateVariableProfile('WAA.LeakLevel', 1);
            IPS_SetVariableProfileValues('WAA.LeakLevel', 0, 6, 1);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 0, $this->Translate('No activity'), 'IPS', 0x80FF80);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 1, $this->Translate('Everything fine'), 'HollowArrowUp', 0x00FF00);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 2, $this->Translate('Normal activity'), 'HollowDoubleArrowUp', 0x008000);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 3, $this->Translate('High activity'), 'Lightning', 0xFFFF00);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 4, $this->Translate('Abnormal activity'), 'Mail', 0xFF8040);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 5, $this->Translate('Pre-Alarm'), 'Warning', 0xFF0000);
            IPS_SetVariableProfileAssociation('WAA.LeakLevel', 6, $this->Translate('Alarm triggered'), 'Alert', 0x800000);
        }

        //BorderValue
        if (!IPS_VariableProfileExists('WAA.ThresholdValue')) {
            IPS_CreateVariableProfile('WAA.ThresholdValue', 2);
            IPS_SetVariableProfileIcon('WAA.ThresholdValue', 'Distance');
            IPS_SetVariableProfileDigits('WAA.ThresholdValue', 1);
            IPS_SetVariableProfileValues('WAA.ThresholdValue', 0, 250, 0.5);
        }

        $this->RegisterVariableInteger('Leak', $this->Translate('Leak'), 'WAA.LeakLevel');
        $leakThresholdVariableID = $this->RegisterVariableFloat('LeakThreshold', $this->Translate('Leak threshold'), 'WAA.ThresholdValue');
        $this->EnableAction('LeakThreshold');

        //Define some default value
        if (GetValue($leakThresholdVariableID) == 0) {
            SetValue($leakThresholdVariableID, 150);
        }

        $this->RegisterVariableBoolean('Burst', $this->Translate('Burst'), '~Alert');
        $this->RegisterVariableFloat('BurstThreshold', $this->Translate('Burst threshold'), 'WAA.ThresholdValue');
        $this->EnableAction('BurstThreshold');

        $this->RegisterVariableBoolean('AlarmAlert', $this->Translate('Alarm Alert'), '~Alert');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $sourceID = $this->ReadPropertyInteger('MeterID');
        if ($sourceID != 0) {
            $MeterValue = GetValue($sourceID);
            $this->SetBuffer('LeakBuffer', json_encode($MeterValue));
            $this->SetBuffer('BurstBuffer', json_encode($MeterValue));
            $this->SetTimerInterval('UpdateLeak', $this->ReadPropertyInteger('LeakInterval') * 60 * 1000);
            $this->SetTimerInterval('UpdateBurst', $this->ReadPropertyInteger('BurstInterval') * 60 * 1000);
        }

        //Deleting references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        //Add reference
        if (IPS_VariableExists($sourceID)) {
            $this->RegisterReference($sourceID);
        }

        $value = $this->ReadPropertyInteger('flowType');
        if ($value == TYPE_WATER) {
            $this->MaintainVariable('LeakThreshold', $this->Translate('Leak'), 2, '~Flow', 0, true);
            $this->MaintainVariable('BurstThreshold', $this->Translate('Burst'), 2, '~Flow', 0, true);
        } elseif ($value == TYPE_ELECTICITY) {
            $this->MaintainVariable('LeakThreshold', $this->Translate('Leak'), 2, '~Electricity', 0, true);
            $this->MaintainVariable('BurstThreshold', $this->Translate('Burst'), 2, '~Electricity', 0, true);
        } elseif ($value == TYPE_GAS) {
            $this->MaintainVariable('LeakThreshold', $this->Translate('Leak'), 2, '~Gas', 0, true);
            $this->MaintainVariable('BurstThreshold', $this->Translate('Burst'), 2, '~Gas', 0, true);
        }
    }

    public function CheckAlert(string $ThresholdName, string $BufferName)
    {
        $MeterValue = GetValue($this->ReadPropertyInteger('MeterID'));
        $ValueOld = json_decode($this->GetBuffer($BufferName));

        // if Threshold is exceeded -> Set Alert
        if (($MeterValue - $ValueOld) > GetValueFloat($this->GetIDForIdent($ThresholdName))) {
            if ($ThresholdName == 'LeakThreshold') {
                SetValue($this->GetIDForIdent('Leak'), GetValueInteger($this->GetIDForIdent('Leak')) + 1);
                $this->SetBuffer($BufferName, json_encode($MeterValue));
            } elseif (GetValueFloat($this->GetIDForIdent($ThresholdName)) != 0) {
                SetValue($this->GetIDForIdent('Burst'), true);
                $this->SetBuffer($BufferName, json_encode($MeterValue));
            }

            // if Leak is over the AlarmThresholder or Burst is true -> send Alarm
            if (GetValue($this->GetIDForIdent('Leak')) > $this->ReadPropertyInteger('AlarmThresholder') || GetValue($this->GetIDForIdent('Burst'))) {
                SetValueBoolean($this->GetIDForIdent('AlarmAlert'), true);
            }
        }
        // if Threshold is not exceeded -> reset Alert
        else {
            if ($ThresholdName == 'LeakThreshold') {
                SetValue($this->GetIDForIdent('Leak'), 0);
                $this->SetBuffer($BufferName, json_encode($MeterValue));
            } elseif (GetValueFloat($this->GetIDForIdent($ThresholdName)) != 0) {
                SetValue($this->GetIDForIdent('Burst'), false);
                $this->SetBuffer($BufferName, json_encode($MeterValue));
            }
            //reset the Alarm
            if (GetValue($this->GetIDForIdent('Leak')) < $this->ReadPropertyInteger('AlarmThresholder') || !GetValue($this->GetIDForIdent('Burst'))) {
                SetValueBoolean($this->GetIDForIdent('AlarmAlert'), false);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'LeakThreshold':
            case 'BurstThreshold':
                //Neuen Wert in die Statusvariable schreiben
                SetValue($this->GetIDForIdent($Ident), $Value);
                break;

            default:
                throw new Exception($this->Translate('Invalid Ident'));
        }
    }
}