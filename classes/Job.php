<?php

// Copyright © 2016 Ultralink Inc.

class Job
{
    public $db;
    public $ID;

    /* GROUP(Class Functions) */
    public static function J( $ID = -1, $db = '' )
    {
        global $cDB;

             if( $db === '' ){ $db = $cDB; }
        else if( gettype($db) != "object" ){ $db = Database::DB( $db ); }

        $j = new self();

        $j->db = $db;
        $j->ID = $ID;

        return $j;
    }

    /* GROUP(Class Functions) ID(<job identifier>) db(<database identifier>) Returns the existing ultralink object specified by <b>ID</b>. Errors out if it does not exist. */
    public static function existingJ( $ID = -1, $db = '' ){ $j = Job::J( $ID, $db ); if( !$j->doesExist() ){ commandResult( 404, $j->description() . " does not exist." ); } return $j; }

    /* GROUP(Information) Returns a string describing this ultralink. */
    public function description(){ return "Job " . $this->db->name . "/" . $this->ID; }

    /* GROUP(Information) Returns whether this job exists on disk. */
    public function doesExist(){ return $this->APICall( '', "Could not test for existence for job " . $this->description() ); }

    /* GROUP(Work) Gets a list of the assignment details for all users who have even completed any work for the specified job or are currently assigned work. */
    public function jobAssignmentDetails(){ return $this->APICall( 'assignments', "Couldn't lookup the job details for " . $this->ID ); }

    /* GROUP(Work) Gets a list of the ultralinks currently assigned to the current user for the given job. */
    public function getJobAssigned(){ return $this->APICallSub( '/ul', '', "Couldn't lookup the assigned for " . $this->ID ); }

    /* GROUP(Work) description_ID(An Ultralink ID.) Gets the work state of an ultralink for the given job if it has been assigned to the current user. */
    public function getWorkState( $description_ID ){ return $this->APICallSub( '/ul/' . $description_ID, '', "Couldn't lookup the work state for  " . $description_ID . " on " . $this->ID ); }

    /* GROUP(Work) description_ID(An Ultralink ID.) operation(A valid operation ID for the job.) input(An input string if the operation requires it.) Commits an operation for an ultralink in a job with an optional input. */
    public function commitWork( $description_ID, $operation, $input ){ return $this->APICallSub( '/ul/' . $description_ID, array('operation' => $operation, 'input' => $input), "Couldn't commit work for  " . $description_ID . " on " . $this->ID ); }

    /* GROUP(Work) theUser(<user identifier>) amount(An optional amount of work to assign.) Attempts to assign an optionally given amount of work to the specified user for the specified job. If no amount is specified then if tries to assign the default amount for the job. */
    public function assignWork( $theUser, $amount = null ){ return $this->APICall( array('assign' => $theUser, 'amount' => $amount), "Couldn't assign work to " . $theUser->description(). " on " . $this->description() ); }

    /* GROUP(Work) theUser(<user identifier>) Removes all the assigned work from <b>theUser</b> for <b>jobID</b>. */
    public function deassignWork( $theUser ){ return $this->APICall( array('deassign' => $theUser), "Couldn't deassign work from " . $theUser->description(). " on " . $this->description() ); }

    /* GROUP(Work) work_LIMIT(A limit of how much assigned work should be returned.) Attempts to get assigned work data associated with the specified job for the current user up to an optionally given limit. */
    public function getWork( $work_LIMIT = -1 ){ global $cUser; return $this->APICall( array('get' => $work_LIMIT), "Couldn't get work for " . $cUser->description(). " from " . $this->description() ); }

    public function APICall( $fields, $error ){ return APICallSub( '', $fields, $error ); }
    public function APICallSub( $sub, $fields, $error )
    {
        global $cMaster;

        if( $call = $cMaster->APICall('0.9.1/db/' . $this->db->ID . '/jobs/' . $this->ID + $sub, $fields ) )
        {
            if( $call === true ){ return $call; }
                            else{ return json_decode( $call, true ); }
        }
        else{ commandResult( 500, $error ); }
    }
}

?>
