[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Module Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FPRTG%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.version&label=Modul%20Version&color=blue)](https://community.symcon.de/t/modul-prtg-prtg-in-ips-einbinden-und-ips-in-prtg-ueberwachen/47105)
[![Symcon Version](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FNall-chan%2FPRTG%2Frefs%2Fheads%2Fmaster%2Flibrary.json&query=%24.compatibility.version&suffix=%3E&label=Symcon%20Version&color=green)](https://www.symcon.de/de/service/dokumentation/installation/migrationen/v80-v81-q3-2025/)  
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Check Style](https://github.com/Nall-chan/PRTG/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/PRTG/actions)
[![Run Tests](https://github.com/Nall-chan/PRTG/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/PRTG/actions)  
[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](#2-spenden)[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](#2-spenden)  

# PRTG Konfigurator  <!-- omit in toc -->  

Vereinfacht das Anlegen von Geräte und Sensor Instanzen in IPS.  

## Inhaltsverzeichnis <!-- omit in toc -->

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen](#4-statusvariablen)
- [5. Visualisierung](#5-visualisierung)
- [6. PHP-Befehlsreferenz](#6-php-befehlsreferenz)
- [7. Aktionen](#7-aktionen)
- [8. Anhang](#8-anhang)
  - [1. Changelog](#1-changelog)
  - [2. Spenden](#2-spenden)
- [9. Lizenz](#9-lizenz)

## 1. Funktionsumfang

- Auslesen und darstellen aller in PRTG und IPS bekannten Geräte und Sensoren.  
- Einfaches Anlegen von neuen Instanzen in IPS.  

## 2. Installation

Dieses Modul ist Bestandteil der [PRTG-Library](../README.md#3-software-installation).  

## 3. Einrichten der Instanzen in IP-Symcon

Nach der installation durch den Modul Store erfolgt eine abfrage ob das enthaltende Konfigurator Modul angelegt werden soll.  
Alternativ ist das Modul im Dialog 'Instanz hinzufügen' unter dem Hersteller 'PRTG' zu finden.  
![Instanz hinzufügen](imgs/add.png)  

Es wird automatisch eine PRTG-IO Instanz erzeugt, wenn noch keine vorhanden ist.  
Erscheint im dem sich öffnenden Konfigurationsformular der Hinweis 'Eine übergeordnete Instanz ist inaktiv', so ist zuerst der IO zu konfigurieren.  
![Konfigurator](imgs/conf1.png)  
Dieser kann über die Schaltfläche 'Gateway konfigurieren' erreicht werden.  
Details zur Konfiguration des [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) sind der Dokumentation des [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) zu entnehmen.

Ist der [IO](../PRTGIO/README.md#4-einrichten-der-instanzen-in-ip-symcon) korrekt verbunden, wird beim öffnen des Konfigurator oder nach dem betätigen der Schaltfläche 'Aktualisieren', folgender Dialog angezeigt.  
Über das selektieren eines Eintrages in der Tabelle und betätigen des dazugehörigen 'Erstellen' Button,  können einzelne Instanzen in IPS angelegt werden.  
Alternativ können auch alle fehlenden Instanzen auf einmal erstellt werden.  
Erstelle Instanzen werden unterhalb einer Kategorie mit dem Namen des jeweiligen Gerätes erstellt.  
Diese Kategorien werden im logischen Baum direkt im Root erstellt.  
Es kann jedoch eine andere Ursprungskategorie ausgewählt werden.  
Diese Struktur dient dem schellen auffinden der erstellten Instanzen im logischen Baum, anschließend können die Instanzen frei im Baum verschoben werden.  
![Konfigurator](imgs/conf2.png)  

## 4. Statusvariablen

Der Konfigurator besitzt keine Statusvariablen.  

## 5. Visualisierung

Der Konfigurator besitzt keine in einer Visualisierung darstellbaren Elemente.  

## 6. PHP-Befehlsreferenz

Der Konfigurator besitzt keine Instanz-Funktionen.  

## 7. Aktionen

Es gibt keine Aktionen für den Konfigurator.  

## 8. Anhang

### 1. Changelog

[Changelog der Library](../README.md#3-changelog)  

### 2. Spenden  
  
Die Library ist für die nicht kommerzielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

[![PayPal.Me](https://img.shields.io/badge/PayPal-Me-lightblue.svg)](https://paypal.me/Nall4chan)  

[![Wunschliste](https://img.shields.io/badge/Wunschliste-Amazon-ff69fb.svg)](https://www.amazon.de/hz/wishlist/ls/YU4AI9AQT9F?ref_=wl_share)  

## 9. Lizenz

IPS-Modul:  
[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
