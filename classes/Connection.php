<?php

// Copyright © 2016 Ultralink Inc.

class Connection
{
    private $ulA;
    private $ulB;

    private $connection;

    public $dirty = false;

    /* GROUP(Class Functions) ul(Ultralink) Returns an array of connections for the ultralink <b>ul</b>. */
    public static function getConnections( $ul )
    {
        global $cMaster;

        $theConnections = array();

        if( $call = $cMaster->APICall('0.9.1/db/' . $ul->db->ID . '/ul/' . $ul->ID, 'connections' ) )
        {
            foreach( json_decode( $call, true ) as $connection ){ array_push( $theConnections, Connection::connectionFromObject( $ul, $connection ) ); }
        }
        else{ commandResult( 500, "Could not retrieve connections for " . $ul->ID . " - " . $ul->db->name ); }

        return $theConnections;
    }

    /* GROUP(Class Functions) theULA(Ultralink or <ultralink identifier>) theULB(Ultralink or <ultralink identifier>) theConnection(A connection string.) db(<Database or <database identifier>>) Creates a connection <b>theConnection</b> between ultralinks <b>theULA</b> and <b>theULB</b>. */
    public static function C( $theULA, $theULB, $theConnection = null, $db = null )
    {
        global $cMaster;

        $c = new self();

        $aType = gettype($theULA);
        $bType = gettype($theULB);

        if( isset($db) )
        {
            if( $aType != "object" ){ $theULA = Ultralink::U( $theULA, $db ); }
            if( $bType != "object" ){ $theULB = Ultralink::U( $theULB, $db ); }
        }
        else if( ($aType == "object") || ($bType == "object") )
        {
            if( $aType != "object" ){ $theULA = Ultralink::U( $theULA, $theULB->db ); }
            if( $bType != "object" ){ $theULB = Ultralink::U( $theULB, $theULA->db ); }
        }
        else{ commandResult( 500, "Need some sort of database context to make this connection (A: " . print_r( $theULA, true) . ", B: " . print_r( $theULB, true) . ", theDB: " . $db . ")" ); }

        $c->ulA = $theULA;
        $c->ulB = $theULB;

        if( isset($theConnection) )
        {
            $c->connection = $theConnection;
        }
        else
        {
            if( $call = $cMaster->APICall('0.9.1/db/' . $theULA->db->ID . '/ul/' . $theULA->ID, array('connectionA' => $theULA->ID, 'connectionB' => $theULB->ID) ) )
            {
                $details = json_decode( $call, true );

                $c->connection = $details['connection'];
            }
            else
            {
                $c->connection = '';

                $c->dirty = true;
            }
        }

        return $c;
    }

    /* GROUP(Class Functions) ul(Ultralink) connection(A JSON object representing the Connection.) Creates a connection on based on the state in <b>connection<b> object passed in. */
    public static function connectionFromObject( $ul, $connection ){ return Connection::C( $connection['aID'], $connection['bID'], $connection['connection'], $ul->db ); }

    public function __destruct(){ if( isset($this->ulA) ){ unset($this->ulA); } if( isset($this->ulB) ){ unset($this->ulB); } }

    /* GROUP(Information) Returns a string describing this connection. */
    public function description(){ return "Connection " . $this->ulA->ID . " / " . $this->ulB->ID . " / " . $this->connection; }

    /* GROUP(Information) Returns a string that can be used for hashing purposes. */
    public function hashString(){ return $this->ulA->db->ID . "_" . $this->ulA->ID . "_" . $this->ulB->db->ID . "_" . $this->ulB->ID; }

    /* GROUP(Representations) Returns a JSON string representation of this connection. */
    public function json(){ return json_encode( $this->objectify() ); }

    /* GROUP(Representations) Returns a serializable object representation of the connection. */
    public function objectify(){ return array( 'aID' => $this->ulA()->ID, 'bID' => $this->ulB()->ID, 'connection' => $this->connection() ); }

    /* GROUP(Connections) Returns the 'A' ultralink. */
    public function ulA(){ return $this->ulA; }

    /* GROUP(Connections) Returns the 'B' ultralink. */
    public function ulB(){ return $this->ulB; }

    /* GROUP(Connections) Returns the connection string. */
    public function connection(){ return $this->connection; }

    /* GROUP(Connections) v(A connection string.) Sets the connection string to <b>v</b>. */
    public function setConnection( $v ){ if( $this->connection != $v ){ $this->dirty = true; } $this->connection = $v; }

    /* GROUP(Connections) theUL(Ultralink) Returns the ultalink that <b>theUL</b> is connected to through this connection. */
    public function getOtherConnection( $theUL )
    {
             if( $this->ulA->ID == $theUL->ID ){ return $this->ulB; }
        else if( $this->ulB->ID == $theUL->ID ){ return $this->ulA; }

        return null;
    }

    /* GROUP(Actions) other(Connection) Performs a value-based equality check. */
    public function isEqualTo( $other )
    {
        if( ( $this->connection  == $other->connection() ) &&
            ( $this->ulA->ID     == $other->ulA->ID      ) &&
            ( $this->ulB->ID     == $other->ulB->ID      ) &&
            ( $this->ulA->db->ID == $other->ulA->db->ID  ) &&
            ( $this->ulB->db->ID == $other->ulB->db->ID  ) )
        { return true; }
        return false;
    }

    /* GROUP(Actions) Syncs the status of this connection to disk in an efficient way. */
    public function sync()
    {
        global $cMaster;

        if( $this->dirty )
        {
            if( !$cMaster->APICall('0.9.1/db/' . $this->ulA->db->ID . '/ul/' . $this->ulA->ID, array( 'setConnection' => $this->json() ) ) ){ commandResult( 500, "Could not set connection " . $this->description() ); }
            $this->dirty = false;

            return true;
        }

        return false;
    }

    /* GROUP(Actions) Deletes this connection. */
    public function nuke()
    {
        global $cMaster;

        if( !$cMaster->APICall('0.9.1/db/' . $this->ulA->db->ID . '/ul/' . $this->ulA->ID, array( 'removeConnection' => $this->json() ) ) ){ commandResult( 500, "Could remove connection " . $this->description() ); }
    }
}

?>
