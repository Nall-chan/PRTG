<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PRTGConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PRTGConfigurator {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.50
 *
 */

/**
 * PRTGConfigurator Klasse für ein PRTG Konfigurator.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.50
 *
 * @example <b>Ohne</b>
 */
class PRTGConfigurator extends IPSModule
{
    use \PRTGConfigurator\BufferHelper;
    use \PRTGConfigurator\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{67470842-FB5E-485B-92A2-4401E371E6FC}');
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->RegisterPropertyInteger('RootId', 1);
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $IOID = $this->GetIO();
        if ($IOID === false) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Not connected to IO.'
                    ]]
                ]
            ];
        }
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (IPS_GetInstance($IOID)['InstanceStatus'] != IS_ACTIVE) {
            $Form['actions'][] = [
                'type'  => 'PopupAlert',
                'popup' => [
                    'items' => [[
                        'type'    => 'Label',
                        'caption' => 'Instance has no active parent.'
                    ]]
                ]
            ];
        }
        $Sensors = $this->GetSensors();
        $Devices = $this->GetDevices();
        $RootNames = [];
        $RootId = $this->ReadPropertyInteger('RootId');
        if (IPS_CategoryExists($RootId)) {
            while ($RootId != 0) {
                if ($RootId != 0) {
                    $RootNames[] = IPS_GetName($RootId);
                }
                $RootId = IPS_GetParent($RootId);
            }
        }
        $RootNames = array_reverse($RootNames);
        $InstanceIDListSensors = IPS_GetInstanceListByModuleID('{A37FD212-2E5B-4B65-83F2-956CB5BBB2FA}');

        $MyParent = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        $InstancesSensors = [];
        foreach ($InstanceIDListSensors as $InstanceIDSensor) {
            if (IPS_GetInstance($InstanceIDSensor)['ConnectionID'] == $MyParent) {
                $InstancesSensors[$InstanceIDSensor] = IPS_GetProperty($InstanceIDSensor, 'id');
            }
        }

        foreach ($Sensors as &$Sensor) {
            $InstanceIDSensor = array_search($Sensor['objid'], $InstancesSensors);
            $Sensor['type'] = 'Sensor';
            $Sensor['group'] = '';
            if ($InstanceIDSensor === false) {
                $Sensor['instanceID'] = 0;
            } else {
                unset($InstancesSensors[$InstanceIDSensor]);
                $Sensor['name'] = IPS_GetLocation($InstanceIDSensor);
                $Sensor['instanceID'] = $InstanceIDSensor;
            }
            $Sensor['parent'] = $Sensor['parentid'];
            unset($Sensor['parentid']);
            $Sensor['create'] = [
                'moduleID'      => '{A37FD212-2E5B-4B65-83F2-956CB5BBB2FA}',
                'configuration' => [
                    'id' => $Sensor['objid']
                ],
                'location'      => array_merge($RootNames, [$Sensor['device']])
            ];
        }

        $MissingSensors = [];
        foreach ($InstancesSensors as $InstanceIDSensor => $objid) {
            $MissingSensors[] = [
                'type'       => 'Sensor',
                'instanceID' => $InstanceIDSensor,
                'name'       => IPS_GetLocation($InstanceIDSensor),
                'objid'      => $objid,
                'device'     => '',
                'group'      => ''
            ];
        }

        $InstanceIDListDevices = IPS_GetInstanceListByModuleID('{95C47F84-8DF2-4370-90BD-3ED34C65ED7B}');
        $InstancesDevices = [];
        foreach ($InstanceIDListDevices as $InstanceIDDevice) {
            if (IPS_GetInstance($InstanceIDDevice)['ConnectionID'] == $MyParent) {
                $InstancesDevices[$InstanceIDDevice] = IPS_GetProperty($InstanceIDDevice, 'id');
            }
        }

        foreach ($Devices as &$Device) {
            $InstanceIDDevice = array_search($Device['objid'], $InstancesDevices);
            $Device['type'] = 'Device';
            $Device['id'] = $Device['objid'];
            if ($InstanceIDDevice === false) {
                $Device['instanceID'] = 0;
                $Device['name'] = '';
            } else {
                unset($InstancesDevices[$InstanceIDDevice]);
                $Device['name'] = IPS_GetLocation($InstanceIDDevice);
                $Device['instanceID'] = $InstanceIDDevice;
            }
            $Device['create'] = [
                'moduleID'      => '{95C47F84-8DF2-4370-90BD-3ED34C65ED7B}',
                'configuration' => [
                    'id' => $Device['objid']
                ],
                'location'      => array_merge($RootNames, [$Device['device']])
            ];
        }
        $MissingDevices = [];
        foreach ($InstancesDevices as $InstanceIDDevice => $objid) {
            $MissingDevices[] = [
                'type'       => 'Device',
                'instanceID' => $InstanceIDDevice,
                'name'       => IPS_GetLocation($InstanceIDDevice),
                'objid'      => $objid,
                'device'     => '',
                'group'      => ''
            ];
        }

        $Values = array_merge($Devices, $MissingDevices, $Sensors, $MissingSensors);
        if (count($Values) > 0) {
            foreach ($Values as $key => $row) {
                $SortDevice[$key] = $row['device'];
                $SortType[$key] = $row['type'];
            }
            array_multisort($SortDevice, SORT_ASC, $SortType, SORT_ASC, $Values);
        }
        $Form['actions'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Liefert alle Sensoren.
     *
     * @return array Array mit allen Sensoren
     */
    private function GetSensors(): array
    {
        $Result = $this->SendData('api/table.json', [
            'content' => 'sensors',
            'columns' => 'objid,device,name,parentid'
        ]);

        if (!array_key_exists('sensors', $Result)) {
            return [];
        }
        return $Result['sensors'];
    }

    /**
     * Liefert alle Geräte.
     *
     * @return array Array mit allen Geräten
     */
    private function GetDevices(): array
    {
        $Result = $this->SendData('api/table.json', [
            'content' => 'devices',
            'columns' => 'objid,group,device'
        ]);

        if (!array_key_exists('devices', $Result)) {
            return [];
        }
        return $Result['devices'];
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
        $ResultString = @$this->SendDataToParent(json_encode($Data));
        if ($ResultString === false) {
            return [];
        }
        $Result = unserialize($ResultString);
        if ($Result['Error'] != 200) {
            $this->SendDebug('Result Error', $Result, 0);
            return [];
        }
        unset($Result['Error']);
        $this->SendDebug('Request Result', $Result, 0);
        return $Result;
    }

    /**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetIO()
    {
        $SplitterID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($SplitterID == 0) {
            return false;
        }
        return $SplitterID;
    }
}

/* @} */
