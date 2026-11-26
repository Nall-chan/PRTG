<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/PRTGChannelValue.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use prtg\ChannelValue;

/**
 * Tests der Einheitenerkennung mit Wertepaaren (Anzeigewert, Rohwert) aus veröffentlichten PRTG-Antworten.
 *
 * Quellen der Wertepaare (nur einzelne Werte übernommen, kein Code):
 * - Paessler Knowledge Base, helpdesk.paessler.com Artikel 76000067947, 76000074159, 76000081708, 76000081745
 * - lordmilko/PrtgAPI, Test-Items (ChannelItem.cs)
 * - anodot/daria, containers/dummy_destination/data/prtg_table.json
 * - danbyte-net/danbyte, prtg/tests/fixtures/table_channels_2089.json
 * - kanap-hq/KANAP, backend/src/ai/__tests__/prtg-monitoring.provider.spec.ts (deutsches PRTG)
 * Werte aus dem Live-System des Autors (deutsches PRTG) sind als solche gekennzeichnet.
 */
class PRTGChannelValueTest extends TestCase
{
    public static function ValueProvider(): array
    {
        return [
            // Anzeigewert, Rohwert, erwartete Art, erwarteter Wert, erwartete Einheit
            'kbit/s (KB 76000074159)'                    => ['9.22 kbit/s', 1152.0667, ChannelValue::KindBandwidth, 1152.0667, 'kbit/s'],
            'kbit/s (KB 76000081745)'                    => ['24 kbit/s', 2986.0656, ChannelValue::KindBandwidth, 2986.0656, 'kbit/s'],
            'Mbit/s (anodot)'                            => ['0.99 Mbit/s', 124113.4266, ChannelValue::KindBandwidth, 124113.4266, 'Mbit/s'],
            'Mbit/s mit Tausender (KB 76000067947)'      => ['1,062 Mbit/s', 132710979.55, ChannelValue::KindBandwidth, 132710979.55, 'Mbit/s'],
            'Mbit/Sek. (live, deutsch)'                  => ['15.607 Mbit/Sek.', 1950844.224, ChannelValue::KindBandwidth, 1950844.224, 'Mbit/Sek.'],
            'KByte (KB 76000081745)'                     => ['3,691 KByte', 3779761.8783, ChannelValue::KindBytes, 3779761.8783, 'KByte'],
            'MByte mit Tausender (KB 76000067947)'       => ['6,522 MByte', 6838698632.0, ChannelValue::KindBytes, 6838698632.0, 'MByte'],
            'MB (anodot)'                                => ['139 MB', 1.45236508E8, ChannelValue::KindBytes, 1.45236508E8, 'MB'],
            'MByte intern ×10, deutsch (KB 76000081708)' => ['8.325 MByte', 87297228800.0, ChannelValue::KindBytes, 8729722880.0, 'MByte'],
            'MByte auf 0 gerundet'                       => ['0 MByte', 400000.0, ChannelValue::KindBytes, 400000.0, 'MByte'],
            '% (anodot)'                                 => ['100 %', 100.0, ChannelValue::KindPercent, 100.0, '%'],
            '% intern ×10 (KB 76000081708)'              => ['93.4 %', 930.4, ChannelValue::KindPercent, 93.4, '%'],
            '% intern ×10 mit Rest (KB 76000081708)'     => ['51 %', 500.9934, ChannelValue::KindPercent, 50.9934, '%'],
            '% intern, XML-Format (PrtgAPI)'             => ['26 %', '0000000000000260.0000', ChannelValue::KindPercent, 26.0, '%'],
            'msec (anodot)'                              => ['0 msec', 0.4, ChannelValue::KindMilliseconds, 0.4, 'msec'],
            'ms (KANAP, deutsch)'                        => ['12 ms', 12, ChannelValue::KindMilliseconds, 12.0, 'ms'],
            'Anzahl kleiner als'                         => ['< 0.01 #', 0.0049, ChannelValue::KindCount, 0, '#'],
            'Anzahl pro Sekunde aktuell'                 => ['15 #/Sek.', 15, ChannelValue::KindCountPerSecond, 15, '#/Sek.'],
            'Anzahl pro Sekunde intern ×10'              => ['15 #/Sek.', 150, ChannelValue::KindCountPerSecond, 15, '#/Sek.'],
            'Dauer mehrteilig'                           => ['3 Tg. 4 Std.', 273600, ChannelValue::KindDuration, 273600, 'Std.'],
            '°C (anodot)'                                => ['31 °C', 31.0, ChannelValue::KindNumeric, 31.0, '°C'],
            '°C intern ×10 (KB 76000081708)'             => ['5.6 °C', 50.6, ChannelValue::KindNumeric, 5.6, '°C'],
            'V (anodot)'                                 => ['3.29 V', 3.29, ChannelValue::KindNumeric, 3.29, 'V'],
            'Wertetabelle (anodot)'                      => ['Normal', 1, ChannelValue::KindText, 'Normal', ''],
            'Wertetabelle mehrere Wörter (KB)'           => ['Reservoir Level OK', 1, ChannelValue::KindText, 'Reservoir Level OK', 'OK'],
            'Zahl ohne Einheit'                          => ['42', 42, ChannelValue::KindText, '42', ''],
        ];
    }

    #[DataProvider('ValueProvider')]
    public function testParse(string $LastValue, string|int|float $Raw, string $Kind, string|int|float $Value, string $Unit): void
    {
        $Result = ChannelValue::Parse($LastValue, $Raw);
        $this->assertNotNull($Result);
        $this->assertSame($Kind, $Result['Kind']);
        $this->assertSame($Unit, $Result['Unit']);
        if (is_float($Value)) {
            $this->assertEqualsWithDelta($Value, $Result['Value'], 0.0001);
        } else {
            $this->assertSame($Value, $Result['Value']);
        }
    }

    public function testNoData(): void
    {
        $this->assertNull(ChannelValue::Parse('', ''));
        $this->assertNull(ChannelValue::Parse('Keine Daten', 'Keine Daten'));
        $this->assertNull(ChannelValue::Parse('No data', ''));
    }

    public function testDecodeInternal(): void
    {
        $this->assertEqualsWithDelta(93.4, ChannelValue::DecodeInternal(930.4), 0.0001);
        $this->assertEqualsWithDelta(82.0, ChannelValue::DecodeInternal(820.0), 0.0001);
        $this->assertEqualsWithDelta(5.6, ChannelValue::DecodeInternal(50.6), 0.0001);
    }

    public function testValueWithoutUnitIsMarked(): void
    {
        $this->assertFalse(ChannelValue::Parse('42', 42)['HasUnit']);
        $this->assertTrue(ChannelValue::Parse('42 #', 42)['HasUnit']);
    }
}
