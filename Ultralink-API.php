<?php

// Copyright © 2016 Ultralink Inc.

namespace UL;

$startTime = microtime(true);

function     topPath(){ return __DIR__;              }
function classesPath(){ return __DIR__ . '/classes'; }
function  configPath(){ return __DIR__ . '/config';  }

require_once  configPath() . '/authLevels.php';

require_once classesPath() . '/Master.php';
require_once classesPath() . '/Ultralink.php';

if( php_sapi_name() == 'cli' )
{
    Master::$printErrors      = true;
    Master::$shouldExitOnFail = true;
}

function commandResult( $statusCode, $logString = "" )
{
    global $startTime;

    $executionTime = number_format(round(microtime(true) - $startTime, 5), 5);

    $statusString  = "";
    $messageString = "";

    $messageString = "$executionTime $statusString";

    if( isset(User::$cUser) ){ if( User::$cUser->ID != 0 ){ $messageString .= " [" . User::$cUser->email . "] "; } }
    if(   Database::$cDB->ID != 0 ){ $messageString .= " {" . Database::$cDB->name    . "} "; }

    $messageString .= " - $logString";

    switch( $statusCode )
    {
        case 400:
        case 401:
        case 403:
        case 404:
        case 500:
        {
            if( !empty(Master::$errorCallback) ){ call_user_func( Master::$errorCallback, $messageString ); }
            if( Master::$printErrors ){ echo $messageString . "\n"; }
            if( Master::$shouldExitOnFail ){ exit; }
        } break;
    }
}

?>
