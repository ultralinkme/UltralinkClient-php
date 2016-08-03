<?php

// Copyright © 2016 Ultralink Inc.

namespace UL;

class Master
{
    public static $cMaster;

    public static $printCallstring = false;
    public static $printBacktrace  = false;
    public static $printRawResult  = false;
    public static $printErrors     = false;

    public static $shouldExitOnFail = false;

    public static $errorCallback = "";

    public $masterPath;
    public $masterDomain;

    public $numberOfCalls = 0;

    /* GROUP(Interfacing) mp(A URL to an Ultralink Master) Creates an Ultralink Master instance located at <b>mp</b>. */
    public static function M( $mp = "https://ultralink.me/" )
    {
        $m = new self();

        if( substr($mp, -1) != '/' ){ $mp = $mp . '/'; }

        $m->masterPath = $mp;
        
        $m->masterDomain = str_replace('https://', '', $m->masterPath  );
        $m->masterDomain = str_replace( 'http://', '', $m->masterDomain);
        $m->masterDomain = str_replace(       '/', '', $m->masterDomain);

        return $m;
    }

    /* GROUP(Interfacing) at(An Ultralink API Key) mp(A URL to an Ultralink Master) Creates an Ultralink Master instance located at <b>mp</b> and authenticates it against the given API Key <b>at</b>. */
    public static function authenticate( $at, $mp = "https://ultralink.me/" )
    {
        $m = Master::M( $mp );
        $m->login($at);

        return $m;
    }

    /* GROUP(Interfacing) at(An Ultralink API Key) Authenticates this Master object with the given API Key. Returns the corresponding User object. */
    public function login( $at )
    {
        if( $call = $this->APICall('0.9.1/user/me', '', $at ) )
        {
            $details = json_decode( $call, true );

            $u = new User();

            $u->ID           = $details['ID'];
            $u->email        = $details['email'];
            $u->access_token = $at;
            
            $u->iterateOverDetails( $details );

            if( User::$cUser->ID == 0 ){ $u->setCurrent(); }
        }
        else{ commandResult( 500, "Could not lookup user with token " . $at ); }

        return User::$cUser;
    }

    /* GROUP(Interfacing) identifier(<database identifier>) Sets the current database to the one identified by <b>identifier</b>. */
    public static function currentMaster( $mp = "https://ultralink.me/" ){ if( $theM = Master::M( $mp ) ){ $theM->setCurrent(); return $theM; } return null;  }

    /* GROUP(Interfacing) Sets this master to be the current master. */
    public function setCurrent(){ Master::$cMaster = $this; }

    public function APICall( $command, $fields = "", $at = "" )
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->masterPath . "API/" . $command );
        curl_setopt($ch, CURLOPT_USERAGENT, 'Ultralink API Client PHP/0.9.1');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $callLineString = "";

        $token_string = "";

        if( !empty(User::$cUser->access_token) ){ $token_string .= 'access_token=' . User::$cUser->access_token; }
                          else if( !empty($at) ){ $token_string .= 'access_token=' . $at;                        }

        $fields_string = $token_string;

        if( !empty($fields) )
        {
            switch( gettype($fields) )
            {
                case 'string':
                {
                    if( !empty($fields_string) ){ $fields_string .= '&'; }
                    $fields_string .= $fields;
                    $callLineString .= $fields;
                } break;

                case 'array':
                {
                    foreach( $fields as $key => $value )
                    {
                        if( !empty($fields_string) ){ $fields_string .= '&'; }
                        $fields_string .= urlencode($key) . '=' . urlencode($value);
                        $callLineString .= $key . ' = ' . $value . ' ';
                    }
                } break;

                case 'object':
                {
                    foreach( $fields as $key => $value)
                    {
                        if( gettype($value) == 'object' )
                        {
                            if( !empty($fields_string) ){ $fields_string .= '&'; }
                            $fields_string .= urlencode($key) . '=' . urlencode(json_encode($value));
                            $callLineString .= $key . ' = ' . $value . ' ';
                        }
                        else if( empty($value) )
                        {
                            if( !empty($fields_string) ){ $fields_string .= '&'; }
                            $fields_string .= urlencode($key);
                            $callLineString .= $key . ' ';
                        }
                        else
                        {
                            if( !empty($fields_string) ){ $fields_string .= '&'; }
                            $fields_string .= urlencode($key) . '=' . urlencode($value);
                            $callLineString .= $key . ' = ' . $value . ' ';
                        }
                    }
                } break;

                default:
                {

                }
            }
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

//        echo $command . " - " . $fields_string. "\n";

        $call = array();
        
        $call['result'] = curl_exec($ch);
        $call['info']   = curl_getinfo($ch);

        curl_close($ch);

//        echo print_r( debug_backtrace(), true );

        if( Master::$printCallstring ){ echo $this->masterPath . "API/" . $command . " - " . $callLineString . "\n"; }
        if( Master::$printBacktrace  ){ foreach( debug_backtrace() as $frame ){ echo $frame['file'] . ":" . $frame['line'] . " " . $frame['class'] . "." . $frame['function'] . "\n"; } }
        if( Master::$printRawResult  ){ echo $call['result'] . "\n"; }

        $this->numberOfCalls++;

        if( $call['info']['http_code'] == 200 )
        {
            if( $call['result'] !== "" ){ return $call['result']; }

            return true;
        }
        else
        {
            $errorString = $this->masterPath . " returned " . $call['info']['http_code'] . " - " . $call['result'];

            if( !empty(Master::$errorCallback) ){ call_user_func( Master::$errorCallback, $errorString ); }
            if( Master::$printErrors ){ echo $errorString . "\n"; }
        }
    }

    /* GROUP(Misc.) Returns all the unique user assocation types. */
    public function associationTypes(){ if( $call = $this->APICall('0.9.1/', 'associationTypes' ) ){ $types = array(); foreach( json_decode( $call, true ) as $type ){ array_push( $types, $type ); } return $types; }else{ commandResult( 500, "Could not retrieve the association types" ); } }

    /* GROUP(Misc.) ultralinks(A JSON array of Ultralink ID numbers.) Returns descriptions for the specified ultralinks. */
    public function specifiedDescriptions( $ultralinks )
    {
        $result = array();
        $changedUltralinks = array();
        
        $i = 0;
        
        while( $i < count($ultralinks) )
        {
            if( $d = Ultralink::U( $ultralinks[$i] )->objectify( true ) ){ array_push( $changedUltralinks, $d );                               }
                                                                     else{ array_push( $changedUltralinks, array( 'ID' => $ultralinks[$i] ) ); }
            $i++;
        }

        $result['changedUltralinks'] = $changedUltralinks;

        return $result;
    }

    /* GROUP(Misc.) Gets the currrent routing table. */
    public function getRoutingTable()
    {
        if( $call = $this->APICall('0.9.1/', 'getRoutingTable' ) )
        {
            $routingTable = array();

            foreach( json_decode( $call, true ) as $rt )
            {
                $rt['interface'] = $rt['interface'] . "API/0.9/";
                array_push( $routingTable, $rt );
            }

            return array( $this->masterDomain => $routingTable );
        }
        else{ commandResult( 500, "Could not get the routing table" ); }
    }

    /* GROUP(Misc.) Returns if this Master exists or not. */
    public function exists(){ if( $call = $this->APICall('0.9.1/', 'exists') ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could test for Master existance" ); } }

    /* GROUP(Misc.) Returns a human-readable description string about this Master. */
    public function description(){ return "Master at " . $this->masterPath; }

    /* GROUP(Syncing Progress) name(The name of the sync) type(The type of syncing count. Can be 'time' or 'count') kind(Each syncing type has a 'Lower' and 'Upper' varient. You can use either or both.) This returns a number for either a syncing count or UNIX timestamp representing progress, ceilings or however you want to use these stored values. */
    public function syncingProgress( $name = "", $type = "time", $kind = "Lower" ){ if( $call = $this->APICall('0.9.1/', array( 'syncingProgress' => $name, 'type' => $type, 'kind' => $kind ) ) ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could not get the syncing progress for " . $name ); } }

    /* GROUP(Syncing Progress) value(The numeric count or time value of the syncing progress) name(The name of the sync) type(The type of syncing count. Can be 'time' or 'count') kind(Each syncing type has a 'Lower' and 'Upper' varient. You can use either or both.) This enters in the syncing progress specifed by 'value'. */
    public function syncingProgressSet( $value, $name = "", $type = "time", $kind = "Lower" ){ if( !$this->APICall('0.9.1/', array( 'syncingProgressSet' => $name, 'type' => $type, 'kind' => $kind, 'value' => $value ) ) ){ commandResult( 500, "Could not set the syncing progress for " . $name ); } }

    /* GROUP(Syncing Progress) name(The name of the sync) Returns the currentlySyncing numeric value. */
    public function syncingCurrently( $name = "" ){ if( $call = $this->APICall('0.9.1/', array( 'syncingCurrently' => $name ) ) ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could not get the currently syncing for " . $name ); } }

    /* GROUP(Syncing Progress) name(The name of the sync) Attempts to acquire the currentlySyncing lock. */
    public function syncingLockAquire( $name = "" ){ if( $call = $this->APICall('0.9.1/', array( 'syncingLockAquire' => $name ) ) ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could not release the sync lock for " . $name ); } }

    /* GROUP(Syncing Progress) name(The name of the sync) force(Indicates whether this should forcefully release the currently syncing lock even if this process doesn't own it.) Attempts to release the currentlySyncing lock. */
    public function syncingLockRelease( $name = "", $force = false ){ if( !$this->APICall('0.9.1/', array( 'syncingLockRelease' => $name, 'force' => $force ) ) ){ commandResult( 500, "Could not release the sync lock for " . $name ); } }

    /* GROUP(Syncing Progress) value(The numeric count or time value of the syncing progress) name(The name of the sync) force(Indicates whether this should forcefully release the currently syncing lock even if this process doesn't own it.) Writes the current syncing progress and attempts to release the currentlySyncing lock. */
    public function syncingComplete( $value, $name = "", $force = false ){ if( !$this->APICall('0.9.1/', array( 'syncingComplete' => $name, 'name' => $name, 'force' => $force ) ) ){ commandResult( 500, "Could not set syncing for " . $name . " to complete." ); } }

    /* GROUP(Syncing Progress) time(A UNIX timestamp) Converts a UNIX timestamp into an equivolent LDAP timestamp. */
    public function LDAPTime( $time = null ){ if( isset($time) ){ return date("YmdHis", $time) . ".0Z"; }else{ return LDAPTime( time() ); } }

    /* GROUP(Invite Codes) Returns a list of all the unredeemed invite codes and the information related to them. */
    public function inviteCodes(){ if( $call = $this->APICall('0.9.1/', 'inviteCodes' ) ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could not retrieve the invite codes" ); } }

    /* GROUP(Invite Codes) name(The person's name.) email(Email address for the person being invited.) Creates a new invite code for a user. */
    public function inviteUser( $name, $email ){ if( $call = $this->APICall('0.9.1/', array('inviteUser' => $email, 'name' => $name) ) ){ return json_decode( $call, true ); }else{ commandResult( 500, "Could not invite " . $name . "/" . $email ); } }
}

Master::$cMaster = Master::M();

?>
