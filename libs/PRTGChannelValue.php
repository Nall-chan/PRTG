<?php

declare(strict_types=1);

namespace prtg;

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          PRTGChannelValue.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2026 Michael Tröger
 * @license       Custom Non-Commercial Share-Alike License (see LICENSE)
 * @version       2.61
 *
 */

/**
 * Erkennt Einheit und Wert eines Kanals aus der PRTG-API (table.json, content=channels).
 * Ohne Abhängigkeit zu Symcon, damit die Erkennung per Unit-Test geprüft werden kann.
 *
 * Belege (siehe STATUS.md, „Belege zu Einheiten und Rohwerten“):
 * - Bandbreite: lastvalue_raw in Byte/s, Volumen in Byte (1024-basiert).
 * - Ältere PRTG-Versionen liefern lastvalue_raw teilweise im internen Format
 *   „ganzzahliger Teil × 10 + Nachkommaanteil“ (93.4 % → 930.4), aktuelle Versionen den echten Wert.
 *   Welches Format vorliegt, wird über den Vergleich mit der Zahl im Anzeigewert entschieden.
 */
class ChannelValue
{
    public const KindDuration = 'Duration';
    public const KindMilliseconds = 'Milliseconds';
    public const KindCount = 'Count';
    public const KindBytes = 'Bytes';
    public const KindPercent = 'Percent';
    public const KindBandwidth = 'Bandwidth';
    public const KindCountPerSecond = 'CountPerSecond';
    public const KindCountPerMinute = 'CountPerMinute';
    public const KindItems = 'Items';
    public const KindNumeric = 'Numeric';
    public const KindText = 'Text';

    /** @var array<string, string> Einheit im Anzeigewert => Art des Wertes (deutsch und englisch) */
    private const Units = [
        'Tg.'       => self::KindDuration,
        'Std.'      => self::KindDuration,
        'Min.'      => self::KindDuration,
        'Sek.'      => self::KindDuration,
        'ms'        => self::KindMilliseconds,
        'msec'      => self::KindMilliseconds,
        '#'         => self::KindCount,
        'kByte'     => self::KindBytes,
        'KByte'     => self::KindBytes,
        'MByte'     => self::KindBytes,
        'GByte'     => self::KindBytes,
        'kb'        => self::KindBytes,
        'KB'        => self::KindBytes,
        'MB'        => self::KindBytes,
        'GB'        => self::KindBytes,
        '%'         => self::KindPercent,
        'kbit/Sek.' => self::KindBandwidth,
        'Mbit/Sek.' => self::KindBandwidth,
        'kbit/s'    => self::KindBandwidth,
        'Mbit/s'    => self::KindBandwidth,
        '#/Sek.'    => self::KindCountPerSecond,
        'Msg/s'     => self::KindCountPerSecond,
        'Nachr./s'  => self::KindCountPerSecond,
        '#/Min.'    => self::KindCountPerMinute,
        'Items'     => self::KindItems
    ];

    /**
     * @var array<string, int> Faktor von der Zahl im Anzeigewert zum Rohwert, wenn er nicht 1 ist.
     * Volumen: Rohwert in Byte (1024-basiert). Bandbreite: Rohwert in Byte/s (1 kbit/s = 125 Byte/s).
     */
    private const DisplayFactors = [
        'kByte'     => 1024,
        'KByte'     => 1024,
        'kb'        => 1024,
        'KB'        => 1024,
        'MByte'     => 1048576,
        'MB'        => 1048576,
        'GByte'     => 1073741824,
        'GB'        => 1073741824,
        'kbit/Sek.' => 125,
        'kbit/s'    => 125,
        'Mbit/Sek.' => 125000,
        'Mbit/s'    => 125000
    ];

    /** @var string[] Anzeigewerte ohne Daten */
    private const NoData = ['Keine Daten', 'No data'];

    /**
     * Wertet einen Kanal aus.
     *
     * @param string           $LastValue Anzeigewert (lastvalue), z.B. "9.22 kbit/s"
     * @param string|int|float $Raw       Rohwert (lastvalue_raw)
     *
     * @return array{Kind: string, Unit: string, Value: string|int|float, HasUnit: bool}|null null, wenn der Kanal keine Daten liefert
     */
    public static function Parse(string $LastValue, string|int|float $Raw): ?array
    {
        $LastValue = trim($LastValue);
        if (in_array($LastValue, self::NoData, true) || in_array($Raw, self::NoData, true)) {
            return null;
        }
        if (($LastValue === '') && ($Raw === '')) {
            return null;
        }
        $Tokens = preg_split('/\s+/', $LastValue);
        if (in_array($Tokens[0], ['<', '>'], true)) {
            array_shift($Tokens);
        }
        if ((count($Tokens) > 1) && (end($Tokens) === 'Delay')) {
            array_pop($Tokens);
        }
        $HasUnit = count($Tokens) > 1;
        $Unit = $HasUnit ? (string) end($Tokens) : '';
        $Kind = self::Units[$Unit] ?? null;
        if ($Kind === null) {
            // Unbekannte Einheit: Zahlenwert mit Einheit als Suffix, sonst Text.
            $Kind = ($HasUnit && is_numeric($Raw) && (self::ParseDisplayNumbers($Tokens[0]) !== [])) ? self::KindNumeric : self::KindText;
        }
        if (($Kind !== self::KindText) && !is_numeric($Raw)) {
            $Kind = self::KindText;
        }
        if ($Kind === self::KindText) {
            return ['Kind' => $Kind, 'Unit' => $Unit, 'Value' => $LastValue, 'HasUnit' => $HasUnit];
        }
        $Value = (float) $Raw;
        if ($Kind !== self::KindDuration) {
            // Dauer wird mehrteilig angezeigt („3 Tg. 4 Std.“) und taugt nicht zum Vergleich.
            $Value = self::SelectRawValue($Value, $Tokens[0], self::DisplayFactors[$Unit] ?? 1);
        }
        $Value = match ($Kind) {
            self::KindDuration, self::KindCount, self::KindCountPerSecond, self::KindCountPerMinute, self::KindItems => (int) round($Value),
            default                                                                                                  => $Value
        };
        return ['Kind' => $Kind, 'Unit' => $Unit, 'Value' => $Value, 'HasUnit' => $HasUnit];
    }

    /**
     * Wandelt einen Rohwert im internen PRTG-Format („ganzzahliger Teil × 10 + Nachkommaanteil“) um.
     *
     * @param float $Raw Rohwert im internen Format, z.B. 930.4
     *
     * @return float Wert, z.B. 93.4
     */
    public static function DecodeInternal(float $Raw): float
    {
        $Whole = floor($Raw);
        return floor($Whole / 10) + ($Raw - $Whole);
    }

    /**
     * Wählt zwischen Rohwert und intern kodiertem Rohwert den, der zur Zahl im Anzeigewert passt.
     *
     * @param float  $Raw     Rohwert
     * @param string $Display Zahl aus dem Anzeigewert, z.B. "6,522" oder "93.4"
     * @param int    $Factor  Faktor von der Zahl im Anzeigewert zum Rohwert (z.B. 1048576 bei MByte)
     *
     * @return float Passender Wert; bei Gleichstand oder ohne lesbare Zahl der Rohwert
     */
    private static function SelectRawValue(float $Raw, string $Display, int $Factor): float
    {
        $Numbers = array_map(fn (float $Number): float => $Number * $Factor, self::ParseDisplayNumbers($Display));
        // Ohne Zahl oder bei einem auf 0 gerundeten Anzeigewert ist kein Vergleich möglich.
        if (($Numbers === []) || (max(array_map('abs', $Numbers)) == 0)) {
            return $Raw;
        }
        $Decoded = self::DecodeInternal($Raw);
        $DiffRaw = min(array_map(fn (float $Number): float => abs($Number - $Raw), $Numbers));
        $DiffDecoded = min(array_map(fn (float $Number): float => abs($Number - $Decoded), $Numbers));
        return ($DiffDecoded < $DiffRaw) ? $Decoded : $Raw;
    }

    /**
     * Liest die Zahl eines Anzeigewertes in beiden Schreibweisen (englisch und deutsch),
     * da das Tausender- und Dezimaltrennzeichen von der Sprache in PRTG abhängt.
     *
     * @param string $Display Zahl aus dem Anzeigewert
     *
     * @return float[] Mögliche Werte (leer, wenn keine Zahl)
     */
    private static function ParseDisplayNumbers(string $Display): array
    {
        if (!preg_match('/^-?[0-9][0-9.,]*$/', $Display)) {
            return [];
        }
        $Numbers = [];
        foreach ([[',', '.'], ['.', ',']] as [$Thousands, $Decimal]) {
            $Number = str_replace([$Thousands, $Decimal], ['', '.'], $Display);
            if (is_numeric($Number)) {
                $Numbers[] = (float) $Number;
            }
        }
        return $Numbers;
    }
}

/* @} */
