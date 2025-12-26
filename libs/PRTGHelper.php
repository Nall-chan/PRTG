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
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.60
 *
 */
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
/**
 * PRTGPause Trait für ein PRTGSensors und PRTGDevices.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
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
     * @param  mixed $Value
     * @return array|bool
     */
    private function GetVariableData($Value): array|bool
    {
        if (!array_key_exists('lastvalue_raw', $Value)) {
            $this->LogMessage(sprintf($this->Translate('Channel "%s (%s)", lastvalue_raw not found!'), $Value['name'], IPS_GetLocation($this->InstanceID)), KL_ERROR);
            return false;
        }
        if ($Value['lastvalue_raw'] === 'Keine Daten') {
            return false;
        }
        if (($Value['lastvalue_raw'] === '') && ($Value['lastvalue'] === '')) {
            return false;
        }
        $data = explode(' ', $Value['lastvalue']);
        if (count($data) == 1) {
            if (preg_match('/[0-9]/', $Value['lastvalue'], $matches, PREG_OFFSET_CAPTURE, 0)) {
                if (!$Value['Ignore']) {
                    $this->LogMessage(sprintf($this->Translate("Value without suffix in channel \"%s (%s)\" detected.\r\nPlease check the PRTG channel configuration."), $Value['name'], IPS_GetLocation($this->InstanceID)), KL_ERROR);
                }
            }
        }
        if ($data[0] == '<') {
            array_shift($data);
        }
        $Suffix = trim(array_pop($data));
        if ($Suffix == 'Delay') {
            $Suffix = trim(array_pop($data));
        }
        switch ($Suffix) {
            case 'Tg.':
            case 'Std.':
            case 'Min.':
            case 'Sek.':
                $Result = [
                    'Data'         => (int) $Value['lastvalue_raw'],
                    'Presentation' => [
                        'COUNTDOWN_TYPE' => 0,
                        'FORMAT'         => 2,
                        'PRESENTATION'   => VARIABLE_PRESENTATION_DURATION,
                    ],
                    'VarType'      => VARIABLETYPE_INTEGER
                ];
                break;
            case 'ms':
                $Result = [
                    'Data'         => $Value['lastvalue_raw'],
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
                ];
                break;
            case '#':
                $Result = [
                    'Data'         => $Value['lastvalue_raw'],
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
                ];
                break;
            case 'kByte':
            case 'MByte':
            case 'GByte':
            case 'kb':
            case 'KB':
            case 'MB':
            case 'GB':
                $Result = [
                    'Data'         => $Value['lastvalue_raw'],
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
                            [
                                'IntervalMinValue' => 0,
                                'IntervalMaxValue' => 1024,
                                'ConstantActive'   => false,
                                'ConstantValue'    => '',
                                'ConversionFactor' => 1,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' Byte',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 0,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ],
                            [
                                'IntervalMinValue' => 1024,
                                'IntervalMaxValue' => 1024 * 1024,
                                'ConstantActive'   => false,
                                'ConstantValue'    => '',
                                'ConversionFactor' => 1024,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' KB',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 2,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ],
                            [
                                'IntervalMinValue' => 1024 * 1024,
                                'IntervalMaxValue' => 1024 * 1024 * 1024,
                                'ConstantActive'   => false,
                                'ConstantValue'    => '',
                                'ConversionFactor' => 1024 * 1024,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' MB',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 2,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ],
                            [
                                'IntervalMinValue' => 1024 * 1024 * 1024,
                                'IntervalMaxValue' => PHP_FLOAT_MAX,
                                'ConstantActive'   => false,
                                'ConstantValue'    => '',
                                'ConversionFactor' => 1024 * 1024 * 1024,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' GB',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 2,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ],
                        ])
                    ],
                    'VarType'      => VARIABLETYPE_FLOAT
                ];
                break;
            case '%':
                $Result = [
                    'Data'         => $Value['lastvalue_raw'],
                    'Presentation' => [
                        'COLOR'           => -1,
                        'ICON'            => 'Intensity',
                        'SUFFIX'          => ' %',
                        'PRESENTATION'    => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'USAGE_TYPE'      => 0,
                        'PERCENTAGE'      => true,
                        'DIGITS'          => 2,
                        'MIN'             => 0,
                        'MAX'             => 100,
                        'INTERVALS_ACTIVE'=> false
                    ],
                    'VarType'      => VARIABLETYPE_FLOAT
                ];
                break;
            case 'kbit/Sek.':
            case 'Mbit/Sek.':
                $Result = [
                    'Data'         => $Value['lastvalue_raw'],
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
                            [
                                'IntervalMinValue' => 0,
                                'IntervalMaxValue' => 125000,
                                'ConstantActive'   => false,
                                'ConstantValue'    => '',
                                'ConversionFactor' => 125,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' kbit/Sek.',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 0,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ],
                            [
                                'IntervalMinValue' => 125000,
                                'IntervalMaxValue' => PHP_FLOAT_MAX,
                                'ConstantActive'   => false,
                                'ConstantValue'    => ' Mbit/Sek.',
                                'ConversionFactor' => 125000,
                                'PrefixActive'     => false,
                                'PrefixValue'      => '',
                                'SuffixActive'     => true,
                                'SuffixValue'      => ' KB',
                                'DigitsActive'     => true,
                                'DigitsValue'      => 2,
                                'IconActive'       => false,
                                'IconValue'        => '',
                                'ColorActive'      => false,
                                'Color'            => -1
                            ]
                        ])
                    ],
                    'VarType'      => VARIABLETYPE_FLOAT
                ];
                break;
            case '#/Sek.':
            case 'Msg/s':
            case 'Nachr./s':
                $Result = [
                    'Data'         => floor($Value['lastvalue_raw'] / 10),
                    'Presentation' => [
                        'COLOR'        => -1,
                        'ICON'         => 'sigma',
                        'SUFFIX'       => $this->Translate(' Items/sec'),
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'USAGE_TYPE'   => 0,
                        'PERCENTAGE'   => false,
                        'DIGITS'       => 0,
                    ],
                    'VarType'      => VARIABLETYPE_INTEGER
                ];
                break;
            case '#/Min.':
                $Result = [
                    'Data'         => floor($Value['lastvalue_raw'] / 10),
                    'Presentation' => [
                        'COLOR'        => -1,
                        'ICON'         => 'sigma',
                        'SUFFIX'       => $this->Translate(' Items/min'),
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'USAGE_TYPE'   => 0,
                        'PERCENTAGE'   => false,
                        'DIGITS'       => 0,
                    ],
                    'VarType'      => VARIABLETYPE_INTEGER
                ];
                break;
            case 'Items':
                $Result = [
                    'Data'         => floor($Value['lastvalue_raw'] / 10),
                    'Presentation' => [
                        'COLOR'        => -1,
                        'ICON'         => 'sigma',
                        'SUFFIX'       => $this->Translate(' Items'),
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                        'USAGE_TYPE'   => 0,
                        'PERCENTAGE'   => false,
                        'DIGITS'       => 0,
                    ],
                    'VarType'      => VARIABLETYPE_INTEGER
                ];
                break;
            default:
                $Result = [
                    'Data'         => $Value['lastvalue'],
                    'Presentation' => [],
                    'VarType'      => VARIABLETYPE_STRING
                ];
                break;
        }
        $this->AddChannelSuffix($Value, $Suffix, $this->GetVariableTypeName($Result['VarType']));
        return $Result;
    }
}

/* @} */
