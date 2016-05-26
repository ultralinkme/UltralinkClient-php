<?php

// Copyright © 2016 Ultralink Inc.

class ULBase
{
    private $schema;

    public function &__get( $name )
    { //echo "GET - " . $name . "\n";
        if( $this->ID != -1 ){ $this->populateDetails(); $theElement = $this->$name; return $theElement; }
    }
    public function __set( $name, $value )
    { //echo "SET - " . $name . " - " . print_r( $value, true ) . "\n";
        if( $this->ID != -1 ){ $this->populateDetails(); $this->$name = $value; }
    }

    public function iterateOverDetails( $details )
    {
        $this->schema = array();

        foreach( $details as $key => $value )
        {
            $this->populateDetail( $key, $value );

            array_push( $this->schema, $key );
        }
    }

    protected function populateDetails( $call = "" )
    {
        global $cMaster;

        if( $call != "" )
        {
            if( !isset($this->schema) )
            {
                if( $call = $cMaster->APICall($call, '' ) )
                {
                    $this->iterateOverDetails( json_decode( $call, true ) );
                }
                else{ commandResult( 500, "Could not lookup info using call " . $call ); }
            }
        }
        else{ echo "Need to pass in a call to populateDetails\n"; }
    }
}

?>
