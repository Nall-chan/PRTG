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
 * @copyright     2025 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.60
 *
 */
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
require_once __DIR__ . '/PRTGChannelValue.php';

/**
 * ParentStatus Trait für PRTGSensors und PRTGDevices.
 *
 * Verteilt die Nachrichten des IO (Statuswechsel, Verbinden, Trennen) an IOChangeState.
 *
 * Symcon (C++) arbeitet die MessageSink aller Instanzen nacheinander ab. Der ParentIOHelper
 * startet deshalb je Instanz ein Skript, damit ein langer Statuswechsel die übrigen Nachrichten nicht aufhält.
 * Die Rust-Edition (interne Testversion, Stand 9.1; das Verhalten kann sich noch ändern)
 * stellt die Nachrichten gleichzeitig zu; die Skripte aller Instanzen starten dann auf
 * einmal und Symcon verwirft einen Teil davon („Zu viele gleichzeitige Skripte“). Dort wird IOChangeState
 * direkt in der MessageSink aufgerufen. Das ist möglich, weil IOChangeState nur Status und Timer setzt.
 *
 * Erfordert die Methoden IOMessageSink (Alias von InstanceStatus::MessageSink), RegisterParent,
 * HasActiveParent und IOChangeState sowie die Eigenschaft ParentID.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       2.61
 */
trait ParentStatus
{
    /**
     * Verarbeitet eine Nachricht des IO bzw. der eigenen Verbindung.
     *
     * @param  int $TimeStamp
     * @param  int $SenderID
     * @param  int $Message
     * @param  array $Data
     * @return void
     */
    protected function ParentMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if (!$this->IsRustKernel()) {
            $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
            return;
        }
        switch ($Message) {
            case FM_CONNECT:
                $this->RegisterParent();
                $State = $this->HasActiveParent() ? IS_ACTIVE : IS_INACTIVE;
                break;
            case FM_DISCONNECT:
                $this->RegisterParent();
                $State = IS_INACTIVE;
                break;
            case IM_CHANGESTATUS:
                if ($SenderID != $this->ParentID) {
                    return;
                }
                $State = $Data[0];
                break;
            default:
                return;
        }
        $this->IOChangeState($State);
    }

    /**
     * Prüft, ob Symcon in der Rust-Edition läuft (Revision beginnt mit „rust-“).
     *
     * @return bool
     */
    private function IsRustKernel(): bool
    {
        return str_starts_with(IPS_GetKernelRevision(), 'rust-');
    }
}

/**
 * PRTGPause Trait für ein PRTGSensors und PRTGDevices.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 */
trait PRTGPause
{
    /**
     * SetResume
     *
     * IPS Instanz-Funktion PRTG_SetResume
     * Setzt die Überwachung des Gerätes in PRTG fort.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetResume(): bool
    {
        $Result = $this->SendData('api/pause.htm', [
            'id'     => $this->ReadPropertyInteger('id'),
            'action' => 1

        ]);
        if (array_key_exists('Payload', $Result)) {
            IPS_Sleep(50);
            return $this->RequestState();
        }
        return false;
    }

    /**
     * SetPause
     *
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
     * SetPauseEx
     *
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
     * SetPauseDuration
     *
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
     * SetPauseDurationEx
     *
     * IPS Instanz-Funktion PRTG_SetPauseDurationEx
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
            IPS_Sleep(50);
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
    /**
     * ConvertPRTGTimestamp
     *
     * @param  float $Timestamp
     * @return float|int
     */
    private function ConvertPRTGTimestamp(float $Timestamp): float|int
    {
        return -2209165200 + (86400 * $Timestamp);
    }

    /**
     * GetVariableData
     *
     * Ermittelt aus den Kanaldaten von PRTG den Wert, den Variablentyp und die Darstellung.
     *
     * @param  array $Value Kanal aus table.json (lastvalue, lastvalue_raw, name, objid) und 'Ignore'
     * @return array|bool Array mit 'Data', 'Presentation', 'VarType' und 'Text' (Anzeigewert), false ohne Daten
     */
    private function GetVariableData(array $Value): array|bool
    {
        if (!array_key_exists('lastvalue_raw', $Value)) {
            $this->LogMessage(sprintf($this->Translate('Channel "%s (%s)", lastvalue_raw not found!'), $Value['name'], IPS_GetLocation($this->InstanceID)), KL_ERROR);
            return false;
        }
        $Parsed = ChannelValue::Parse((string) $Value['lastvalue'], $Value['lastvalue_raw']);
        if ($Parsed === null) {
            return false;
        }
        if (!$Parsed['HasUnit'] && preg_match('/[0-9]/', (string) $Value['lastvalue']) && !$Value['Ignore']) {
            $this->LogMessage(sprintf($this->Translate("Value without suffix in channel \"%s (%s)\" detected.\r\nPlease check the PRTG channel configuration."), $Value['name'], IPS_GetLocation($this->InstanceID)), KL_ERROR);
        }
        $Result = match ($Parsed['Kind']) {
            ChannelValue::KindDuration => [
                'Presentation' => [
                    'COUNTDOWN_TYPE' => 0,
                    'FORMAT'         => 2,
                    'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                ],
                'VarType'      => VARIABLETYPE_INTEGER
            ],
            ChannelValue::KindMilliseconds => [
                'Presentation' => [
                    'COLOR'        => -1,
                    'ICON'         => 'binary',
                    'SUFFIX'       => ' ms',
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'   => 0,
                    'PERCENTAGE'   => false,
                    'DIGITS'       => 2,
                ],
                'VarType'      => VARIABLETYPE_FLOAT
            ],
            ChannelValue::KindCount => [
                'Presentation' => [
                    'COLOR'        => -1,
                    'ICON'         => 'sigma',
                    'SUFFIX'       => ' #',
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'   => 0,
                    'PERCENTAGE'   => false,
                    'DIGITS'       => 0,
                ],
                'VarType'      => VARIABLETYPE_INTEGER
            ],
            ChannelValue::KindBytes => [
                'Presentation' => [
                    'COLOR'            => -1,
                    'ICON'             => '',
                    'SUFFIX'           => ' MB',
                    'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'       => 0,
                    'PERCENTAGE'       => false,
                    'DIGITS'           => 2,
                    'INTERVALS_ACTIVE' => true,
                    'INTERVALS'        => json_encode([
                        $this->GetInterval(0, 1024, 1, ' Byte', 0),
                        $this->GetInterval(1024, 1024 * 1024, 1024, ' KB', 2),
                        $this->GetInterval(1024 * 1024, 1024 * 1024 * 1024, 1024 * 1024, ' MB', 2),
                        $this->GetInterval(1024 * 1024 * 1024, PHP_FLOAT_MAX, 1024 * 1024 * 1024, ' GB', 2),
                    ])
                ],
                'VarType'      => VARIABLETYPE_FLOAT
            ],
            ChannelValue::KindPercent => [
                'Presentation' => [
                    'COLOR'            => -1,
                    'ICON'             => 'Intensity',
                    'SUFFIX'           => ' %',
                    'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'       => 0,
                    'PERCENTAGE'       => true,
                    'DIGITS'           => 2,
                    'MIN'              => 0,
                    'MAX'              => 100,
                    'INTERVALS_ACTIVE' => false
                ],
                'VarType'      => VARIABLETYPE_FLOAT
            ],
            ChannelValue::KindBandwidth => [
                // Rohwert in Byte/s: 125 Byte/s = 1 kbit/s, 125000 Byte/s = 1 Mbit/s
                'Presentation' => [
                    'COLOR'            => -1,
                    'ICON'             => '',
                    'SUFFIX'           => ' kbit/Sek.',
                    'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'       => 0,
                    'PERCENTAGE'       => false,
                    'DIGITS'           => 2,
                    'INTERVALS_ACTIVE' => true,
                    'INTERVALS'        => json_encode([
                        $this->GetInterval(0, 125000, 125, ' kbit/Sek.', 0),
                        $this->GetInterval(125000, PHP_FLOAT_MAX, 125000, ' Mbit/Sek.', 2),
                    ])
                ],
                'VarType'      => VARIABLETYPE_FLOAT
            ],
            ChannelValue::KindCountPerSecond => $this->GetCountPresentation(' Items/sec'),
            ChannelValue::KindCountPerMinute => $this->GetCountPresentation(' Items/min'),
            ChannelValue::KindItems          => $this->GetCountPresentation(' Items'),
            ChannelValue::KindNumeric        => [
                'Presentation' => [
                    'COLOR'        => -1,
                    'ICON'         => '',
                    'SUFFIX'       => ' ' . $Parsed['Unit'],
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'USAGE_TYPE'   => 0,
                    'PERCENTAGE'   => false,
                    'DIGITS'       => 2,
                ],
                'VarType'      => VARIABLETYPE_FLOAT
            ],
            default => [
                'Presentation' => [],
                'VarType'      => VARIABLETYPE_STRING
            ]
        };
        $Result['Data'] = $Parsed['Value'];
        $Result['Text'] = (string) $Value['lastvalue'];
        $this->AddChannelSuffix($Value, $Parsed['Unit'], $this->GetVariableTypeName($Result['VarType']));
        return $Result;
    }

    /**
     * GetCountPresentation
     *
     * Darstellung für Anzahlen mit übersetztem Suffix.
     *
     * @param  string $Suffix Suffix (wird übersetzt)
     * @return array 'Presentation' und 'VarType'
     */
    private function GetCountPresentation(string $Suffix): array
    {
        return [
            'Presentation' => [
                'COLOR'        => -1,
                'ICON'         => 'sigma',
                'SUFFIX'       => $this->Translate($Suffix),
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'USAGE_TYPE'   => 0,
                'PERCENTAGE'   => false,
                'DIGITS'       => 0,
            ],
            'VarType'      => VARIABLETYPE_INTEGER
        ];
    }

    /**
     * GetInterval
     *
     * Ein Intervall der Wertanzeige mit Umrechnungsfaktor, Suffix und Nachkommastellen.
     *
     * @param  int|float $Min    Untere Grenze
     * @param  int|float $Max    Obere Grenze
     * @param  int|float $Factor Umrechnungsfaktor (der Wert wird durch ihn geteilt)
     * @param  string $Suffix Suffix
     * @param  int    $Digits Nachkommastellen
     * @return array Intervall
     */
    private function GetInterval(int|float $Min, int|float $Max, int|float $Factor, string $Suffix, int $Digits): array
    {
        return [
            'IntervalMinValue' => $Min,
            'IntervalMaxValue' => $Max,
            'ConstantActive'   => false,
            'ConstantValue'    => '',
            'ConversionFactor' => $Factor,
            'PrefixActive'     => false,
            'PrefixValue'      => '',
            'SuffixActive'     => true,
            'SuffixValue'      => $Suffix,
            'DigitsActive'     => true,
            'DigitsValue'      => $Digits,
            'IconActive'       => false,
            'IconValue'        => '',
            'ColorActive'      => false,
            'ColorValue'       => -1
        ];
    }
}

/* @} */
