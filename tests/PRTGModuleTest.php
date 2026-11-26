<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Tests der Module mit den Symcon-Stubs (ohne Netzwerkzugriff).
 */
class PRTGModuleTest extends TestCase
{
    private const IOID = '{67470842-FB5E-485B-92A2-4401E371E6FC}';
    private const ConfiguratorID = '{32B8B831-91B2-44B5-9B66-9F1685647216}';
    private const DeviceID = '{95C47F84-8DF2-4370-90BD-3ED34C65ED7B}';
    private const SensorID = '{A37FD212-2E5B-4B65-83F2-956CB5BBB2FA}';

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        parent::setUp();
    }

    /**
     * A1: Die Status-Variable startet mit 1 (Unbekannt) und wird bei erneutem ApplyChanges nicht überschrieben.
     */
    public function testStateIsNotResetOnApplyChanges(): void
    {
        foreach ([self::DeviceID, self::SensorID] as $ModuleID) {
            $Iid = IPS_CreateInstance($ModuleID);
            IPS_ApplyChanges($Iid);
            $Vid = IPS_GetObjectIDByIdent('State', $Iid);
            $this->assertSame(1, GetValue($Vid), 'Initialwert ' . $ModuleID);
            // Statusvariablen sind schreibgeschützt: Wert über die Instanz setzen, wie bei einer Abfrage.
            $Intf = IPS\InstanceManager::getInstanceInterface($Iid);
            (new ReflectionMethod($Intf, 'SetValue'))->invoke($Intf, 'State', 3);
            IPS_ApplyChanges($Iid);
            $this->assertSame(3, GetValue($Vid), 'Wert nach ApplyChanges ' . $ModuleID);
        }
    }

    /**
     * B3: Neue Instanzen benennen nicht automatisch um.
     */
    public function testAutoRenameDefaultsToFalse(): void
    {
        $Device = IPS_CreateInstance(self::DeviceID);
        $this->assertFalse(IPS_GetProperty($Device, 'AutoRename'));
        $Sensor = IPS_CreateInstance(self::SensorID);
        $this->assertFalse(IPS_GetProperty($Sensor, 'AutoRename'));
        $this->assertFalse(IPS_GetProperty($Sensor, 'AutoRenameChannels'));
    }

    /**
     * B8: Der Konfigurator zeigt den PRTG-Server des IO ohne Pfad und Zugangsdaten an.
     */
    public function testConfiguratorSummaryShowsHostOfIO(): void
    {
        $IO = IPS_CreateInstance(self::IOID);
        // Open bleibt false: kein Verbindungsaufbau im Test.
        IPS_SetProperty($IO, 'Host', 'https://user:secret@prtg.example:8443/prtg/');
        IPS_ApplyChanges($IO);
        $Configurator = IPS_CreateInstance(self::ConfiguratorID);
        IPS_ConnectInstance($Configurator, $IO);
        IPS_ApplyChanges($Configurator);
        $this->assertSame('https://prtg.example:8443', IPS_GetObject($Configurator)['ObjectSummary']);
    }

    /**
     * B1: Zugangsdaten erscheinen nicht im Debug.
     */
    public function testMaskUrl(): void
    {
        $IO = IPS\InstanceManager::getInstanceInterface(IPS_CreateInstance(self::IOID));
        $Method = new ReflectionMethod($IO, 'MaskUrl');
        $Masked = $Method->invoke($IO, 'https://user:secret@prtg:443/api/getpasshash.htm?username=admin&password=geheim&passhash=12345');
        $this->assertStringNotContainsString('secret', $Masked);
        $this->assertStringNotContainsString('geheim', $Masked);
        $this->assertStringNotContainsString('12345', $Masked);
        $this->assertStringContainsString('username=admin', $Masked);
    }

    /**
     * A5: GetGraph meldet Fehler beim Aufrufer statt still einen leeren String zu liefern.
     */
    public function testGetGraphReportsErrorWhenInactive(): void
    {
        $Iid = IPS_CreateInstance(self::IOID);
        IPS_ApplyChanges($Iid);
        $IO = IPS\InstanceManager::getInstanceInterface($Iid);
        $this->expectOutputString($IO->Translate('PRTG not reachable') . PHP_EOL);
        $this->assertSame('', $IO->GetGraph(1, 1001, 0, 800, 400, 0, 10, true));
    }

    /**
     * A14: Neue Kanäle mit Zahl und unbekannter Einheit werden Float.
     * Eine vorhandene String-Variable gilt als Typfehler: Sie bleibt unverändert und wird nicht beschrieben.
     */
    public function testChannelTypeForNewAndExistingVariables(): void
    {
        $Iid = IPS_CreateInstance(self::SensorID);
        IPS_ApplyChanges($Iid);
        $Sensor = IPS\InstanceManager::getInstanceInterface($Iid);
        // Vorhandene Variable aus einer älteren Version: Kanal 6 als String.
        $Old = IPS_CreateVariable(VARIABLETYPE_STRING);
        IPS_SetParent($Old, $Iid);
        IPS_SetIdent($Old, '6');
        $Decode = new ReflectionMethod($Sensor, 'DecodeChannelData');
        $Decode->invoke($Sensor, [
            ['objid' => 5, 'name' => 'Temperatur', 'lastvalue' => '31 °C', 'lastvalue_raw' => 31.0],
            ['objid' => 6, 'name' => 'Spannung', 'lastvalue' => '3.29 V', 'lastvalue_raw' => 3.29],
        ]);
        $New = IPS_GetObjectIDByIdent('5', $Iid);
        $this->assertSame(VARIABLETYPE_FLOAT, IPS_GetVariable($New)['VariableType']);
        $this->assertEqualsWithDelta(31.0, GetValue($New), 0.001);
        $this->assertSame(VARIABLETYPE_STRING, IPS_GetVariable($Old)['VariableType']);
        $this->assertSame('', GetValue($Old));

        // „Ignoriere Einheit und Typ“: Variable bleibt erhalten (gleiche ID, Typ String) und bekommt den Anzeigewert.
        $Overwrite = new ReflectionMethod($Sensor, 'SetChannelOverwrite');
        $Overwrite->invoke($Sensor, 6, true);
        $Decode->invoke($Sensor, [
            ['objid' => 6, 'name' => 'Spannung', 'lastvalue' => '3.29 V', 'lastvalue_raw' => 3.29],
        ]);
        $this->assertSame($Old, IPS_GetObjectIDByIdent('6', $Iid));
        $this->assertSame(VARIABLETYPE_STRING, IPS_GetVariable($Old)['VariableType']);
        $this->assertSame('3.29 V', GetValue($Old));
    }

    /**
     * Ignorierter Typ bei Zahl-Variablen: Wert wird in den Typ der vorhandenen Variable umgewandelt.
     */
    public function testIgnoredTypeConvertsValue(): void
    {
        $Sensor = IPS\InstanceManager::getInstanceInterface(IPS_CreateInstance(self::SensorID));
        $Convert = new ReflectionMethod($Sensor, 'ConvertToVariableType');
        $this->assertSame(3, $Convert->invoke($Sensor, ['Data' => 3.29, 'Text' => '3.29 V'], VARIABLETYPE_INTEGER));
        $this->assertSame(26.0, $Convert->invoke($Sensor, ['Data' => 26, 'Text' => '26 #'], VARIABLETYPE_FLOAT));
        $this->assertSame('3.29 V', $Convert->invoke($Sensor, ['Data' => 3.29, 'Text' => '3.29 V'], VARIABLETYPE_STRING));
        $this->assertNull($Convert->invoke($Sensor, ['Data' => 'Normal', 'Text' => 'Normal'], VARIABLETYPE_FLOAT));
    }

    /**
     * A2: Bandbreite ab 1 Mbit/s wird in Mbit/Sek. angezeigt (nicht in KB).
     */
    public function testBandwidthIntervalSuffix(): void
    {
        $Sensor = IPS\InstanceManager::getInstanceInterface(IPS_CreateInstance(self::SensorID));
        $Method = new ReflectionMethod($Sensor, 'GetVariableData');
        $Result = $Method->invoke($Sensor, [
            'objid'         => 1,
            'name'          => 'Datenverkehr ausgehend',
            'lastvalue'     => '15.607 Mbit/Sek.',
            'lastvalue_raw' => 1950844.224,
            'Ignore'        => false
        ]);
        $this->assertSame(VARIABLETYPE_FLOAT, $Result['VarType']);
        $Intervals = json_decode($Result['Presentation']['INTERVALS'], true);
        $this->assertSame(' kbit/Sek.', $Intervals[0]['SuffixValue']);
        $this->assertSame(' Mbit/Sek.', $Intervals[1]['SuffixValue']);
        $this->assertSame(125000, $Intervals[1]['ConversionFactor']);
    }
}
