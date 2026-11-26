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
 * @copyright     2025 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.60
 *
 */

/**
 * PRTGConfigurator Klasse für ein PRTG Konfigurator.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2025 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 *
 * @version       2.60
 *
 * @example <b>Ohne</b>
 *
 * @property int $ParentID
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class PRTGConfigurator extends IPSModuleStrict
{
    use \PRTGConfigurator\BufferHelper;
    use \PRTGConfigurator\DebugHelper;

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->SetReceiveDataFilter('.*"nothingtoreceive":.*');
        $this->ParentID = 0;
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        $this->UpdateParent();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->UnregisterMessage(0, IPS_KERNELSTARTED);
                $this->UpdateParent();
                break;
            case FM_CONNECT:
            case FM_DISCONNECT:
                $this->UpdateParent();
                break;
            case IM_CHANGESETTINGS:
            case IM_CHANGESTATUS:
                if ($SenderID == $this->ParentID) {
                    $this->UpdateSummary();
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function GetConfigurationForm(): string
    {
        $IOID = $this->GetIO();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
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
            return json_encode($Form);
        }
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
            $Sensors = [];
            $Devices = [];
        } else {
            $Sensors = $this->GetSensors();
            $Devices = $this->GetDevices();
        }
        $InstanceIDListSensors = IPS_GetInstanceListByModuleID('{A37FD212-2E5B-4B65-83F2-956CB5BBB2FA}');

        $InstancesSensors = [];
        foreach ($InstanceIDListSensors as $InstanceIDSensor) {
            if (IPS_GetInstance($InstanceIDSensor)['ConnectionID'] == $IOID) {
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
                $Sensor['name'] = IPS_GetName($InstanceIDSensor);
                $Sensor['instanceID'] = $InstanceIDSensor;
            }
            $Sensor['parent'] = $Sensor['parentid'];
            unset($Sensor['parentid']);
            $Sensor['create'] = [
                'moduleID'      => '{A37FD212-2E5B-4B65-83F2-956CB5BBB2FA}',
                'configuration' => [
                    'id' => $Sensor['objid']
                ],
                'location'      => [$Sensor['device']]
            ];
            $Sensor['device'] = '';
        }

        $MissingSensors = [];
        foreach ($InstancesSensors as $InstanceIDSensor => $objid) {
            $MissingSensors[] = [
                'type'       => 'Sensor',
                'instanceID' => $InstanceIDSensor,
                'name'       => IPS_GetName($InstanceIDSensor),
                'objid'      => $objid,
                'device'     => '',
                'group'      => ''
            ];
        }

        $InstanceIDListDevices = IPS_GetInstanceListByModuleID('{95C47F84-8DF2-4370-90BD-3ED34C65ED7B}');
        $InstancesDevices = [];
        foreach ($InstanceIDListDevices as $InstanceIDDevice) {
            if (IPS_GetInstance($InstanceIDDevice)['ConnectionID'] == $IOID) {
                $InstancesDevices[$InstanceIDDevice] = IPS_GetProperty($InstanceIDDevice, 'id');
            }
        }

        foreach ($Devices as &$Device) {
            $InstanceIDDevice = array_search($Device['objid'], $InstancesDevices);
            $Device['type'] = 'Device';
            $Device['id'] = $Device['objid'];
            if ($InstanceIDDevice === false) {
                $Device['instanceID'] = 0;
                $Device['name'] = $Device['device'];
            } else {
                unset($InstancesDevices[$InstanceIDDevice]);
                $Device['instanceID'] = $InstanceIDDevice;
                $Device['name'] = IPS_GetName($InstanceIDDevice);
            }
            $Device['create'] = [
                'moduleID'      => '{95C47F84-8DF2-4370-90BD-3ED34C65ED7B}',
                'configuration' => [
                    'id' => $Device['objid']
                ],
                'location'      => [$Device['device']]
            ];
        }
        $MissingDevices = [];
        foreach ($InstancesDevices as $InstanceIDDevice => $objid) {
            $MissingDevices[] = [
                'type'       => 'Device',
                'instanceID' => $InstanceIDDevice,
                'name'       => IPS_GetName($InstanceIDDevice),
                'objid'      => $objid,
                'device'     => '',
                'group'      => ''
            ];
        }

        $Values = array_merge($Devices, $MissingDevices, $Sensors, $MissingSensors);
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
     * @param string[]  $QueryData Alle mit Allen GET-Parametern
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
        // ForwardData liefert im Fehlerfall einen leeren String. Entsteht beim Aufruf eine Ausgabe (Warnung), verwirft Symcon den Rückgabewert und liefert false.
        $Result = is_string($ResultString) ? @unserialize($ResultString) : false;
        if (!is_array($Result) || !array_key_exists('Error', $Result)) {
            return [];
        }
        if ($Result['Error'] != 200) {
            $this->SendDebug('Result Error', $Result, 0);
            return [];
        }
        unset($Result['Error']);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    /**
     * Ermittelt den verbundenen IO und beobachtet dessen Einstellungen, damit die Zusammenfassung aktuell bleibt.
     *
     * @return void
     */
    private function UpdateParent(): void
    {
        $OldParentID = $this->ParentID;
        $NewParentID = (int) $this->GetIO();
        if ($OldParentID != $NewParentID) {
            if ($OldParentID > 0) {
                $this->UnregisterMessage($OldParentID, IM_CHANGESETTINGS);
                $this->UnregisterMessage($OldParentID, IM_CHANGESTATUS);
            }
            if ($NewParentID > 0) {
                $this->RegisterMessage($NewParentID, IM_CHANGESETTINGS);
                // Statuswechsel: Der IO kann beim Modul-Reload noch nicht fertig erstellt sein.
                $this->RegisterMessage($NewParentID, IM_CHANGESTATUS);
            }
            $this->ParentID = $NewParentID;
        }
        $this->UpdateSummary();
    }

    /**
     * Zeigt den PRTG-Server des IO (ohne Pfad und Zugangsdaten) in der Zusammenfassung an.
     *
     * @return void
     */
    private function UpdateSummary(): void
    {
        $Summary = '';
        if (($this->ParentID > 0) && IPS_InstanceExists($this->ParentID)) {
            // Beim Modul-Reload werden alle Instanzen neu erstellt; die Eigenschaften des IO sind dann
            // eventuell noch nicht lesbar. Die bisherige Zusammenfassung bleibt in dem Fall stehen,
            // die neue folgt mit IM_CHANGESTATUS/IM_CHANGESETTINGS des IO.
            $Url = @IPS_GetProperty($this->ParentID, 'Host');
            if (!is_string($Url)) {
                return;
            }
            $Host = parse_url($Url, PHP_URL_HOST);
            if (is_string($Host) && ($Host != '')) {
                $Scheme = parse_url($Url, PHP_URL_SCHEME) ?? 'http';
                $Port = parse_url($Url, PHP_URL_PORT);
                $Summary = $Scheme . '://' . $Host . (is_int($Port) ? ':' . $Port : '');
            }
        }
        $this->SetSummary($Summary);
    }

    /**
     * Liefert den aktuell verbundenen Splitter.
     *
     * @return bool|int FALSE wenn kein Splitter vorhanden, sonst die ID des Splitter.
     */
    private function GetIO(): false|int
    {
        $IOID = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($IOID == 0) {
            return false;
        }
        return $IOID;
    }
}

/* @} */
