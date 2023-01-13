[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version 2.50](https://img.shields.io/badge/Modul%20Version-2.50-blue.svg)]()
[![Version 6.2](https://img.shields.io/badge/Symcon%20Version-6.2%20%3E-green.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v61-v62-q2-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/PRTG/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/PRTG/actions)
[![Run Tests](https://github.com/Nall-chan/PRTG/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/PRTG/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#4-spenden)  

# PRTG Device <!-- omit in toc -->
Einbindung eines PRTG-Sensor in IPS.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen und Profile](#4-statusvariablen-und-profile)
- [5. WebFront](#5-webfront)
- [6. PHP-Befehlsreferenz](#6-php-befehlsreferenz)
- [7. Lizenz](#7-lizenz)

## 1. Funktionsumfang

 - Empfangen und darstellen des aktuellen Zustand.  
 - Pausieren und Fortsetzen der Überwachung über die Frontends, Aktionen und PHP-Scripten.  
 - Quittieren von Alarmmeldungen über die Frontends, Aktionen und PHP-Scripten.  

## 2. Installation

 Dieses Modul ist Bestandteil der [PRTG-Library](../README.md#3-software-installation).  

## 3. Einrichten der Instanzen in IP-Symcon

Das Anlegen von neuen Instanzen kann komfortabel über den [PRTG Konfigurator:](../PRTGConfigurator/README.md#3-einrichten-der-instanzen-in-ip-symcon) erfolgen.  

Alternativ ist das Modul im Dialog 'Instanz hinzufügen' unter dem Hersteller 'PRTG' zu finden.  
![Instanz hinzufügen](imgs/add.png)  

Es wird automatisch ein PRTGIO Instanz erzeugt, wenn noch keine vorhanden ist.  
Erscheint im dem sich öffnenden Konfigurationsformular der Hinweis 'Eine übergeordnete Instanz ist inaktiv', so ist zuerst der [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) zu konfigurieren.  
Dieser kann über die Schaltfläche 'Gateway konfigurieren' erreicht werden.  
Details zur Konfiguration des [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) sind der Dokumentation des [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) zu entnehmen.

Folgende Parameter sind in der Instanz zu konfigurieren:  

![Konfigurator](imgs/conf.png)  

**Konfigurationsseite:**  

|    Eigenschaft     |   Typ   | Standardwert |                               Funktion                               |
| :----------------: | :-----: | :----------: | :------------------------------------------------------------------: |
|         id         | integer |              |                       PRTG ObjektID des Sensor                       |
|     AutoRename     |  bool   |     true     |          Instanz automatisch an den Namen in PRTG anpassen           |
|  ShowActionButton  |  bool   |     true     |             Aktionsbutton zum pausieren der Überwachung              |
|   ShowAckButton    |  bool   |     true     |                Aktionsbutton zum Quittieren des Alarm                |
|   ReadableState    |  bool   |     true     |                Status als Klartext in String-Variable                |
| AutoRenameChannels |  bool   |     true     | Statusvariablen der Kanäle automatisch an den Namen in PRTG anpassen |
|    UseInterval     |  bool   |     true     | Abfrageintervall aus Interval benutzen, sonst PRTG-Intervall nutzen  |
|      Interval      | integer |      60      |                     Abfrageintervall in Sekunden                     |

## 4. Statusvariablen und Profile

Folgende Statusvariablen werden automatisch angelegt.  
Zusätzlich werden dynamisch Statusvariablen für die einzelnen Kanäle erstellt.  

|      Name       |   Typ   |     Ident     |                         Beschreibung                         |
| :-------------: | :-----: | :-----------: | :----------------------------------------------------------: |
|     Status      | integer |     State     |                      Status des Sensor                       |
| Status Klartext | string  | ReadableState | Status des Sensor als String wie er von PRTG übertragen wird |
|    Steuerung    | integer | ActionButton  |      Pause / Resume Button zum Steuern der Überwachung       |
| Alarmsteuerung  | integer |   AckButton   |          Bestätigen Button zum Quittieren des Alarm          |



**Profile**:

|      Name      |   Typ   | verwendet von Statusvariablen |
| :------------: | :-----: | :---------------------------: |
|  PRTG.Sensor   | integer |             State             |
|  PRTG.Action   | integer |         ActionButton          |
|    PRTG.Ack    | integer |           AckButton           |
|    PRTG.ms     |  float  |        Sensorvariablen        |
| PRTG.Intensity |  float  |        Sensorvariablen        |
|    PRTG.No     | integer |        Sensorvariablen        |
|   PRTG.MByte   |  float  |        Sensorvariablen        |
|    PRTG.Sec    | integer |        Sensorvariablen        |
|  PRTG.MBitSec  | integer |        Sensorvariablen        |
|  PRTG.kBitSec  | integer |        Sensorvariablen        |
|    PRTG.IpS    | integer |        Sensorvariablen        |
|    PRTG.IpM    | integer |        Sensorvariablen        |
|   PRTG.Items   | integer |        Sensorvariablen        |

## 5. WebFront

Die direkte Darstellung und Steuerung im WebFront ist möglich.  
Hier ein Beispiel eines HTTP Sensors für IPS.  
![WebFront Beispiel](imgs/wf.png)  


## 6. PHP-Befehlsreferenz

```php
bool PRTG_RequestState(integer $InstanzID)
```
Liest den Zustand des Gerätes von PRTG.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_SetResume(integer $InstanzID)
```
Setzt die Überwachung des Gerätes in PRTG fort.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_SetPause(integer $InstanzID)
```
Pausiert die Überwachung des Gerätes in PRTG.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_SetPauseEx(integer $InstanzID, string $Message)
```
Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergebenen Meldung.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_SetPauseDuration(integer $InstanzID, integer $Minutes)
```
Pausiert die Überwachung des Gerätes in PRTG für die in '$Minutes' angegebene Zeit in Minuten.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_SetPauseDurationEx(integer $InstanzID, integer $Minutes, string $Message)
```
Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergebenen Meldung für die in '$Minutes' angegebene Zeit in Minuten.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_AcknowledgeAlarm(integer $InstanzID)
```
Bestätigt den Alarm des Sensor in PRTG.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

```php
bool PRTG_AcknowledgeAlarmEx(integer $InstanzID, string $Message)
```
Bestätigt den Alarm des Sensor in PRTG mit einer in '$Message' übergebenen Meldung.  
Wurde der Befehl erfolgreich ausgeführt, wird `true` zurück gegeben.  
Im Fehlerfall wird eine Warnung erzeugt und `false`zurück gegeben.  

## 7. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
