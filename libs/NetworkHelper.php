<?php

declare(strict_types=1);
trait NetworkHelper
{
    private function getadresses()
    {
        return array_merge(getadresses4(), getadresses6());
    }

    private function getadresses4()
    {
        $all = socket_addrinfo_lookup('');
        $IPs = [];
        foreach ($all as $res) {
            $addrinfo = socket_addrinfo_explain($res);
            if ($addrinfo['ai_family'] == 2) {
                $IPs[] = $addrinfo['ai_addr']['sin_addr'];
            }
        }
        return $IPs;
    }

    private function getadresses6()
    {
        $all = socket_addrinfo_lookup('');
        $IPs = [];
        foreach ($all as $res) {
            $addrinfo = socket_addrinfo_explain($res);
            if ($addrinfo['ai_family'] == 23) {
                $IPs[] = $addrinfo['ai_addr']['sin6_addr'];
            }
        }
        return $IPs;
    }
}
