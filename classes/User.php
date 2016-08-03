<?php

// Copyright © 2016 Ultralink Inc.

namespace UL;

require_once classesPath() . '/ULBase.php';
require_once classesPath() . '/Achievement.php';
require_once classesPath() . '/Database.php';

class User extends ULBase
{
    public static $cUser;
    public $access_token;

    public $ID;
    public $email;

    protected $achievements    = array();
    protected $databases       = array();
    protected $dbAuth          = array();

    protected static $anonymousUser = "(anonymous)";

    public static $usersID    = array();
    public static $usersEmail = array();

    protected function populateDetails(){ parent::populateDetails('0.9.1/user/' . $this->ID); }

    protected function populateDetail( $key, $value )
    {
        switch( $key )
        {
            case 'billingInfo':
            case 'paymentAllocation':
            case 'grants':
            case 'settings':
            {
                if( empty($value) ){ $this->$key = array(); }
                else{ $this->$key = json_decode( $value, true ); }
            } break;

            default: { $this->$key = $value; }
        }
    }

    public function getDetail( $key )
    {
        switch( $key )
        {
            case 'billingInfo':
            case 'paymentAllocation':
            case 'grants':
            case 'settings':
            {
                return json_encode($this->$key);
            } break;

            default: { return $this->$key; }
        }
    }

    /* GROUP(Class Functions) Returns a list of all the users in the system. */
    public static function allUsers()
    {
        if( $call = Master::$cMaster->APICall('0.9.1/user') )
        {
            return json_decode( $call, true );
        }
        else{ commandResult( 500, "Could not retrieve the accounts" ); }
    }

    /* GROUP(Class Functions) text(A search string.) minAuth(An integer for the minimum auth level.) Returns a list of users based on a search string and minimum authorization level. */
    public static function accountSuggestion( $text, $minAuth )
    {
        if( $call = Master::$cMaster->APICall('0.9.1/user', array('accountSuggestion' => $text, 'minAuth' => $minAuth)) )
        {
            return json_decode( $call, true );
        }
        else{ commandResult( 500, "Couldn't lookup account suggestion for " . $text . " and minAuth " . $minAuth ); }
    }

    /* GROUP(Class Functions) identifier(<user identifier>) at(A master access_token.) Loads the user identified by <b>identifier</b>. */
    public static function U( $identifier = "", $at = "" )
    {
        if( ($identifier == "") || ($identifier === '0') || ($identifier == null) || ($identifier == "undefined") || (empty($identifier)) ){ $identifier = 0; }

             if( !empty(self::$usersID[$identifier])    ){ return self::$usersID[$identifier];    }
        else if( !empty(self::$usersEmail[$identifier]) ){ return self::$usersEmail[$identifier]; }
        else
        {
            $user = new self();
            if( $at != "" ){ $user->access_token = $at; }
            if( $user = $user->loadByIdentifer( $identifier ) ){ return User::enterUser( $user ); }
        }

        return null;
    }

    protected static function UWithIDEmail( $ID, $email )
    {
             if( !empty(self::$usersID[$ID])       ){ return self::$usersID[$ID];       }
        else if( !empty(self::$usersEmail[$email]) ){ return self::$usersEmail[$email]; }
        else
        {
            $user = new self();
            if( $user->loadByIDEmail( $ID, $email ) )
            {
                return User::enterUser( $user );
            }
        }

        return null;
    }

    protected static function enterUser( $user )
    {
        self::$usersID[$user->ID]       = $user;
        self::$usersEmail[$user->email] = $user;

        return $user;
    }

    protected function loadByIdentifer( $identifier = "" )
    {
        if( ($identifier == "") || ($identifier === 0) || ($identifier === '0') || ($identifier == null) || ($identifier == "undefined") || (empty($identifier)) )
        {
            $this->ID    = 0;
            $this->email = User::$anonymousUser;
        }
        else if( is_numeric($identifier) )
        {
            $this->ID = intval($identifier);

            if( $call = Master::$cMaster->APICall('0.9.1/user/' . $this->ID, 'email', $this->access_token ) )
            {
                $this->email = json_decode( $call, true );
            }
            else{ commandResult( 500, "Could not lookup user " . $identifier ); }
        }
        else
        {
            $this->email = $identifier;

            if( $call = Master::$cMaster->APICall('0.9.1/user/' . $this->email, 'ID', $this->access_token ) )
            {
                $this->ID = intval(json_decode( $call, true ));
            }
            else{ commandResult( 500, "Could not lookup user " . $identifier ); }
        }

        return $this;
    }

    protected function loadByIDEmail( $ID, $email )
    {
        if( $ID === 0 ){ $this->ID = 0;           $this->email = User::$anonymousUser; }
                   else{ $this->ID = intval($ID); $this->email = $email;               }
        return $this;
    }

    /* GROUP(Class Functions) identifier(<user identifier>) Sets the current user to the one identified by <b>identifier</b>. */
    public static function currentU( $identifier = "" ){ if( $theUser = User::U( $identifier ) ){ $theUser->setCurrent(); return $theUser; } return null;  }

    /* GROUP(Information) Returns a human-readable description string about this User. */
    public function description(){ return $this->email . " [" . $this->ID . "]"; }

    /* GROUP(Information) Returns the URL to this user gravatar. */
    public function gravatar(){ return "https://secure.gravatar.com/avatar/" . md5( strtolower( trim( $this->email ) ) ) . "?d=" . urlencode( Master::$cMaster->masterPath . "images/anonymous.png" ); }

    /* GROUP(Information) Returns the URL to this user's image. */
    public function image(){ return $this->APICall('image', "Could not retrieve the user image"); }

    /* GROUP(Information) Returns this user's edit count for today. */
    public function todaysEditCount(){ return $this->APICall('todaysEditCount', "Failed to get todaysEditCount"); }

    /* GROUP(Information) todaysCount(Positive integer.) Returns whether <b>todaysCount</b> is under this user's daily edit limit. */
    public function underEditLimit( $todaysCount = "" ){ if( $todaysCount === "" ){ $todaysCount = $this->todaysEditCount(); } if( $todaysCount < $this->dailyEditLimit ){ return true; } return false; }

    /* GROUP(Information) Returns this user's set of applications. */
    public function applications(){ return $this->APICallSub('/applications', '', "Could not retrieve applications"); }

    /* GROUP(Auth) Sets this user's authorization level up a notch. */
    public function promote(){ return $this->APICall('promote', "Could not promote user " . $this->description()); }

    /* GROUP(Auth) Sets this user's authorization level down a notch. */
    public function demote(){ return $this->APICall('demote', "Could not demote user " . $this->description()); }

    /* GROUP(Auth) db(Database) Returns whether this user has any authorization level on database <b>db</b>. */
    protected function getDBAuth( $db )
    {
        if( $this->ID == 0 ){ return false; }
        else
        {
            if( $db->ID == 0 )
            {
                $this->dbAuth[$db->ID] = intval($this->mainlineAuth);
                return true;
            }
            else
            {
                $this->dbAuth[$db->ID] = intval( $this->APICall(array('authForDB' => $db->ID), "Could not retrieve the db " . $db->description() . " auth") );
                return true;
            }
        }

        return false;
    }

    /* GROUP(Auth) db(Database) Returns the authorization level this user has on database <b>db</b>. */
    public function authForDB( $db )
    {
        if( gettype($db) != 'object' ){ $db = Database::DB( $db ); }
        if( empty($this->dbAuth[$db->ID]) ){ if( !$this->getDBAuth( $db ) ){ return 0; } }
        return $this->dbAuth[$db->ID];
    }

    /* GROUP(Auth) Returns the authorization level for this user on the default database. */
    public function authForDefaultDB(){ if( $db = Database::DB( $this->defaultDatabase ) ){ return $this->authForDB( $db ); } return 0; }

    /* GROUP(Auth) db(Database) auth(A auth level integer.) Sets the authorization level for this user to <b>auth</b> on database <b>db</b>. */
    public function setAuthForDB( $db, $auth ){ return $this->APICall(array('setAuthForDB' => $db->ID, 'auth' => $auth), "Couldn't set user auth to " . $auth . " for " . $db->description()); }

    /* GROUP(Notifications) Returns all the current notifications for this user. */
    public function notifications(){ return $this->APICallSub('/notifications', '', "Could not get notifications"); }

    /* GROUP(Notifications) nID(<notification identifier>) Returns the notification for the given ID for this user. */
    public function getNotification( $nID ){ return $this->APICallSub('/notifications/' . $nID, '', "Could not get notification " . $nID); }

    /* GROUP(Notifications) nID(<notification identifier>) Dismisses the notification for the given ID for this user. */
    public function dismissNotification( $nID ){ return $this->APICallSub('/notifications/' . $nID, 'dismiss', "Could not dismiss notification" . $nID); }

    /* GROUP(Achievements) type(achievement type.) Returns whether this user has unlocked an achievement of <b>type</b>. */
    public function hasAchievement( $type ){ if( $this->ID != 0 ){ return $this->getAchievement( $type )->isUnlocked(); } return false; }

    /* GROUP(Achievements) type(achievement type.) Returns the achievement of <b>type</b> for this user. */
    public function getAchievement( $type ){ if( $this->ID != 0 ){ if( empty($achievements[$type]) ){ $achievements[$type] = Achievement::A( $type, $this ); } return $achievements[$type]; } return null; }

    /* GROUP(Achievements) type(achievement type.) Returns the status for the achievement of <b>type</b> for this user. */
    public function getAchievementStatus( $type ){ if( $this->ID != 0 ){ return $this->getAchievement( $type )->status(); } return null; }

    /* GROUP(Achievements) Returns an array containing all the achievements for this user. */
    public function getAllAchievements()
    {
        if( $this->ID != 0 )
        {
            $theAchievements = array();

            foreach( Achievement::allAchievementsForUser( $this ) as $theA )
            {
                $theUnlocked = ''; if( $theA->unlocked == 1 ){ $theUnlocked = $theA->time; }

                $theAchievements[$theA->type] = array( 'progress' => $theA->progress, 'unlocked' => $theUnlocked );
            }

            return $theAchievements;
        }

        return null;
    }

    /* GROUP(Actions) Set this user to be the current user. */
    public function setCurrent(){ User::$cUser = $this; }

    /* GROUP(Actions) Returns an array containing information on all the databases this user has permissions to. */
    public function getDatabases(){ return $this->APICall('databases', "Could not get databases for " . $this->description()); }

    /* GROUP(Actions) nuDefault(<database identifier>) Sets the default database for this user to <b>nuDefault</b>. */
    public function setDefaultDatabase( $nuDefault ){ return $this->APICall(array('setDefaultDatabase' => $nuDefault), "Attempted to change the defaultDatabase to " . $nuDefault); }

    /* GROUP(Actions) update(A JSON object with the new user info.) Updates this use accounts name and/or description ID. */
    public function update( $update ){ return $this->APICall(array('update' => $update), "Could not update information for " . $this->description()); }

    /* GROUP(Actions) deviceID(Device ID string.) type(Device type string.) Register's the device identifed by <b>deviceID</b> of <b>type</b> to this user. */
    public function registerDevice( $deviceID, $type ){ return $this->APICallSub('/notifications', array('registerDevice' => $deviceID, 'type' => $type), "Could not register " . $type . " device " . $deviceID . " for " . $this->description()); }

    /* GROUP(Jobs) theDB(Database) Returns all this user's jobs. */
    public function jobs( $theDB ){ return $this->APICallSub('/jobs/' . $theDB->ID, '', "Could not lookup jobs on " . $theDB->description()); }

    /* GROUP(Jobs) theDB(Database) Returns the count of all unfinished jobs for this user. */
    public function jobsCount( $theDB ){ return $this->APICallSub('/jobs/' . $theDB->ID, 'count', "Could not lookup job count on " . $theDB->description() . " for " . $this->description()); }

    /* GROUP(Jobs) theDB(Database) Returns a list of the potential jobs this user can be assigned to. */
    public function potentialJobs( $theDB ){ return $this->APICallSub('/jobs/' . $theDB->ID, 'potentialJobs', "Could not lookup potential jobs on " . $theDB->description() . " for " . $this->description()); }

    public function APICall( $fields, $error ){ return $this->APICallSub( '', $fields, $error ); }
    public function APICallSub( $sub, $fields, $error )
    {
        $call = Master::$cMaster->APICall('0.9.1/user/' . $this->ID . $sub, $fields );
        
        if( $call !== "" )
        {
            if( $call === true ){ return $call; }
                            else{ return json_decode( $call, true ); }
        }
        else{ commandResult( 500, $error ); }
    }
}

User::$cUser = User::U();

?>
