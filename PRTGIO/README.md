[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version 2.50](https://img.shields.io/badge/Modul%20Version-2.50-blue.svg)]()
[![Version 6.2](https://img.shields.io/badge/Symcon%20Version-6.2%20%3E-green.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v61-v62-q2-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/PRTG/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/PRTG/actions)
[![Run Tests](https://github.com/Nall-chan/PRTG/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/PRTG/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#4-spenden)  

# PRTG I/O  <!-- omit in toc -->
I/O zur Kommunikation mit PRTG  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen und Profile](#4-statusvariablen-und-profile)
- [5. WebFront](#5-webfront)
- [6. PHP-Befehlsreferenz](#6-php-befehlsreferenz)
- [7. Anhang](#7-anhang)
  - [1. Events von PRTG empfangen](#1-events-von-prtg-empfangen)
  - [2. IPS in PRTG überwachen](#2-ips-in-prtg-überwachen)
- [8. Lizenz](#8-lizenz)

## 1. Funktionsumfang

 - Schnittstelle zwischen den Geräte und Sensor Instanzen und PRTG.  
 - Empfangen von Events aus PRTG.  
 - Bereitstellen von IPS Systeminformation für einen PRTG-Sensor.  
 - Abfragen von Graphen aus PRTG.  

## 2. Installation

 Dieses Modul ist Bestandteil der [PRTG-Library](../README.md#3-software-installation).  

## 3. Einrichten der Instanzen in IP-Symcon

Diese Instanz wird automatisch erzeugt sobald z.B. der [PRTG Konfigurator:](../PRTGConfigurator/) erstellt wird.  

Alternativ ist das Modul im Dialog 'Instanz hinzufügen' unter dem Hersteller 'PRTG' zu finden.  
![Instanz hinzufügen](imgs/add.png)  

Folgende Parameter sind in der Instanz zu konfigurieren:  
Dabei sollte der Benutzer in PRTG Administrative Rechte bekommen, um die Überwachung zu steuern und Alarme quittieren zu können.  
Die Erweiterten SSL Einstellungen sind per default inaktiv.  Für z.B. selbst-signierte Zertifikate sind z.B. Option 1 und 2 zu aktivieren.  

Der Ereignis-Hook für PRTG wird als Link unterhalb der Konfiguration ausgegeben.  
Dabei werden sowohl mehrere IP-Adressen, als auch die globalen NAT Einstellungen von Symcon beachtet.  
Sollte es auch anderen Gründen nötig sein den Ereignis-Hook anzupassen, so kann dies in den Experteneinstellungen erfolgen.  

![Konfigurator](imgs/conf.png)  
**Konfigurationsseite:**  

|  Eigenschaft   |   Typ   | Standardwert |                   Funktion                   |
| :------------: | :-----: | :----------: | :------------------------------------------: |
|      Open      |  bool   |    false     |          I/O öffnen oder schließen           |
|      Host      | string  |              | URL zum PRTG Webfront z.B. http://prtg:8081  |
|    Username    | string  |              |             Benutzername in PRTG             |
|    Password    | string  |              |          Passwort für den Benutzer           |
|  NoHostVerify  |  bool   |              |      Deaktiviere Prüfung des Hostnamen       |
|  NoPeerVerify  |  bool   |              |     Deaktiviere Prüfung der Gegenstelle      |
|  NoCertCheck   |  bool   |              |      Deaktiviere Prüfung des Zertifikat      |
|    ReturnIP    | string  |              | Abweichende IP-Adresse für den Ereignis-Hook |
|   ReturnPort   | integer |     3777     |   Abweichender Port für den Ereignis-Hook    |
| ReturnProtocol |  bool   |    false     |       true für https im Ereignis-Hook        |

## 4. Statusvariablen und Profile

Der I/O legt keine Statusvariablen und Profile an.  

## 5. WebFront

Entfällt.  

## 6. PHP-Befehlsreferenz

```php
bool PRTG_GetGraph(integer $InstanzID, integer $Type, integer $SensorId, integer $GraphId, integer $Width, integer $Height, integer $Theme, integer $BaseFontSize, bool $ShowLegend)
```
Liefert ein PNG oder SVG Bild eines Graphen als String.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  
Folgende Parameter stehen zur Verfügung:  
 - $Type 1=PNG, 2=SVG  
 - $SensorId Objekt-ID des Sensors  
 - $GraphId Zeitbereich des Graphen: 0=live, 1=last 48 hours, 2=30 days, 3=365 days  
 - $Width Höhe des Graphen in Pixel.  
 - $Height Höhe des Graphen in Pixel.  
 - $Theme Darstellungschema (0,1,2,3)  
 - $BaseFontSize Schriftgröße, 10 ist Standard.  
 - $ShowLegend true für Legende Anzeigen, false zum verbergen.  

## 7. Anhang

### 1. Events von PRTG empfangen  

PRTG kann bei Statusänderungen eines Sensors eine Benachrichtigung an IPS senden, damit IPS den Zustand zeitnah darstellen kann und nicht um das Abfrageintervall verzögert in IPS dargestellt wird.  
![PRTG Benachrichtigung](imgs/prtg_event1.png)  
Hierzu zum zuerst in PRTG eine neue Benachrichtigung angelegt werden, mit folgenden Parametern:  
Name: beliebig  z.B. IPS  
Status: gestartet  
Verschieben: Nachrichten während Pausenzustand verwerfen  
Methode: Immer sofort benachrichtigen, nie zusammenfassen.  
HTTP-Aktion ausführen: URL von IPS nach folgendem Schema eintragen:  
```
http://<ips-ip>:<ips-port>/hook/PRTG<InstanzID>
```
z.B. http://192.168.123.123:3777/hook/PRTG12345  
(Die URL wird in der Konfiguration der Instanz angezeigt.)  
Post-Daten:  
```
%deviceid
%sensorid
```
![PRTG Benachrichtigung](imgs/prtg_event2.png)  
Anschließend müssen noch Trigger definiert werden, welche diese Benachrichtigung auslösen.  
PRTG vererbt Konfigurationen vom obersten Element nach unten. Somit können einzelne Objekte diese Benachrichtigung auslösen, oder auch alle.  
Soll die Benachrichtigung für alle Sensoren erfolgen, so werden die Trigger im Objekt 0 (oberstes Element im Gerätebaum von PRTG) unter Benachrichtigungen angelegt.  

Hier sind vier Trigger anzulegen für die Zustände 'Fehler', 'Warnung', 'Ungewöhnlich' und 'Fehler (teilweise)'.  
Als Aktion wird immer die oben erzeugte Benachrichtigung ausgewählt. Auch wenn die Bedingung nicht mehr zutrifft.  
Wird in IPS eine Benachrichtigung empfangen, so wird dies im Reiter Debug mit 'PRTG EVENT' ausgegeben.  
![PRTG Benachrichtigung](imgs/prtg_event3.png)  

### 2. IPS in PRTG überwachen  

Die Instant stellt einen HTTP-Sensor für PRTG bereit welcher wie folgt in PRTG eingebunden werden kann:  
- Unterhalb des gewünschten Gerätes einen neuen Sensor hinzufügen.  
- Im Suchfeld 'HTTP Daten' eingeben und den Sensor 'HTTP Daten (Erweitert)' auswählen.  
- Als URL wird wieder der Webhook eingetragen:  
```
http://<ips-ip>:<ips-port>/hook/PRTG<InstanzID>
```
http://192.168.123.123:3777/hook/PRTG12345  
(Die URL wird in der Konfiguration der Instanz angezeigt.)  
 
- Anfragemethode bleibt auf GET  

Nach dem erzeugen und speichern der Sensoreinstellungen dauert es einen Augenblick bis PRTG die ersten Werte darstellt.  
![PRTG Sensor](imgs/prtg_sensor1.png)  
![PRTG Sensor](imgs/prtg_sensor2.png)  
![PRTG Sensor](imgs/prtg_sensor3.png)  


## 8. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
