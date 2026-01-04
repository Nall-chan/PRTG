<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PRTGHelper.php';

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

/**
 * PRTGDevice Klasse für ein Gerät von PRTG.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 *
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method void UnregisterProfile(string $Name)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method int RegisterParent()
 */
class PRTGDevice extends IPSModuleStrict
{
    use \prtg\VariableHelper;
    use \prtg\VariableProfileHelper;
    use \prtg\DebugHelper;
    use \prtg\BufferHelper;
    use \prtg\PRTGPause;
    use \prtg\VariableConverter;
    use \prtg\InstanceStatus {
        \prtg\InstanceStatus::MessageSink as IOMessageSink;
        \prtg\InstanceStatus::RequestAction as IORequestAction;
    }

    /**
     * Create
     *
     * @return void
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyInteger('id', 0);
        $this->RegisterPropertyBoolean('AutoRename', true);
        $this->RegisterPropertyBoolean('ShowActionButton', true);
        $this->RegisterPropertyBoolean('ReadableState', true);
        $this->RegisterPropertyBoolean('DisplaySensorState', true);
        $this->RegisterPropertyBoolean('DisplayTotalSensors', true);
        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterTimer('RequestState', 0, 'IPS_RequestAction($_IPS[\'TARGET\'],\'RequestState\', 0);');
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->UnregisterProfile('PRTG.Action');
        $this->UnregisterProfile('PRTG.Sensor');
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*"objid":' . $this->ReadPropertyInteger('id') . '.*');

        if ($this->MaintainVariable(
            'State',
            $this->Translate('State'),
            VARIABLETYPE_INTEGER,
            [
                'ICON'            => '',
                'PRESENTATION'    => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'INTERVALS_ACTIVE'=> true,
                'USAGE_TYPE'      => 0,
                'PERCENTAGE'      => false,
                'MIN'             => 1,
                'MAX'             => 14,
                'INTERVALS'       => json_encode(
                    [
                        [
                            'IntervalMinValue' => 1,
                            'IntervalMaxValue' => 1,
                            'ConstantActive'   => true,
                            'ConstantValue'    => $this->Translate('Unknown'),
                            'IconActive'       => true,
                            'IconValue'        => 'question',
                            'ColorActive'      => true,
                            'ColorValue'       => 0x555555
                        ],
                        [
                            'IntervalMinValue'  => 2,
                            'IntervalMaxValue'  => 2,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Scanning'),
                            'IconActive'        => true,
                            'IconValue'         => 'Information',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x555555
                        ],
                        [
                            'IntervalMinValue'  => 3,
                            'IntervalMaxValue'  => 3,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Up'),
                            'IconActive'        => true,
                            'IconValue'         => 'check',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x00ff00
                        ],
                        [
                            'IntervalMinValue'  => 4,
                            'IntervalMaxValue'  => 4,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Warning'),
                            'IconActive'        => true,
                            'IconValue'         => 'Warning',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x808000
                        ],
                        [
                            'IntervalMinValue'  => 5,
                            'IntervalMaxValue'  => 5,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Down'),
                            'IconActive'        => true,
                            'IconValue'         => 'Warning',
                            'ColorActive'       => true,
                            'ColorValue'        => 0xff0000
                        ],
                        [
                            'IntervalMinValue'  => 6,
                            'IntervalMaxValue'  => 6,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('No Probe'),
                            'IconActive'        => true,
                            'IconValue'         => 'Information',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x555555
                        ],
                        [
                            'IntervalMinValue'  => 7,
                            'IntervalMaxValue'  => 7,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Paused'),
                            'IconActive'        => true,
                            'IconValue'         => 'Sleep',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x000090
                        ],
                        [
                            'IntervalMinValue'  => 8,
                            'IntervalMaxValue'  => 8,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Paused by Dependency'),
                            'IconActive'        => true,
                            'IconValue'         => 'Sleep',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x000090
                        ],
                        [
                            'IntervalMinValue'  => 9,
                            'IntervalMaxValue'  => 9,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Paused by Schedule'),
                            'IconActive'        => true,
                            'IconValue'         => 'Sleep',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x000090
                        ],
                        [
                            'IntervalMinValue'  => 10,
                            'IntervalMaxValue'  => 10,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Unusual'),
                            'IconActive'        => true,
                            'IconValue'         => 'Warning',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x808000
                        ],
                        [
                            'IntervalMinValue'  => 11,
                            'IntervalMaxValue'  => 11,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Not Licensed'),
                            'IconActive'        => true,
                            'IconValue'         => 'Sleep',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x000090
                        ],
                        [
                            'IntervalMinValue'  => 12,
                            'IntervalMaxValue'  => 12,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Paused Until'),
                            'IconActive'        => true,
                            'IconValue'         => 'Sleep',
                            'ColorActive'       => true,
                            'ColorValue'        => 0x000090
                        ],
                        [
                            'IntervalMinValue'  => 13,
                            'IntervalMaxValue'  => 13,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Down Acknowledged'),
                            'IconActive'        => true,
                            'IconValue'         => 'Warning',
                            'ColorActive'       => true,
                            'ColorValue'        => 0xff0000
                        ],
                        [
                            'IntervalMinValue'  => 14,
                            'IntervalMaxValue'  => 14,
                            'ConstantActive'    => true,
                            'ConstantValue'     => $this->Translate('Down Partial'),
                            'IconActive'        => true,
                            'IconValue'         => 'Warning',
                            'ColorActive'       => true,
                            'ColorValue'        => 0xff0000
                        ]
                    ]
                )

            ],
            -2,
            true
        )) {
            $this->SetValue('State', 6);
        }

        if ($this->ReadPropertyBoolean('ReadableState')) {
            $this->MaintainVariable('ReadableState', $this->Translate('Readable state'), VARIABLETYPE_STRING, [
                'COLOR'        => -1,
                'ICON'         => 'Information',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], -2, true);
        } else {
            $this->UnregisterVariable('ReadableState');
        }
        if ($this->ReadPropertyBoolean('ShowActionButton')) {
            $this->MaintainVariable('ActionButton', $this->Translate('Monitoring'), VARIABLETYPE_BOOLEAN, [
                'ICON'         => '',
                'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                'OPTIONS'      => json_encode(
                    [
                        [
                            'Value'        => false,
                            'Caption'      => $this->Translate('Pause'),
                            'IconActive'   => true,
                            'IconValue'    => 'pause',
                            'Color'        => 0x000090
                        ],
                        [
                            'Value'        => true,
                            'Caption'      => $this->Translate('Active'),
                            'IconActive'   => true,
                            'IconValue'    => 'play',
                            'Color'        => 0x00ff00
                        ]
                    ]
                )

            ], -4, true);
            $this->EnableAction('ActionButton');
        } else {
            $this->UnregisterVariable('ActionButton');
        }

        if ($this->ReadPropertyBoolean('DisplayTotalSensors')) {
            $this->MaintainVariable('TotalSens', $this->Translate('Sensors Total'), VARIABLETYPE_INTEGER, [
                'COLOR'        => -1,
                'ICON'         => 'sigma',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 0, true);
        } else {
            $this->UnregisterVariable('TotalSens');
        }
        if ($this->ReadPropertyBoolean('DisplaySensorState')) {
            $this->MaintainVariable('UpSens', $this->Translate('Sensors Up'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0x00ff00,
                'ICON'         => 'check',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 1, true);
            $this->MaintainVariable('WarnSens', $this->Translate('Sensors Warn'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0x808000,
                'ICON'         => 'Warning',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 2, true);
            $this->MaintainVariable('UnusualSens', $this->Translate('Sensors Unusual'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0x808000,
                'ICON'         => 'Warning',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 3, true);
            $this->MaintainVariable('UndefinedSens', $this->Translate('Sensors Undefined'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0x555555,
                'ICON'         => 'question',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 4, true);
            $this->MaintainVariable('PartialDownSens', $this->Translate('Sensors PartialDown'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0xff0000,
                'ICON'         => 'Warning',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 5, true);
            $this->MaintainVariable('DownSens', $this->Translate('Sensors Down'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0xff0000,
                'ICON'         => 'Warning',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 6, true);
            $this->MaintainVariable('DownAckSens', $this->Translate('Sensors Down Acknowledged'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0xff0000,
                'ICON'         => 'check',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 7, true);
            $this->MaintainVariable('PausedSens', $this->Translate('Sensors Paused'), VARIABLETYPE_INTEGER, [
                'COLOR'        => 0x000090,
                'ICON'         => 'Sleep',
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 8, true);
        } else {
            $this->UnregisterVariable('DownSens');
            $this->UnregisterVariable('PartialDownSens');
            $this->UnregisterVariable('DownAckSens');
            $this->UnregisterVariable('UpSens');
            $this->UnregisterVariable('WarnSens');
            $this->UnregisterVariable('PausedSens');
            $this->UnregisterVariable('UnusualSens');
            $this->UnregisterVariable('UndefinedSens');
        }

        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if ($this->ReadPropertyInteger('id') == 0) {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimer(false);
            return;
        }

        if ($this->RegisterParent() && $this->HasActiveParent()) {
            $this->IOChangeState(IS_ACTIVE);
        }
    }

    /**
     * MessageSink
     *
     * @param  int $TimeStamp
     * @param  int $SenderID
     * @param  int $Message
     * @param  array $Data
     * @return void
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            return;
        }
        if (IPS_InstanceExists($SenderID)) {
            $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    /**
     * RequestState
     *
     * IPS Instanz-Funktion PRTG_RequestState.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function RequestState(): bool
    {
        return $this->RequestDeviceState();
    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     * @return string
     */
    public function ReceiveData(string $JSONString): string
    {
        IPS_RunScriptText('PRTG_RequestState(' . $this->InstanceID . ')');
        return '';
    }

    /**
     * RequestAction
     *
     * @param  string $Ident
     * @param  mixed $Value
     * @return void
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return;
        }
        switch ($Ident) {
            case 'RequestState':
                if (!IPS_InstanceExists($this->InstanceID)) {
                    return;
                }
                if (!$this->HasActiveParent()) {
                    return;
                }
                $this->RequestState();
                return;
            case 'ActionButton':
                if ($Value) {
                    $this->SetResume();
                } else {
                    $this->SetPause();
                }
                return;
        }
        trigger_error($this->Translate('Invalid Ident'), E_USER_NOTICE);
        return;
    }

    /**
     * IOChangeState
     *
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     *
     * @param  int $State
     * @return void
     */
    protected function IOChangeState(int $State): void
    {
        if ($State == IS_ACTIVE) {
            if ($this->ReadPropertyInteger('id') > 0) {
                $this->RequestDeviceState();
                $this->SetStatus(IS_ACTIVE);
                $this->SetTimer(true);
            }
        } else {
            @$this->SetStatus(IS_INACTIVE);
            $this->SetTimer(false);
        }
    }

    /**
     * KernelReady
     *
     * @return void
     */
    private function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    /**
     * SetTimer
     *
     * Setzt den Intervall-Timer.
     *
     * @param  bool $Active
     * @return void
     */
    private function SetTimer(bool $Active): void
    {
        if ($Active) {
            $Sec = $this->ReadPropertyInteger('Interval');
            $Interval = ($Sec < 5) ? 0 : $Sec * 1000;
        } else {
            $Interval = 0;
        }
        @$this->SetTimerInterval('RequestState', $Interval);
    }

    /**
     * RequestDeviceState
     *
     * Liest den aktuellen Status des Gerätes von PRTG.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    private function RequestDeviceState(): bool
    {
        if ($this->ReadPropertyInteger('id') == 0) {
            return false;
        }
        $Result = $this->SendData('api/table.json', [
            'content'      => 'devices',
            'columns'      => 'group,name,status,totalsens,active' . ($this->ReadPropertyBoolean('DisplaySensorState') ? ',downsens,partialdownsens,downacksens,upsens,warnsens,pausedsens,unusualsens,undefinedsens' : ''),
            'filter_objid' => $this->ReadPropertyInteger('id')
        ]);
        if (!array_key_exists('devices', $Result)) {
            return false;
        }
        if (count($Result['devices']) != 1) {
            return false;
        }
        $Data = $Result['devices'][0];
        if ($this->GetSummary() != $Data['group']) {
            $this->SetSummary($Data['group']);
        }
        $this->SetValue('State', $Data['status_raw']);
        if ($this->ReadPropertyBoolean('ReadableState')) {
            $this->SetValue('ReadableState', $Data['status']);
        }
        if ($this->ReadPropertyBoolean('ShowActionButton')) {
            $this->SetValue('ActionButton', (bool) $Data['active_raw']);
        }
        if ($this->ReadPropertyBoolean('AutoRename') && (IPS_GetName($this->InstanceID)) != $Data['name']) {
            IPS_SetName($this->InstanceID, $Data['name']);
        }
        if ($this->ReadPropertyBoolean('DisplayTotalSensors')) {
            $this->SetValue('TotalSens', $Data['totalsens_raw']);
        }
        if ($this->ReadPropertyBoolean('DisplaySensorState')) {
            $this->SetValue('DownSens', $Data['downsens_raw']);
            $this->SetValue('PartialDownSens', $Data['partialdownsens_raw']);
            $this->SetValue('DownAckSens', $Data['downacksens_raw']);
            $this->SetValue('UpSens', $Data['upsens_raw']);
            $this->SetValue('WarnSens', $Data['warnsens_raw']);
            $this->SetValue('PausedSens', $Data['pausedsens_raw']);
            $this->SetValue('UnusualSens', $Data['unusualsens_raw']);
            $this->SetValue('UndefinedSens', $Data['undefinedsens_raw']);
        }
        return true;
    }

    /**
     * GetSummary
     *
     * @return string
     */
    private function GetSummary(): string
    {
        return IPS_GetObject($this->InstanceID)['ObjectSummary'];
    }

    /**
     * SendData
     *
     * Sendet Eine Anfrage an den IO und liefert die Antwort.
     *
     * @param string $Uri       URI der Anfrage
     * @param array  $QueryData Alle mit Allen GET-Parametern
     * @param string $PostData  String mit POST Daten
     * @return array Antwort ale Array
     */
    private function SendData(string $Uri, array $QueryData = [], string $PostData = ''): array
    {
        $this->SendDebug('Request Uri:', $Uri, 0);
        $this->SendDebug('Request QueryData:', $QueryData, 0);
        $this->SendDebug('Request PostData:', $PostData, 0);
        $Data['DataID'] = '{963B49EF-64E6-4C70-8DA4-6699EF9B8CC5}';
        $Data['Uri'] = $Uri;
        $Data['QueryData'] = $QueryData;
        $Data['PostData'] = $PostData;
        $ResultString = $this->SendDataToParent(json_encode($Data));
        if ($ResultString === false) {
            return [];
        }
        $Result = unserialize($ResultString);

        if ($Result['Error'] != 200) {
            $this->SendDebug('Result Error', $Result, 0);
            return [];
        }
        unset($Result['Error']);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
}

/* @} */
