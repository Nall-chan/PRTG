<?php

declare(strict_types=1);
trait VariableHelper
{
    private function ConvertRuntime($Seconds)
    {
        $t['sec'] = $Seconds % 60;
        $t['min'] = (($Seconds - $t['sec']) / 60) % 60;
        $t['std'] = (((($Seconds - $t['sec']) / 60) - $t['min']) / 60) % 24;
        $t['day'] = floor(((((($Seconds - $t['sec']) / 60) - $t['min']) / 60) / 24));
        return sprintf($this->Translate('%d Tg. %02d Std. %02d Min. %02d Sek.'), $t['day'], $t['std'], $t['min'], $t['sec']);
    }

    private function ConvertPRTGTimestamp(float $Timestamp)
    {
        return -2209165200 + (86400 * $Timestamp);
    }

    private function ConvertValue($Value)
    {
        $Result = [
            'Data'    => $Value['lastvalue'],
            'Profile' => '',
            'VarType' => vtString
        ];

        if (strpos($Value['lastvalue'], ' ') === false) {
            if (is_int($Value['lastvalue'])) {
                $Result = [
                    'Data'    => $Value['lastvalue'],
                    'Profile' => '',
                    'VarType' => vtInteger
                ];
            } elseif (is_float($Value['lastvalue'])) {
                $Result = [
                    'Data'    => $Value['lastvalue'],
                    'Profile' => '',
                    'VarType' => vtFloat
                ];
            } elseif (is_numeric($Value['lastvalue'])) {
                $Result = [
                    'Data'    => (float) $Value['lastvalue'],
                    'Profile' => '',
                    'VarType' => vtFloat
                ];
            }
            return $Result;
        }
        if (!array_key_exists('lastvalue_raw', $Value)) {
            return false;
        }
        $data = explode(' ', $Value['lastvalue']);
        if ($data[0] == '<') {
            array_shift($data);
        }
        if (count($data) > 3) {
            return $Result;
        }
        if (count($data) < 2) {
            return $Result;
        }
        switch ($data[1]) {
            case 'Tg.':
                $Result = [
                    'Data'    => $this->ConvertRuntime($Value['lastvalue_raw']),
                    'Profile' => '',
                    'VarType' => vtString
                ];
                break;
            case 'ms':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.ms',
                    'VarType' => vtFloat
                ];
                break;
            case '#':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.No',
                    'VarType' => vtInteger
                ];
                break;
            case 'MByte':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'] / 1048576,
                    'Profile' => 'PRTG.MByte',
                    'VarType' => vtFloat
                ];
                break;
            case 'Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.Sec',
                    'VarType' => vtInteger
                ];
                break;
            case '%':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.Intensity',
                    'VarType' => vtFloat
                ];
                break;
            case 'Mbit/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 1250000),
                    'Profile' => 'PRTG.MBitSec',
                    'VarType' => vtInteger
                ];
                break;
            case 'kbit/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 1250),
                    'Profile' => 'PRTG.kBitSec',
                    'VarType' => vtInteger
                ];
                break;
            case '#/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.IpS',
                    'VarType' => vtInteger
                ];
                break;
            case '#/Min.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.IpM',
                    'VarType' => vtInteger
                ];
                break;
            case 'Items':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.Items',
                    'VarType' => vtInteger
                ];
                break;
        }
        return $Result;
    }

    protected function SetValue($Ident, $Value)
    {
        if (method_exists('IPSModule', 'SetValue')) {
            if (parent::SetValue($Ident, $Value) === false) {
                trigger_error('Error on write ' . $Ident . ' with value ' . $Value);
            }
        } else {
            if (SetValue($this->GetIDForIdent($Ident), $Value)) {
                trigger_error('Error on write ' . $Ident . ' with value ' . $Value);
            }
        }
    }
}

/**
 * Trait mit Hilfsfunktionen für Variablenprofile.
 */
trait VariableProfile
{
    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ bool mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        $this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix);
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
        }
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer mit Assoziationen.
     *
     * @param string $Name         Name des Profils.
     * @param string $Icon         Name des Icon.
     * @param string $Prefix       Prefix für die Darstellung.
     * @param string $Suffix       Suffix für die Darstellung.
     * @param array  $Associations Assoziationen der Werte als Array.
     */
    protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
    {
        if (count($Associations) === 0) {
            $MinValue = 0;
            $MaxValue = 0;
        } else {
            $MinValue = $Associations[0][0];
            $MaxValue = $Associations[count($Associations) - 1][0];
        }
        $this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);
        $old = IPS_GetVariableProfile($Name)['Associations'];
        $OldValues = array_column($old, 'Value');
        foreach ($Associations as $Association) {
            IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
            $OldKey = array_search($Association[0], $OldValues);
            if (!($OldKey === false)) {
                unset($OldValues[$OldKey]);
            }
        }
        foreach ($OldValues as $OldKey => $OldValue) {
            IPS_SetVariableProfileAssociation($Name, $OldValue, '', '', 0);
        }
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ bool.
     *
     * @param string $Name   Name des Profils.
     * @param string $Icon   Name des Icon.
     * @param string $Prefix Prefix für die Darstellung.
     * @param string $Suffix Suffix für die Darstellung.
     */
    protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix)
    {
        $this->RegisterProfile(vtBoolean, $Name, $Icon, $Prefix, $Suffix, 0, 0, 0);
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ integer.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param int    $StepSize Schrittweite
     */
    protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
    {
        $this->RegisterProfile(vtInteger, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize);
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param int    $StepSize Schrittweite
     */
    protected function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
    {
        $this->RegisterProfile(vtFloat, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits);
    }

    /**
     * Erstell und konfiguriert ein VariablenProfil für den Typ float.
     *
     * @param int    $VarTyp   Typ der Variable
     * @param string $Name     Name des Profils.
     * @param string $Icon     Name des Icon.
     * @param string $Prefix   Prefix für die Darstellung.
     * @param string $Suffix   Suffix für die Darstellung.
     * @param int    $MinValue Minimaler Wert.
     * @param int    $MaxValue Maximaler wert.
     * @param int    $StepSize Schrittweite
     */
    protected function RegisterProfile($VarTyp, $Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits = 0)
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $VarTyp);
        } else {
            $profile = IPS_GetVariableProfile($Name);
            if ($profile['ProfileType'] != $VarTyp) {
                throw new Exception('Variable profile type does not match for profile ' . $Name, E_USER_WARNING);
            }
        }

        IPS_SetVariableProfileIcon($Name, $Icon);
        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
        if ($VarTyp != vtBoolean) {
            IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
        }
        if ($VarTyp == vtFloat) {
            IPS_SetVariableProfileDigits($Name, $Digits);
        }
    }

    /**
     * Löscht ein Variablenprofile, sofern es nicht außerhalb dieser Instanz noch verwendet wird.
     *
     * @param string $Name Name des zu löschenden Profils.
     */
    protected function UnregisterProfile(string $Name)
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        IPS_DeleteVariableProfile($Name);
    }
}
