[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version 2.50](https://img.shields.io/badge/Modul%20Version-2.50-blue.svg)]()
[![Version 6.2](https://img.shields.io/badge/Symcon%20Version-6.2%20%3E-green.svg)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v61-v62-q2-2022/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/PRTG/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/PRTG/actions)
[![Run Tests](https://github.com/Nall-chan/PRTG/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/PRTG/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](#4-spenden)  

# Symcon-Modul: PRTG  <!-- omit in toc -->
Einbinden von PRTG Geräten und Sensoren in IPS.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
  - [PRTG IO:](#prtg-io)
  - [PRTG Konfigurator:](#prtg-konfigurator)
  - [PRTG Gerät:](#prtg-gerät)
  - [PRTG Sensor:](#prtg-sensor)
- [2. Voraussetzungen](#2-voraussetzungen)
- [3. Software-Installation](#3-software-installation)
- [4. Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
- [5. Anhang](#5-anhang)
  - [1. GUID der Module](#1-guid-der-module)
  - [2. Hinweise](#2-hinweise)
  - [3. Changelog](#3-changelog)
  - [4. Spenden](#4-spenden)
- [6. Lizenz](#6-lizenz)

## 1. Funktionsumfang

### [PRTG IO:](PRTGIO/)  

 - Schnittstelle zwischen den Device und Sensor Instanzen und PRTG.  
 - Empfangen von Events aus PRTG.  
 - Bereitstellen von IPS Systeminformation für einen PRTG-Sensor.  
 - Abfragen von Graphen aus PRTG.  

### [PRTG Konfigurator:](PRTGConfigurator/)  

 - Auflisten alle in PRTG verfügbaren Geräte und Sensoren.  
 - Erstellen von neuen Device und Sensor Instanzen in IPS.  

### [PRTG Gerät:](PRTGDevice/)  

 - Empfangen und darstellen des aktuellen Zustand.  
 - Pausieren und Fortsetzen der Überwachung über die Frontends, Aktionen und PHP-Scripten.  

### [PRTG Sensor:](PRTGSensor/)  

 - Empfangen und darstellen des aktuellen Zustand.  
 - Pausieren und Fortsetzen der Überwachung über die Frontends, Aktionen und PHP-Scripten.  
 - Quittieren von Alarmmeldungen über die Frontends, Aktionen und PHP-Scripten.  

## 2. Voraussetzungen

 - IP-Symcon ab Version 6.2
 - PRTG

## 3. Software-Installation

  Über den 'Module-Store' in IPS das Modul 'PRTG' hinzufügen.  
   **Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  
![Module-Store](imgs/install.png)  

Die Abfrage ob ein [Konfigurator](PRTGConfigurator/README.md#3-einrichten-der-instanzen-in-ip-symcon) Modul angelegt werden soll, ist zu bestätigen.
Es wird automatisch die Konfiguration für den benötigten [IO](PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) abgefragt.

  
## 4. Einrichten der Instanzen in IP-Symcon

Details sind in der Dokumentation der jeweiligen Module beschrieben.  

In der Dokumentation des [PRTG IO](PRTGIO/) wird im Anhang erläutert wie eine Überwachung von IPS aus PRTG erfolgen kann.  
Ebenso wird dort das Empfangen von Statusänderungen eines Sensors in IPS erläutert, damit IPS den Zustand zeitnah darstellen kann.  

Es wird dingend empfohlen somit zuerst den [PRTG IO](PRTGIO/) zu erstellen und fertig zu konfigurieren, sowie in PRTG alle gewünschten Einstellungen vorzunehmen, bevor weitere Instanzen in IPS über den [PRTG Konfigurator](PRTGConfigurator/) angelegt werden.  


## 5. Anhang

###  1. GUID der Module

 
|       Modul       |     Typ      | Prefix |                  GUID                  |
| :---------------: | :----------: | :----: | :------------------------------------: |
|      PRTG IO      |     I/O      |  PRTG  | {67470842-FB5E-485B-92A2-4401E371E6FC} |
| PRTG Configurator | Configurator |  PRTG  | {32B8B831-91B2-44B5-9B66-9F1685647216} |
|    PRTG Device    |    Device    |  PRTG  | {95C47F84-8DF2-4370-90BD-3ED34C65ED7B} |
|    PRTG Sensor    |    Device    |  PRTG  | {A37FD212-2E5B-4B65-83F2-956CB5BBB2FA} |


### 2. Hinweise  

Der im [PRTG IO](PRTGIO/) verwendete Benutzer sollte in PRTG Administrative Rechte bekommen, um die Überwachung zu steuern und Alarme quittieren zu können.  
Die Kommunikation zwischen IPS und PRTG kann sowohl per HTTP als auch per HTTPS (SSL/TLS) erfolgen.  
Hierzu ist PRTG und die URL im [PRTG IO](PRTGIO/) entsprechend zu anzupassen.
Unverschlüsselte Übertragung sollte niemals zur Kommunikation mit einem externen PRTG-Server genutzt werden, da die Login-Informationen dann nicht verschlüsselt übertragen werden!  

### 3. Changelog

Version 2.50:  
 - Wurde ein Sensor pausiert, so wurden alle Statusvariablen neu als String angelegt und die alten somit gelöscht.  
 - Aktionen hinzugefügt.  
 - Dynamische Konfigurationsformulare und somit einfacher zu konfigurieren.  
 - IO zeigt den Event Webhook für PRTG an.  
 - Event Webhook mit verbesserten NAT Support.  
 - Event Webhook unterstützt abweichenden Port (z.B. für NAT).  

Version 2.30:  
 - Werte für Mbit/sek. und kbit/sek. waren um den Faktor 10 zu groß.  

Version 2.20:  
 - Fehler im IPS-Sensor behoben, wenn PRTG 'Keine Daten' als Nutzdaten übertragen hat.  

Version 2.10:  
 - Fehler im IPS-Sensor behoben, wenn Laufwerke keine Bezeichnung hatten  
 - Fehler im IPS-Sensor behoben, wenn Pagefile genutzt wird  
 
Version 2.00:  
 - Release für IPS 5.1 und den Module-Store   

Version 1.36:
 - Location Feld in create verschoben  

Version 1.35:
 - Fehler im Konfigurator, wenn die Kategorie in der Kategorieauswahl nicht auf oberster Ebene war.  
 - Fehlende Übersetzung ergänzt.  
 - Konfigurator meldet wenn IO nicht aktiv ist.  

Version 1.31:
 - Darstellungsfehler im Konfigurator beseitigt  
 - Formen nutzen jetzt NumberSpinner mit Suffix anstatt IntervalBox  

Version 1.30:  
 - Fehlerbehandlung Datenaustausch überarbeitet  
 - Konfigurator erstellt Instanz unterhalb von Kategorien mit dem Namen des jeweiligen Gerätes  

Version 1.20:  
 - Sensordaten eines SSL-Zertifikatssensor verursachten Fehler  

Version 1.10:  
 - SSL Checks sind desaktivierbar  
 - Sensorwerte mit Laufzeit Tage verursachten Fehler  

Version 1.0:  
 - Erstes offizielles Release  

### 4. Spenden  
  
  Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  


  PayPal:  
<a href="https://www.paypal.com/donate?hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>  

  Wunschliste:  
<a href="https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share" target="_blank"><img src="https://upload.wikimedia.org/wikipedia/commons/4/4a/Amazon_icon.svg" border="0" width="100"/></a>  

## 6. Lizenz

  IPS-Modul:  
  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
 
