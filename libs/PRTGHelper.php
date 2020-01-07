<?php

declare(strict_types=1);

namespace prtg;

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.20
 *
 */
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

/**
 * PRTGPause Trait für ein PRTGSensors und PRTGDevices.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2020 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.20
 *
 * @example <b>Ohne</b>
 */
trait PRTGPause
{
    /**
     * IPS Instanz-Funktion PRTG_SetResume
     * Setzt die Überwachung des Gerätes in PRTG fort.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetResume(): bool
    {
        $Result = $this->SendData('api/pause.htm', [
            'action' => 1,
            'id'     => $this->ReadPropertyInteger('id')
        ]);
        if (array_key_exists('Payload', $Result)) {
            return $this->RequestState();
        }
        return false;
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPause
     * Pausiert die Überwachung des Gerätes in PRTG.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPause(): bool
    {
        return $this->SetPauseEx('');
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseEx
     * Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergeben Meldung.
     *
     * @param string $Message Meldung für PRTG
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseEx(string $Message): bool
    {
        return $this->SetPauseDurationEx(0, $Message);
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseDuration
     * Pausiert die Überwachung des Gerätes in PRTG für die in '$Minutes' angegebene Zeit in Minuten.
     *
     * @param int $Minutes Pausenzeit in Minuten
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseDuration(int $Minutes): bool
    {
        return $this->SetPauseDurationEx($Minutes, '');
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseDuration
     * Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergeben Meldung für die in '$Minutes' angegebene Zeit in Minuten.
     *
     * @param int    $Minutes Pausenzeit in Minuten
     * @param string $Message Meldung für PRTG
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseDurationEx(int $Minutes, string $Message): bool
    {
        if (!is_string($Message)) {
            trigger_error($this->Translate('Message must be string.'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($Minutes)) {
            trigger_error($this->Translate('Minutes must be integer.'), E_USER_NOTICE);
            return false;
        }
        $QueryData = [
            'action' => 0,
            'id'     => $this->ReadPropertyInteger('id')
        ];

        if ($Minutes > 0) {
            $Uri = 'pauseobjectfor.htm';
            $QueryData['duration'] = $Minutes;
        } else {
            $Uri = 'pause.htm';
        }

        if ($Message != '') {
            $QueryData['pausemsg'] = $Message;
        }

        $Result = $this->SendData($Uri, $QueryData);
        if (array_key_exists('Payload', $Result)) {
            return $this->RequestState();
        }
        return false;
    }
}

/**
 * Trait mit Hilfsfunktionen für Variablen.
 */
trait VariableConverter
{
    private function ConvertRuntime(int $Seconds)
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
            'VarType' => VARIABLETYPE_STRING
        ];

        if (is_numeric($Value['lastvalue'])) {
            $Result = [
                'Data'    => (float) $Value['lastvalue'],
                'Profile' => '',
                'VarType' => VARIABLETYPE_FLOAT
            ];
            if (is_int($Value['lastvalue'])) {
                $Result = [
                    'Data'    => $Value['lastvalue'],
                    'Profile' => '',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
            } elseif (is_float($Value['lastvalue'])) {
                $Result = [
                    'Data'    => $Value['lastvalue'],
                    'Profile' => '',
                    'VarType' => VARIABLETYPE_FLOAT
                ];
            }
            return $Result;
        }
        if (!array_key_exists('lastvalue_raw', $Value)) {
            return false;
        }
        if ($Value['lastvalue_raw'] === 'Keine Daten') {
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
                    'Data'    => $this->ConvertRuntime((int) $Value['lastvalue_raw']),
                    'Profile' => '',
                    'VarType' => VARIABLETYPE_STRING
                ];
                break;
            case 'ms':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.ms',
                    'VarType' => VARIABLETYPE_FLOAT
                ];
                break;
            case '#':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.No',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case 'MByte':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'] / 1048576,
                    'Profile' => 'PRTG.MByte',
                    'VarType' => VARIABLETYPE_FLOAT
                ];
                break;
            case 'Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.Sec',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case '%':
                $Result = [
                    'Data'    => $Value['lastvalue_raw'],
                    'Profile' => 'PRTG.Intensity',
                    'VarType' => VARIABLETYPE_FLOAT
                ];
                break;
            case 'Mbit/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 1250000),
                    'Profile' => 'PRTG.MBitSec',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case 'kbit/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 1250),
                    'Profile' => 'PRTG.kBitSec',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case '#/Sek.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.IpS',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case '#/Min.':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.IpM',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
            case 'Items':
                $Result = [
                    'Data'    => floor($Value['lastvalue_raw'] / 10),
                    'Profile' => 'PRTG.Items',
                    'VarType' => VARIABLETYPE_INTEGER
                ];
                break;
        }
        return $Result;
    }
}

/* @} */
