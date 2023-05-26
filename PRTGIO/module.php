<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace PRTGIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace PRTGIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace PRTGIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

/*
 * @addtogroup prtg
 * @{
 *
 * @package       PRTG
 * @file          module.php
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 * @version       2.52
 *
 */

/**
 * PRTGIO Klasse für die Kommunikation mit PRTG.
 * Erweitert IPSModule.
 *
 * @author        Michael Tröger <micha@nall-chan.net>
 * @copyright     2023 Michael Tröger
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 *
 * @version       2.52
 *
 * @example <b>Ohne</b>
 *
 * @property string $Url
 * @property string $Hash
 * @property self $State
 * @method void RegisterHook(string $WebHook)
 * @method void UnregisterHook(string $WebHook)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class PRTGIO extends IPSModuleStrict
{
    use \PRTGIO\BufferHelper;
    use \PRTGIO\DebugHelper;
    use \PRTGIO\WebhookHelper;

    public const isConnected = IS_ACTIVE;
    public const isInActive = IS_INACTIVE;
    public const isDisconnected = IS_EBASE + 1;
    public const isUnauthorized = IS_EBASE + 2;
    public const isURLnotValid = IS_EBASE + 3;

    private static $http_error =
        [
            418 => ['Could not connect to host, maybe i am a teapot?', self::isDisconnected],
            404 => ['Service not Found', self::isDisconnected],
            401 => ['Unauthorized', self::isUnauthorized],
            500 => ['Server error', self::isDisconnected],
            501 => ['Webhook invalid', self::isDisconnected]
        ];

    private static $SSLError = [
        0  => 'no connect',
        1  => 'unspecified error',
        2  => 'unable to get issuer certificate',
        3  => 'unable to get certificate CRL',
        4  => "unable to decrypt certificate's signature",
        5  => "unable to decrypt CRL's signature",
        6  => 'unable to decode issuer public key',
        7  => 'certificate signature failure',
        8  => 'CRL signature failure',
        9  => 'certificate is not yet valid',
        10 => 'certificate has expired',
        11 => 'CRL is not yet valid',
        12 => 'CRL has expired',
        13 => "format error in certificate's notBefore field",
        14 => "format error in certificate's notAfter field",
        15 => "format error in CRL's lastUpdate field",
        16 => "format error in CRL's nextUpdate field",
        17 => 'out of memory',
        18 => 'self signed certificate',
        19 => 'self signed certificate in certificate chain',
        20 => 'unable to get local issuer certificate',
        21 => 'unable to verify the first certificate',
        22 => 'certificate chain too long',
        23 => 'certificate revoked',
        24 => 'invalid CA certificate',
        25 => 'path length constraint exceeded',
        26 => 'unsupported certificate purpose',
        27 => 'certificate not trusted',
        28 => 'certificate rejected',
        29 => 'subject issuer mismatch',
        30 => 'authority and subject key identifier mismatch',
        31 => 'authority and issuer serial number mismatch',
        32 => 'key usage does not include certificate signing',
        50 => 'application verification failure'
    ];

    /**
     * Interne Funktion des SDK.
     */
    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyBoolean('Open', false);
        $this->RegisterPropertyString('Host', 'http://');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Interval', 0);
        $this->RegisterPropertyBoolean('NoCertCheck', false);
        $this->RegisterPropertyBoolean('NoPeerVerify', false);
        $this->RegisterPropertyBoolean('NoHostVerify', false);
        $this->RegisterPropertyString('ReturnIP', '');
        $this->RegisterPropertyInteger('ReturnPort', 3777);
        $this->RegisterPropertyBoolean('ReturnProtocol', false);
        $this->RegisterAttributeString('ConsumerAddress', 'Invalid');
        $this->Url = '';
        $this->Hash = '';
        $this->State = self::isInActive;
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
    }

    /**
     * Interne Funktion des SDK.
     */
    public function Destroy(): void
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/PRTG' . $this->InstanceID);
        }
        parent::Destroy();
    }

    /**
     * Interne Funktion des SDK.
     */
    public function ApplyChanges(): void
    {
        $this->Url = '';
        $this->Hash = '';

        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterHook('/hook/PRTG' . $this->InstanceID);
        if ($this->CheckHost()) {
            $this->SetSummary($this->Url);
            if (!$this->GetConsumerAddress()) {
                $this->SetStatus(self::$http_error[501][1]);
                return;
            }
            if (!$this->GetPasswordHash()) {
                return;
            }
        } else {
            $this->SetSummary('');
            return;
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @param type $TimeStamp
     * @param type $SenderID
     * @param type $Message
     * @param type $Data
     */
    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                IPS_RequestAction($this->InstanceID, 'KernelReady', true);
                break;
        }
    }
    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident == 'KernelReady') {
            $this->KernelReady();
            return;
        }
    }
    /**
     * IPS Instanz-Funktion PRTG_GetGraph
     * Liefert einen Graphen aus PRTG.
     *
     * @param int  $Type         Typ des Graphen
     *                           enum[1=PNG, 2=SVG]
     * @param int  $SensorId     Objekt-ID des Sensors
     * @param int  $GraphId      Zeitbereich des Graphen
     *                           enum[0=live, 1=last 48 hours, 2=30 days, 3=365 days]
     * @param int  $Width        Höhe des Graphen in Pixel.
     * @param int  $Height       Höhe des Graphen in Pixel.
     * @param int  $Theme        Darstellung
     *                           enum[0,1,2,3]
     * @param int  $BaseFontSize Schriftgröße, 10 ist Standard
     * @param bool $ShowLegend   Legende Anzeigen
     *
     * @return string
     */
    public function GetGraph(int $Type, int $SensorId, int $GraphId, int $Width, int $Height, int $Theme, int $BaseFontSize, bool $ShowLegend): string
    {
        if ($this->State != self::isConnected) {
            return false;
        }
        //'showLegend%3D%271%27+baseFontSize%3D%275%27'
        $QueryData = ['type'         => 'graph',
            'graphid'                => $GraphId,
            'width'                  => $Width,
            'height'                 => $Height,
            'theme'                  => $Theme,
            'refreshable'            => 'true',
            'graphstyling'           => "showLegend='" . (int) $ShowLegend . "' baseFontSize=" . $BaseFontSize . "'",
            'id'                     => $SensorId
        ];
        if ($Type == 1) {
            $URL = $this->CreateQueryURL('chart.png', $QueryData);
        } elseif ($Type == 2) {
            $URL = $this->CreateQueryURL('chart.svg', $QueryData);
        }
        $Timeout = [
            'Timeout' => 5000
        ];
        $this->SendDebug('PRTG Graph URL', $URL, 0);
        return @Sys_GetURLContentEx($URL, $Timeout);
    }

    /**
     * Interne Funktion des SDK.
     *
     * @param string $JSONString Der IPS-Datenstring
     *
     * @return string Die Antwort an den anfragenden Child
     */
    public function ForwardData(string $JSONString): string
    {
        $Json = json_decode($JSONString, true);
        $Result = $this->SendData($Json['Uri'], $Json['QueryData'], $Json['PostData']);
        set_error_handler([$this, 'ModulErrorHandler']);
        switch ($Result['Error']) {
            case self::isConnected:
            case self::isInActive:
            case 200:
                restore_error_handler();
                return serialize($Result);
            case self::isDisconnected:
                trigger_error('IO not connected', E_USER_WARNING);
                break;
            case self::isURLnotValid:
            case 400:
                trigger_error('Bad Request', E_USER_WARNING);
                break;
            case self::isUnauthorized:
            case 401:
                trigger_error('Unauthorized', E_USER_WARNING);
                break;
            case 404: // not Found
                trigger_error('Not found', E_USER_WARNING);
                break;
            case 500:
                trigger_error('Server error', E_USER_WARNING);
                break;
        }
        restore_error_handler();
        return '';
    }

    /**
     * Interne Funktion des SDK.
     *
     * @return string Konfigurationsform
     */
    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if (IPS_GetOption('NATSupport')) {
            if (IPS_GetOption('NATPublicIP') == '') {
                if ($this->ReadPropertyString('ReturnIP') == '') {
                    $Form['actions'][1]['visible'] = true;
                    $Form['actions'][1]['popup']['items'][0]['caption'] = $this->Translate('Error');
                    $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate('NAT support is active, but no public address is set.');
                }
            }
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if (!$Form['actions'][1]['visible']) {
            if (($ConsumerAddress == 'Invalid') && ($this->ReadPropertyBoolean('Open'))) {
                $Form['actions'][1]['visible'] = true;
                $Form['actions'][1]['popup']['items'][0]['caption'] = $this->Translate('Error');
                $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate('Couldn\'t determine webhook');
            }
        }
        $Form['actions'][0]['items'][1]['caption'] = $this->Translate($ConsumerAddress);
        return json_encode($Form);
    }

    /**
     * Interne Funktion des SDK.
     */
    protected function ProcessHookdata(): void
    {
        header('X-Powered-By: IP-Symcon ' . IPS_GetKernelVersion());
        if (!($this->State == self::isConnected)) {
            header('HTTP/1.0 404 Not Found');
            header('Content-type: text/plain');
            echo 'Not Found!' . PHP_EOL;
            echo 'Webhook available.';
            return;
        }
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                if (isset($_GET['graph']) && ($_GET['graph'] == 'png')) {
                    header('Content-type: image/png');
                    echo $this->GetGraph(1, $_GET['id'], $_GET['graphid'], $_GET['width'], $_GET['height'], $_GET['theme'], $_GET['graphstyling'], true);
                    return;
                }
                if (isset($_GET['graph']) && ($_GET['graph'] == 'svg')) {
                    header('Content-Type: image/svg+xml');
                    echo $this->GetGraph(2, $_GET['id'], $_GET['graphid'], $_GET['width'], $_GET['height'], $_GET['theme'], $_GET['graphstyling'], true);
                    return;
                }
                if (isset($_SERVER['HTTP_SENSORID'])) {
                    header('Content-Type: application/json');
                    echo $this->FetchIPSSensorData();
                    return;
                }
                header('HTTP/1.0 404 Not Found');
                header('Content-type: text/plain');
                echo 'Not Found!' . PHP_EOL;
                echo 'Webhook available.';
                return;
            case 'POST':
                $Data = explode("\r\n", rawurldecode(file_get_contents('php://input')));
                $this->SendDebug('PRTG EVENT', $Data, 0);
                foreach ($Data as $ObjId) {
                    $Sensor = ['objid' => (int) $ObjId, 'DataID' => '{45829008-026B-401E-829F-8384DD27619A}'];
                    $this->SendDataToChildren(json_encode($Sensor));
                }
                break;
        }
    }

    /**
     * Interne Funktion des SDK.
     *
     * @param int $InstanceStatus
     */
    protected function SetStatus(int $InstanceStatus): bool
    {
        $this->State = $InstanceStatus;
        return parent::SetStatus($InstanceStatus);
    }

    protected function ModulErrorHandler(int $errno, string $errstr): bool
    {
        echo $errstr . PHP_EOL;
        return true;
    }
    private function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    private function GetConsumerAddress(): bool
    {
        $Port = $this->ReadPropertyInteger('ReturnPort');
        $Protocol = $this->ReadPropertyBoolean('ReturnProtocol') ? 'https' : 'http';
        if (IPS_GetOption('NATSupport')) {
            $ip = $this->ReadPropertyString('ReturnIP');
            if ($ip == '') {
                $ip = IPS_GetOption('NATPublicIP');
                if ($ip == '') {
                    $this->SendDebug('NAT enabled ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('EventHook', 'caption', $this->Translate('NATPublicIP is missing in special switches!'));
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    $this->ShowLastError('Error', $this->Translate('NAT support is active, but no public address is set.'));
                    return false;
                }
            }
            $Debug = 'NAT enabled ConsumerAddress';
        } else {
            $ip = $this->ReadPropertyString('ReturnIP');
            if ($ip == '') {
                $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_bind($sock, '0.0.0.0', 0);
                $Host = parse_url($this->Url);
                @socket_connect($sock, $Host['host'], $Host['port']);
                $ip = '';
                socket_getsockname($sock, $ip);
                @socket_close($sock);
                if ($ip == '0.0.0.0') {
                    $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('EventHook', 'caption', $this->Translate('Invalid'));
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    return false;
                }
            }
            $Debug = 'ConsumerAddress';
        }
        $Url = $Protocol . '://' . $ip . ':' . $Port . '/hook/PRTG' . $this->InstanceID;
        $this->SendDebug($Debug, $Url, 0);
        $this->UpdateFormField('EventHook', 'caption', $Url);
        $this->WriteAttributeString('ConsumerAddress', $Url);
        return true;
    }
    private function ShowLastError(string $ErrorMessage, string $ErrorTitle = 'Error'): void
    {
        IPS_Sleep(500);
        $this->UpdateFormField('ErrorTitle', 'caption', $this->Translate($ErrorTitle));
        $this->UpdateFormField('ErrorText', 'caption', $this->Translate($ErrorMessage));
        $this->UpdateFormField('ErrorPopup', 'visible', true);
    }

    /**
     * Liefert JSON-Daten für eine HTTP-Abfrage von PRTG an den IPS-Webhook.
     *
     * @return string JSON-String für PRTG HTTP-Daten-Sensor
     */
    private function FetchIPSSensorData(): string
    {
        //$this->SendDebug('FetchIPSSensorData', '', 0);
        $i = 0;
        $Threads = IPS_GetScriptThreadList();
        foreach ($Threads as $Thread) {
            $Par = IPS_GetScriptThread($Thread);
            if ($Par['Sender']) {
                $i++;
            }
        }
        $Channels = [];
        $Channels[] = ['channel' => 'PHP Threads', 'value' => $i, 'unit' => 'Count', 'limitmaxwarning' => (int) (count($Threads) / 100 * 50), 'limitmaxerror' => (int) (count($Threads) / 100 * 90), 'LimitMode' => 1];
        $Channels[] = ['channel' => 'IPS Objects', 'value' => count(IPS_GetObjectList()), 'unit' => 'Count', 'limitmaxwarning' => 45000, 'limitmaxerror' => 50000, 'LimitMode' => 1];

        $UtilsId = IPS_GetInstanceListByModuleID('{B69010EA-96D5-46DF-B885-24821B8C8DBD}');
        if (count($UtilsId) > 0) {
            $VarId = @IPS_GetObjectIDByIdent('LicenseSubscription', $UtilsId[0]);
            if ($VarId > 0) {
                $Channels[] = ['channel' => 'License Subscription', 'value' => GetValueInteger($VarId) - time(), 'unit' => 'TimeSeconds', 'limitminwarning' => 30 * 24 * 60 * 60, 'limitminerror' => 0, 'LimitMode' => 1];
            }
        }

        $Messages = UC_GetLogMessageStatistics($UtilsId[0]);
        $TimeSpanSec = (time() - $Messages['ResetTimeStamp']);
        if ($TimeSpanSec > 0) {
            unset($Messages['ResetTimeStamp']);
            $TimeSpan = $TimeSpanSec / 60;
            foreach ($Messages as $MessageTyp => $Value) {
                switch ($MessageTyp) {
                    case 'MessageWarningCount':
                        $MessageChannel = [
                            'limitmaxwarning' => 10,
                            'limitmaxerror'   => 20,
                            'LimitMode'       => 1
                        ];
                        break;
                    case 'MessageErrorCount':
                        $MessageChannel = [
                            'limitmaxwarning' => 5,
                            'limitmaxerror'   => 10,
                            'LimitMode'       => 1
                        ];
                        break;
                    default:
                        $MessageChannel = [];
                }
                $MessageTyp = str_split(substr($MessageTyp, 0, -5), 7);
                $MessageChannel = array_merge($MessageChannel, ['channel' => $MessageTyp[0] . ' ' . $MessageTyp[1], 'value' => (int) ($Value / $TimeSpan), 'unit' => 'Custom', 'customunit' => '#/Min.', 'speedtime' => 'Minute']);
                $Channels[] = $MessageChannel;
            }
        }
        $ProcessInfo = Sys_GetProcessInfo();
        $Channels[] = ['channel' => 'Process Handles', 'value' => $ProcessInfo['IPS_HANDLECOUNT'], 'unit' => 'Count'];
        $Channels[] = ['channel' => 'Process Threads', 'value' => $ProcessInfo['IPS_NUMTHREADS'], 'unit' => 'Count'];
        $Channels[] = ['channel' => 'Process Virtualsize', 'value' => $ProcessInfo['IPS_VIRTUALSIZE'], 'unit' => 'BytesMemory'];
        $Channels[] = ['channel' => 'Process Workingsetsize', 'value' => $ProcessInfo['IPS_WORKINGSETSIZE'], 'unit' => 'BytesMemory'];
        $Channels[] = ['channel' => 'Process Pagefile', 'value' => $ProcessInfo['IPS_PAGEFILE'], 'unit' => 'BytesMemory'];
        $Channels[] = ['channel' => 'Process Count', 'value' => $ProcessInfo['PROCESSCOUNT'], 'unit' => 'Count'];
        $MemoryInfo = Sys_GetMemoryInfo();
        $Channels[] = ['channel' => 'System RAM Physical Free', 'value' => $MemoryInfo['AVAILPHYSICAL'] / $MemoryInfo['TOTALPHYSICAL'] * 100, 'float' => 1, 'unit' => 'Percent', 'limitminwarning' => 20, 'limitminerror' => 5, 'LimitMode' => 1];
        if ($MemoryInfo['TOTALPAGEFILE'] == 0) {
            $MemoryInfo['AVAILPAGEFILE'] = 1;
            $MemoryInfo['TOTALPAGEFILE'] = 1;
        }
        $Channels[] = ['channel' => 'System RAM Pagefile Free', 'value' => $MemoryInfo['AVAILPAGEFILE'] / $MemoryInfo['TOTALPAGEFILE'] * 100, 'float' => 1, 'unit' => 'Percent', 'limitminwarning' => 20, 'limitminerror' => 5, 'LimitMode' => 1];
        $Channels[] = ['channel' => 'System RAM Virtual Free', 'value' => $MemoryInfo['AVAILVIRTUAL'] / $MemoryInfo['TOTALVIRTUAL'] * 100, 'float' => 1, 'unit' => 'Percent', 'limitminwarning' => 20, 'limitminerror' => 5, 'LimitMode' => 1];
        $CPUs = Sys_GetCPUInfo();
        foreach ($CPUs as $Key => $Value) {
            $Name = explode('_', $Key);
            $Channels[] = ['channel' => 'System CPU ' . $Name[1], 'value' => $Value, 'float' => 1, 'unit' => 'CPU', 'limitmaxwarning' => 70, 'limitmaxerror' => 90, 'LimitMode' => 1];
        }
        $Drives = Sys_GetHardDiskInfo();
        foreach ($Drives as $Value) {
            if ($Value['LABEL'] == '') {
                $Name = $Value['LETTER'];
            } else {
                $Name = $Value['LABEL'] . '(' . $Value['LETTER'] . ')';
            }
            $Channels[] = ['channel' => 'Disk ' . $Name, 'value' => $Value['FREE'] / $Value['TOTAL'] * 100, 'float' => 1, 'unit' => 'Percent', 'limitminwarning' => 20, 'limitminerror' => 5, 'LimitMode' => 1];
            $Channels[] = ['channel' => 'Disk ' . $Name . ' Free', 'value' => (int) $Value['FREE'], 'unit' => 'BytesDisk'];
        }
        $Result = ['prtg' => ['error' => 0, 'result' => $Channels]];
        return json_encode($Result);
    }

    /**
     * Sendet Eine Anfrage an PRTG und liefert die Antwort.
     *
     * @param string $Uri       URI der Abfrage
     * @param array  $QueryData Alle mit Allen GET-Parametern
     * @param string $PostData  String mit POST Daten
     *
     * @return array Antwort ale Array
     */
    private function SendData(string $Uri, array $QueryData = [], string $PostData = ''): array
    {
        if ($this->State != self::isConnected) {
            return ['Error' => $this->State];
        }
        $url = $this->CreateQueryURL($Uri, $QueryData);
        $HttpCode = 0;
        $ResultString = $this->SendRequest($url, $HttpCode, $PostData);
        if ($HttpCode == 0) {
            return ['Error' => 404];
        }
        if ($HttpCode >= 400) {
            return ['Error' => $HttpCode];
        }
        if (substr($Uri, -4) == '.htm') {
            $this->SendDebug('Request HTML-Result', $ResultString, 0);
            return ['Payload' => $ResultString, 'Error' => $HttpCode];
        }
        $Result = json_decode($ResultString, true);
        if ($Result === null) {
            $Result['Error'] = 405;
        }
        array_walk_recursive($Result, [$this, 'ResultEncode']);
        $Result['Error'] = 200;
        return $Result;
    }

    /**
     * Callback für array_walk_recursive. Dekodiert HTML-Kodierte Strings.
     *
     * @param mixed  $item
     * @param string $key
     */
    private function ResultEncode(mixed &$item): void
    {
        if (is_string($item)) {
            $item = html_entity_decode($item);
        }
    }

    /**
     * Prüft die Konfiguration der URL für PRTG und schreibt die bereinigte URL in einen InstanceBuffer.
     *
     * @return bool True wenn Host ok, sonst false.
     */
    private function CheckHost(): bool
    {
        if (!$this->ReadPropertyBoolean('Open')) {
            $this->SetStatus(IS_INACTIVE);
            $this->State = self::isInActive;
            return false;
        }
        $URL = $this->ReadPropertyString('Host');
        if ($URL == 'http://') {
            $this->SetStatus(IS_INACTIVE);
            $this->State = self::isInActive;
            return false;
        }
        $Scheme = parse_url($URL, PHP_URL_SCHEME);
        if ($Scheme == null) {
            $Scheme = 'http';
        }
        $Host = parse_url($URL, PHP_URL_HOST);
        if ($Host == null) {
            $this->SetStatus(IS_EBASE + 3);
            $this->State = self::isDisconnected;
            return false;
        }
        $Port = parse_url($URL, PHP_URL_PORT);
        if ($Port == null) {
            $Port = ($Scheme == 'https') ? 443 : 80;
        }
        $Path = parse_url($URL, PHP_URL_PATH);
        if (is_null($Path)) {
            $Path = '';
        } else {
            if ((strlen($Path) > 0) && (substr($Path, -1) == '/')) {
                $Path = substr($Path, 0, -1);
            }
        }
        $this->Url = $Scheme . '://' . $Host . ':' . $Port . $Path . '/';
        return true;
    }

    /**
     * Holt einen PAsswordHash von PRTG.
     *
     * @return bool True bei Erfolg, sonst false
     */
    private function GetPasswordHash(): bool
    {
        $User = $this->ReadPropertyString('Username');
        $Password = $this->ReadPropertyString('Password');
        $QueryData = [
            'username' => $User,
            'password' => $Password
        ];
        $QueryURL = $this->CreateQueryURL('api/getpasshash.htm', $QueryData);
        $HttpCode = 0;
        $Result = $this->SendRequest($QueryURL, $HttpCode);
        if ($Result === '') {
            if ($HttpCode == 0) {
                $this->SetStatus(IS_EBASE + 1);
                $this->State = self::isDisconnected;
            } elseif ($HttpCode == 404) {
                $this->SetStatus(S_EBASE + 1);
                $this->State = self::isDisconnected;
            } else {
                $this->SetStatus(S_EBASE + 2);
                $this->State = self::isUnauthorized;
            }
            $this->Hash = '';
            return false;
        }
        $this->Hash = $Result;
        $this->SetStatus(IS_ACTIVE);
        $this->State = self::isConnected;
        return true;
    }

    /**
     * Erstellt eine komplette URL für die Anfrage an den PRTG-Server.
     *
     * @param string $Uri       URI für die URL
     * @param array  $QueryData Array mit allen GET-Parametern
     *
     * @return string Die fertige URL
     */
    private function CreateQueryURL(string $Uri, array $QueryData): string
    {
        $Hash = $this->Hash;
        if ($Hash != '') {
            $QueryData['username'] = $this->ReadPropertyString('Username');
            $QueryData['passhash'] = $Hash;
        }
        return $this->Url . $Uri . '?' . http_build_query($QueryData);
    }

    /**
     * Sendet Eine Anfrage an PRTG.
     *
     * @param string $Url      URL der Abfrage
     * @param int    $HttpCode Enthält den HTTP-Code der Antwort
     * @param string $PostData String mit POST Daten
     *
     * @return string Antwort als String
     */
    private function SendRequest(string $Url, int &$HttpCode, string $PostData = ''): string
    {
        $this->SendDebug('Request:', $Url, 0);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        if ($PostData != '') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $PostData);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($this->ReadPropertyBoolean('NoCertCheck')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        }
        if ($this->ReadPropertyBoolean('NoHostVerify')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        if ($this->ReadPropertyBoolean('NoPeerVerify')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        $Result = curl_exec($ch);
        $HttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $SSLResult = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
        if (is_bool($Result)) {
            $Result = '';
        }
        curl_close($ch);
        if ($HttpCode == 0) {
            if ($SSLResult !== false) {
                $this->SendDebug('SSL connect', self::$SSLError[$SSLResult], 0);
            }
            $this->SendDebug('Not connected', '', 0);
        } elseif ($HttpCode == 400) {
            $this->SendDebug('Bad Request', $HttpCode, 0);
        } elseif ($HttpCode == 401) {
            $this->SendDebug('Unauthorized Error', $HttpCode, 0);
            return '';
        } elseif ($HttpCode == 404) {
            $this->SendDebug('Not Found Error', $HttpCode, 0);
            return '';
        } else {
            $this->SendDebug('Result:' . $HttpCode, $Result, 0);
        }
        return $Result;
    }
}

/* @} */
