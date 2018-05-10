<?php

declare(strict_types = 1);

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0
 *
 */

/**
 * PRTGPause Trait für ein PRTGSensors und PRTGDevices.
 * 
 * @package       PRTG
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2018 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       1.0 
 * @example <b>Ohne</b>
 */
trait PRTGPause
{
    /**
     * IPS Instanz-Funktion PRTG_SetResume
     * Setzt die Überwachung des Gerätes in PRTG fort.  
     * @access public
     * @return boolean True bei Erfolg, False im Fehlerfall
     */ public function SetResume(): bool
    {
        $Result = $this->SendData('api/pause.htm', [
            'action' => 1,
            'id'     => $this->ReadPropertyInteger('id')
        ]);
        if (array_key_exists('Payload', $Result)) {
            return $this->RequestState();
        }
        return false;
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPause
     * Pausiert die Überwachung des Gerätes in PRTG.  
     * @access public
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPause(): bool
    {
        return $this->SetPauseEx('');
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseEx
     * Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergeben Meldung.  
     * @param string $Message Meldung für PRTG
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseEx(string $Message): bool
    {
        return $this->SetPauseDurationEx(0, $Message);
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseDuration
     * Pausiert die Überwachung des Gerätes in PRTG für die in '$Minutes' angegebene Zeit in Minuten.  
     * @param int $Minutes Pausenzeit in Minuten
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseDuration(int $Minutes): bool
    {
        return $this->SetPauseDurationEx($Minutes, '');
    }

    /**
     * IPS Instanz-Funktion PRTG_SetPauseDuration
     * Pausiert die Überwachung des Gerätes in PRTG mit einer in '$Message' übergeben Meldung für die in '$Minutes' angegebene Zeit in Minuten.
     * @param int $Minutes Pausenzeit in Minuten
     * @param string $Message Meldung für PRTG
     * @return bool True bei Erfolg, False im Fehlerfall
     */
    public function SetPauseDurationEx(int $Minutes, string $Message): bool
    {
        if (!is_string($Message)) {
            trigger_error($this->Translate('Message must be string.'), E_USER_NOTICE);
            return false;
        }
        if (!is_int($Minutes)) {
            trigger_error($this->Translate('Minutes must be integer.'), E_USER_NOTICE);
            return false;
        }
        $QueryData = [
            'action' => 0,
            'id'     => $this->ReadPropertyInteger('id')
        ];

        if ($Minutes > 0) {
            $Uri = 'pauseobjectfor.htm';
            $QueryData['duration'] = $Minutes;
        } else {
            $Uri = 'pause.htm';
        }

        if ($Message != '') {
            $QueryData['pausemsg'] = $Message;
        }

        $Result = $this->SendData($Uri, $QueryData);
        if (array_key_exists('Payload', $Result)) {
            return $this->RequestState();
        }
        return false;
    }

}

/** @} */