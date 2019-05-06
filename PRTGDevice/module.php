<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/PRTGHelper.php';
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace prtg {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.31
 *
 */

/**
 * PRTGDevice Klasse für ein Gerät von PRTG.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       1.31
 *
 * @example <b>Ohne</b>
 */
class PRTGDevice extends IPSModule
{

    use prtg\VariableHelper,
        prtg\VariableProfileHelper,
        prtg\DebugHelper,
        prtg\BufferHelper,
        prtg\PRTGPause,
        prtg\VariableConverter;
    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('id', 0);
        $this->RegisterPropertyBoolean('AutoRename', true);
        $this->RegisterPropertyBoolean('ShowActionButton', true);
        $this->RegisterPropertyBoolean('ReadableState', true);
        $this->RegisterPropertyBoolean('DisplaySensorState', true);
        $this->RegisterPropertyBoolean('DisplayTotalSensors', true);
        $this->RegisterPropertyInteger('Interval', 60);
        $this->RegisterTimer('RequestState', 0, 'PRTG_RequestState($_IPS[\'TARGET\']);');
        $this->ConnectParent('{67470842-FB5E-485B-92A2-4401E371E6FC}');
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        $this->RegisterProfileBooleanEx('PRTG.Action', 'Gear', '', '', [
            [true, $this->Translate('Active'), '', 0x00ff00],
            [false, $this->Translate('Pause'), '', 0x000090]
        ]);

        $this->RegisterProfileIntegerEx('PRTG.Sensor', 'Information', '', '', [
            [1, $this->Translate('Unknown'), '', 0x555555],
            [2, $this->Translate('Scanning'), '', 0x555555],
            [3, $this->Translate('Up'), '', 0x00ff00],
            [4, $this->Translate('Warning'), 'Warning', 0x808000],
            [5, $this->Translate('Down'), 'Warning', 0xff0000],
            [6, $this->Translate('No Probe'), '', 0x555555],
            [7, $this->Translate('Paused'), 'Sleep', 0x000090],
            [8, $this->Translate('Paused by Dependency'), 'Sleep', 0x000090],
            [9, $this->Translate('Paused by Schedule'), 'Sleep', 0x000090],
            [10, $this->Translate('Unusual'), 'Warning', 0x808000],
            [11, $this->Translate('Not Licensed'), 'Sleep', 0x000090],
            [12, $this->Translate('Paused Until'), 'Sleep', 0x000090],
            [13, $this->Translate('Down Acknowledged'), 'Warning', 0xff0000],
            [14, $this->Translate('Down Partial'), 'Warning', 0xff0000],
        ]);
        parent::ApplyChanges();
        $this->SetReceiveDataFilter('.*"objid":' . $this->ReadPropertyInteger('id') . '.*');

        if (!@$this->GetIDForIdent('State')) {
            $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_INTEGER, 'PRTG.Sensor', -2, true);
            $this->SetValue('State', 6);
        }

        if ($this->ReadPropertyBoolean('ReadableState')) {
            $this->MaintainVariable('ReadableState', $this->Translate('Readable state'), VARIABLETYPE_STRING, '', -2, true);
        } else {
            $this->UnregisterVariable('ReadableState');
        }
        if ($this->ReadPropertyBoolean('ShowActionButton')) {
            $this->MaintainVariable('ActionButton', $this->Translate('Control'), VARIABLETYPE_BOOLEAN, 'PRTG.Action', -4, true);
            $this->EnableAction('ActionButton');
        } else {
            $this->UnregisterVariable('ActionButton');
        }

        if ($this->ReadPropertyBoolean('DisplayTotalSensors')) {
            $this->MaintainVariable('TotalSens', $this->Translate('Sens Total'), VARIABLETYPE_INTEGER, '', 0, true);
        } else {
            $this->UnregisterVariable('TotalSens');
        }
        if ($this->ReadPropertyBoolean('DisplaySensorState')) {
            $this->MaintainVariable('UpSens', $this->Translate('Sensors Up'), VARIABLETYPE_INTEGER, '', 1, true);
            $this->MaintainVariable('WarnSens', $this->Translate('Sensors Warn'), VARIABLETYPE_INTEGER, '', 2, true);
            $this->MaintainVariable('UnusualSens', $this->Translate('Sensors Unusual'), VARIABLETYPE_INTEGER, '', 3, true);
            $this->MaintainVariable('UndefinedSens', $this->Translate('Sensors Undefined'), VARIABLETYPE_INTEGER, '', 4, true);
            $this->MaintainVariable('PartialDownSens', $this->Translate('Sensors PartialDown'), VARIABLETYPE_INTEGER, '', 5, true);
            $this->MaintainVariable('DownSens', $this->Translate('Sensors Down'), VARIABLETYPE_INTEGER, '', 6, true);
            $this->MaintainVariable('DownAckSens', $this->Translate('Sensors Down Acknowledged'), VARIABLETYPE_INTEGER, '', 7, true);
            $this->MaintainVariable('PausedSens', $this->Translate('Sensors Paused'), VARIABLETYPE_INTEGER, '', 8, true);
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
        if ($this->ReadPropertyInteger('id') > 0) {
            $this->SetStatus(IS_ACTIVE);
            if (IPS_GetKernelRunlevel() == KR_READY) { // IPS läuft dann gleich Daten abholen
                $this->RequestDeviceState();
            }
            $this->SetTimer(true);
        } else {
            $this->SetStatus(IS_INACTIVE);
            $this->SetTimer(false);
        }
    }

    /**
     * Setzt den Intervall-Timer.
     */
    private function SetTimer(bool $Active)
    {
        if ($Active) {
            $Sec = $this->ReadPropertyInteger('Interval');
            $Interval = ($Sec < 5) ? 0 : $Sec * 1000;
        } else {
            $Interval = 0;
        }
        $this->SetTimerInterval('RequestState', $Interval);
    }

    /**
     * IPS Instanz-Funktion PRTG_RequestState.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function RequestState(): bool
    {
        return $this->RequestDeviceState();
    }

    /**
     * Liest den aktuellen Status des Gerätes von PRTG.
     *
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    private function RequestDeviceState(): bool
    {
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
        $this->SetSummary($Data['group']);

        $this->SetValue('State', $Data['status_raw']);
        if ($this->ReadPropertyBoolean('ReadableState')) {
            $this->SetValue('ReadableState', $Data['status']);
        }
        if ($this->ReadPropertyBoolean('ShowActionButton')) {
            $this->SetValue('ActionButton', (bool) $Data['active_raw']);
        }
        if ($this->ReadPropertyBoolean('AutoRename')) {
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
     * Sendet Eine Anfrage an den IO und liefert die Antwort.
     *
     * @param string $Uri       URI der Anfrage
     * @param array  $QueryData Alle mit Allen GET-Parametern
     * @param string $PostData  String mit POST Daten
     *
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
            //trigger_error('Error: ' . $Result['Error'], E_USER_NOTICE);
            return [];
        }
        unset($Result['Error']);
        $this->SendDebug('Request Result', $Result, 0);
        return $Result;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        $this->SendDebug('Got Event', $Data, 0);
        $this->RequestState();
        $this->SendDebug('End Event', $Data, 0);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ActionButton':
                if ($Value) {
                    return $this->SetResume();
                } else {
                    return $this->SetPause();
                }
        }
        trigger_error($this->Translate('Invalid Ident'), E_USER_NOTICE);
        return false;
    }

}

/* @} */
