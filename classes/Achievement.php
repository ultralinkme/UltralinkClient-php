<?php

// Copyright © 2016 Ultralink Inc.

require_once classesPath() . '/User.php';

class Achievement
{
    public $user;
    public $type;
    public $time;
    public $unlocked;
    public $progress;

    /* GROUP(Class Functions) user(User) Returns all the achievements for <b>user</b>. */
    public static function allAchievementsForUser( $user )
    {
        global $cMaster;

        $theAchievements = array();

        if( $call = $cMaster->APICall('0.9.1/user/' . $user->ID, 'achievements' ) )
        {
            foreach( json_decode( $call, true ) as $achievement ){ array_push( $theAchievements, Achievement::AWithRow( $achievement, $user ) ); }
        }
        else{ commandResult( 500, "Could not get achievements for " . $user->description() ); }

        return $theAchievements;
    }

    /* GROUP(Class Functions) type(An Achievement type string.) user(User) Returns the achievement of <b>type</b> for <b>user</b>. */
    public static function A( $type, $user )
    {
        global $cMaster;

        $achievement = new self();

        $achievement->user = $user;
        $achievement->type = $type;

        if( $call = $cMaster->APICall('0.9.1/user/' . $user->ID, array('achievement' => $type) ) )
        {
            $cheevo = json_decode( $call, true );

            $achievement->time     = $cheevo['unixTime'];
            $achievement->progress = intval($cheevo['progress']);
            $achievement->unlocked = intval($cheevo['unlocked']);

            return $achievement;
        }

        $achievement->time     = 0;
        $achievement->progress = 0;
        $achievement->unlocked = 0;

        return $achievement;
    }

    public static function AWithRow( $row, $user )
    {
        $achievement = new self();

        $achievement->user     = $user;
        $achievement->type     = $row['achievement'];
        $achievement->time     = $row['unixTime'];
        $achievement->progress = intval($row['progress']);
        $achievement->unlocked = intval($row['unlocked']);

        return $achievement;
    }

    /* GROUP(Status) Returns the progress of the achievment if set and the unlocked status otherwise. */
    public function status()
    {
        $statusValue = 0;

        if( !empty($this->progress) ){ $statusValue = $this->progress; }
        if( !empty($this->unlocked) ){ if( $statusValue == 0 ){ $statusValue = 1; } }

        return $statusValue;
    }

    /* GROUP(Status) Returns the value needed for unlocking. */
    public function unlockRequirement()
    {
        $requiredForUnlock = 1;
        
        switch( $this->type )
        {
            case 'imauser'         : { $requiredForUnlock = 10; } break;
            
            case 'everyonesacritic':
            case 'outofcontext':
            case 'knowledgeseeker' :
            case 'linkedlist'      :
            case 'wordsmith'       :
            case 'librarian'       :
            case 'completionist'   : { $requiredForUnlock =  5; } break;
        }

        return $requiredForUnlock;
    }

    /* GROUP(Status) Returns whether the achievement has been unlocked or not. */
    public function isUnlocked()
    {
        if( $this->status() == $this->unlockRequirement() ){ return 1; }
        
        return 0;
    }
}

?>
