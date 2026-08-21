<?php

namespace App\Helper;

class UtilsBase64 {

    public static function encrypt($text = NULL) {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }

    public static function decrypt($text = NULL) {
        return base64_decode(str_pad(strtr($text, '-_', '+/'), strlen($text) % 4, '=', STR_PAD_RIGHT));
    }

    public static function dateDiff($date) {
        $start_date = strtotime(date("Y-m-d", strtotime($date)));
        $start_time = date("H:i:s", strtotime($date));
        $end_date = strtotime(date("Y-m-d", strtotime("now")));
        $end_time = date("H:i:s", strtotime("now"));
        $day = ($end_date - $start_date) / 60 / 60 / 24;
        if ($day >= 0) {
            $time_diff = (strtotime($start_time) - strtotime($end_time)) / 60;
            return $time_diff;
        } else {
            return 0;
        }
    }

}
