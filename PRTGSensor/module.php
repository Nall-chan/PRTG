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
 * PRTGSensor Klasse für ein Sensor von PRTG.
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
 * @property int $Interval
 * @property array $Channels
 * @method bool IORequestAction(string $Ident, mixed $Value)
 * @method void IOMessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data)
 * @method int FindIDForIdent(string $Ident)
 * @method void UnregisterProfile(string $Name)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method void RegisterParent()
 */
class PRTGSensor extends IPSModuleStrict
{
    use \prtg\VariableHelper;
    use \prtg\VariableProfileHelper;
    use \prtg\DebugHelper;
    use \prtg\BufferHelper;
    use \prtg\PRTGPause;
    use \prtg\VariableConverter;
    use \prtg\InstanceStatus {
        \prtg\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
        //\prtg\InstanceStatus::RegisterParent as IORegisterParent;
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
        $this->RegisterPropertyBoolean('AutoRename', true);
        $this->RegisterPropertyBoolean('ShowActionButton', true);
        $this->RegisterPropertyBoolean('ShowAckButton', true);
        $this->RegisterPropertyBoolean('AutoRenameChannels', true);
        $this->RegisterPropertyBoolean('ReadableState', true);
        $this->RegisterPropertyBoolean('UseInterval', false);
        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterPropertyInteger('id', 0);
        $this->RegisterTimer('RequestState', 0, 'PRTG_RequestState($_IPS[\'TARGET\']);');
        $this->Interval = 0;
        $this->Channels = [];
    }

    /**
     * ApplyChanges
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        $this->UnregisterProfile('PRTG.Action');
        $this->UnregisterProfile('PRTG.Ack');
        $this->UnregisterProfile('PRTG.Sensor');
        $this->UnregisterProfile('PRTG.ms');
        $this->UnregisterProfile('PRTG.Intensity');
        $this->UnregisterProfile('PRTG.No');
        $this->UnregisterProfile('PRTG.MByte');
        $this->UnregisterProfile('PRTG.Sec');
        $this->UnregisterProfile('PRTG.MBitSec');
        $this->UnregisterProfile('PRTG.kBitSec');
        $this->UnregisterProfile('PRTG.IpS');
        $this->UnregisterProfile('PRTG.IpM');
        $this->UnregisterProfile('PRTG.Items');
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
                            'IconValue'         => 'check',
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
        if ($this->ReadPropertyBoolean('ShowAckButton')) {
            $this->MaintainVariable(
                'AckButton',
                $this->Translate('Alarm control'),
                VARIABLETYPE_INTEGER,
                [
                    'ICON'         => 'Gear',
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => json_encode(
                        [
                            [
                                'Value'        => 0,
                                'Caption'      => $this->Translate('Acknowledge alarm'),
                                'IconActive'   => false,
                                'IconValue'    => '',
                                'Color'        => 0x555555
                            ]
                        ]
                    )

                ],
                -3,
                true
            );
            $this->EnableAction('AckButton');
        } else {
            $this->UnregisterVariable('AckButton');
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
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

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
        if ($this->RequestSensorState()) {
            return $this->RequestChannelState();
        }
        return false;
    }

    /**
     * ReceiveData
     *
     * @param  string $JSONString
     * @return string
     */
    public function ReceiveData(string $JSONString): string
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug('Got Event', $Data, 0);
        $this->RequestState();
        $this->SendDebug('End Event', $Data, 0);
        return '';
    }

    /**
     * GetConfigurationForm
     *
     * @return string
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Form['elements'][6]['caption'] = sprintf($this->Translate('Use not sensor Interval of %d seconds'), $this->Interval);
        $Form['elements'][6]['onChange'] = 'IPS_RequestAction(' . $this->InstanceID . ', \'ShowIntervall\' ,$UseInterval);';
        $Form['elements'][7]['visible'] = $this->ReadPropertyBoolean('UseInterval');
        $Form['actions'][0]['values'] = $this->GetChannelOverview();
        return json_encode($Form);
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
            case 'ActionButton':
                if ($Value) {
                    $this->SetResume();
                } else {
                    $this->SetPause();
                }
                return;
            case 'AckButton':
                $this->AcknowledgeAlarm();
                return;
            case 'ShowIntervall':
                $this->UpdateFormField('Interval', 'visible', $Value);
                return;
        }
        trigger_error('Invalid Ident', E_USER_NOTICE);
        return;
    }

    /**
     * AcknowledgeAlarm
     *
     * Bestätigt einen Alarm in PRTG.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function AcknowledgeAlarm(): bool
    {
        return $this->AcknowledgeAlarmEx('');
    }

    /**
     * AcknowledgeAlarmEx
     *
     * Bestätigt einen Alarm in PRT mit der in $Message übergebenen Nachricht.
     *
     * @param string $Message Nachricht für PRTG.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function AcknowledgeAlarmEx(string $Message): bool
    {
        if (!is_string($Message)) {
            trigger_error($this->Translate('Message must be string.'), E_USER_NOTICE);
            return false;
        }
        $QueryData = [
            'action' => 0,
            'id'     => $this->ReadPropertyInteger('id')
        ];

        if ($Message != '') {
            $QueryData['ackmsg'] = $Message;
        }

        $Result = $this->SendData('api/acknowledgealarm.htm', $QueryData);

        if (array_key_exists('Payload', $Result)) {
            return $this->RequestState();
        }
        return false;
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
                $this->RequestSensorState();
                $this->RequestChannelState();
                $this->SetStatus(IS_ACTIVE);
                $this->SetTimer(true);
            }
        } else {
            $this->SetStatus(IS_INACTIVE);
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
            if ($this->ReadPropertyBoolean('UseInterval')) {
                $Sec = $this->ReadPropertyInteger('Interval');
            } else {
                $Sec = $this->Interval;
                if ($Sec == 0) {
                    $Sec = $this->ReadPropertyInteger('Interval');
                }
            }
            $Interval = ($Sec < 5) ? 0 : $Sec * 1000;
        } else {
            $Interval = 0;
        }

        $this->SetTimerInterval('RequestState', $Interval);
    }

    /**
     * RequestSensorState
     *
     * Fragt den Zustand des Sensors aus PRTG ab.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    private function RequestSensorState(): bool
    {
        $Result = $this->SendData('api/table.json', [
            'content'      => 'sensors',
            'columns'      => 'objid,lastvalue,device,status,name,interval,active',
            'filter_objid' => $this->ReadPropertyInteger('id')
        ]);
        if (!array_key_exists('sensors', $Result)) {
            return false;
        }
        if (count($Result['sensors']) != 1) {
            return false;
        }
        $Data = $Result['sensors'][0];
        if ($Data['name'] == '') {
            return false;
        }
        if ($this->GetSummary() != $Data['device']) {
            $this->SetSummary($Data['device']);
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
        if ($this->Interval != (int) $Data['interval_raw']) {
            $this->Interval = (int) $Data['interval_raw'];
            $this->SetTimer(true);
        }

        return true;
    }

    /**
     * RequestChannelState
     *
     * Fragt den Zustand aller Kanäle dieses Sensors aus PRTG ab.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    private function RequestChannelState(): bool
    {
        $Result = $this->SendData('api/table.json', [
            'content' => 'channels',
            'columns' => 'objid,lastvalue,name',
            'id'      => $this->ReadPropertyInteger('id')
        ]);
        if (!array_key_exists('channels', $Result)) {
            return false;
        }
        $this->DecodeChannelData($Result['channels']);
        return true;
    }

    /**
     * DecodeChannelData
     *
     * Dekodiert die Daten der Kanäle und schreibt diese in Statusvariablen.
     *
     * @param array $Channels
     * @return void
     */
    private function DecodeChannelData(array $Channels): void
    {
        foreach ($Channels as $Channel) {
            if ($Channel['objid'] < -3) {
                continue;
            }
            if ($Channel['objid'] < 0) {
                $Ident = (string) ($Channel['objid'] + 255);
            } else {
                $Ident = (string) $Channel['objid'];
            }
            $Data = $this->GetVariableData($Channel);
            if ($Data === false) {
                continue;
            }
            if (array_key_exists('name_raw', $Channel)) {
                $Channel['name'] = $Channel['name_raw'];
            }
            $vid = $this->FindIDForIdent($Ident);
            if ($vid && IPS_GetVariable($vid)['VariableType'] != $Data['VarType']) {
                $IpsVarTyp = $this->GetVariableTypeName(IPS_GetVariable($vid)['VariableType']);
                $ChannelVarTyp = $this->GetVariableTypeName($Data['VarType']);
                $this->LogMessage(sprintf($this->Translate("Variable type mismatch for channel \"%s (%s)\"\r\nExpected %s but got %s"), $Channel['name'], IPS_GetLocation($this->InstanceID), $IpsVarTyp, $ChannelVarTyp), KL_ERROR);
                $this->AddChannelVarTypes($Channel, $ChannelVarTyp, $IpsVarTyp);
                continue;
            }
            $this->MaintainVariable($Ident, $Channel['name'], $Data['VarType'], $Data['Presentation'], $Channel['objid'], true);
            $vid = $this->FindIDForIdent($Ident);

            if ($this->ReadPropertyBoolean('AutoRenameChannels') && (IPS_GetName($vid)) != $Channel['name']) {
                IPS_SetName($vid, $Channel['name']);
            }
            $this->SetValue($Ident, $Data['Data']);
        }
    }

    /**
     * GetVariableTypeName
     *
     * @param  int $VarType
     * @return string
     */
    private function GetVariableTypeName(int $VarType): string
    {
        return match ($VarType) {
            VARIABLETYPE_BOOLEAN => 'Boolean',
            VARIABLETYPE_INTEGER => 'Integer',
            VARIABLETYPE_FLOAT   => 'Float',
            VARIABLETYPE_STRING  => 'String',
            default              => 'Unknown',
        };
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
     * AddChannelSuffix
     *
     * @param  array $ChannelData
     * @param  string $Unit
     * @return void
     */
    private function AddChannelSuffix(array $ChannelData, string $Unit = '', string $ChannelVarTyp = ''): void
    {
        $Channels = $this->Channels;
        if (!array_key_exists($ChannelData['objid'], $Channels)) {
            $Channels[$ChannelData['objid']]['ChannelVarTyp'] = $ChannelVarTyp;
            $Channels[$ChannelData['objid']]['IpsVarTyp'] = $ChannelVarTyp;
        }
        $Channels[$ChannelData['objid']]['Name'] = $ChannelData['name'];
        $Channels[$ChannelData['objid']]['Data'] = $ChannelData['lastvalue'];
        $Channels[$ChannelData['objid']]['Unit'] = $Unit ? $Unit : 'no Unit detected';
        $this->Channels = $Channels;
    }

    /**
     * AddChannelVarTypes
     *
     * @param  array $ChannelData
     * @param  string $ChannelVarType
     * @param  string $IpsVarId
     * @return void
     */
    private function AddChannelVarTypes(array $ChannelData, string $ChannelVarTyp, string $IpsVarTyp): void
    {
        $Channels = $this->Channels;
        $Channels[$ChannelData['objid']]['Name'] = $ChannelData['name'];
        $Channels[$ChannelData['objid']]['Data'] = $ChannelData['lastvalue'];
        $Channels[$ChannelData['objid']]['ChannelVarTyp'] = $ChannelVarTyp;
        $Channels[$ChannelData['objid']]['IpsVarTyp'] = $IpsVarTyp;
        $this->Channels = $Channels;
    }

    /**
     * GetChannelOverview
     *
     * @return array
     */
    private function GetChannelOverview(): array
    {
        return array_values($this->Channels);
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
