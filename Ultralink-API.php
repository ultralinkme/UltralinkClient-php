<?php

// Copyright © 2016 Ultralink Inc.

$startTime = microtime(true);

function     topPath(){ return __DIR__;              }
function classesPath(){ return __DIR__ . '/classes'; }
function  configPath(){ return __DIR__ . '/config';  }

require_once  configPath() . '/authLevels.php';

require_once classesPath() . '/Master.php';
require_once classesPath() . '/Ultralink.php';

$shouldExitOnFail = true;

function commandResult( $statusCode, $logString = "" )
{
    global $startTime;
    global $cDB;
    global $cUser;
    global $shouldExitOnFail;

    $executionTime = number_format(round(microtime(true) - $startTime, 5), 5);

    $statusString = "";
    $messageString = "";

    $messageString = "$executionTime $statusString";

    if( isset($cUser) ){ if( $cUser->ID != 0 ){ $messageString .= " [" . $cUser->email . "] "; } }
    if(   $cDB->ID != 0 ){ $messageString .= " {" . $cDB->name    . "} "; }

    $messageString .= " - $logString";

    switch( $statusCode )
    {
        case 400:
        case 401:
        case 403:
        case 404:
        case 500:
        {
            echo $messageString . "\n";
            if( $shouldExitOnFail ){ exit; }
        } break;
    }
}

?>
