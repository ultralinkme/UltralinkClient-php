<?php

// Copyright © 2016 Ultralink Inc.

class Word
{
    public $ul;
    private $wordString;

    private $caseSensitive;
    private $primaryWord;
    private $commonalityThreshold;

    public $dirty = false;

    /* GROUP(Class Functions) ul(Ultralink) Returns an array of words for the ultralink <b>ul</b>. */
    public static function getWords( $ul )
    {
        global $cMaster;

        $theWords = array();

        if( $call = $cMaster->APICall('0.9.1/db/' . $ul->db->ID . '/ul/' . $ul->ID, 'words' ) )
        {
            foreach( json_decode( $call, true ) as $word ){ array_push( $theWords, Word::wordFromObject( $ul, $word ) ); }
        }
        else{ commandResult( 500, "Could not retrieve words for " . $ul->ID . " - " . $ul->db->name ); }

        return $theWords;
    }

    /* GROUP(Class Functions) theUL(Ultralink) theString(A word string.) theCaseSensitive(Boolean. Indicates whether the word is case-sensitive.) thePrimaryWord(Boolean. Indicates whether the word is primary.) theCommonalityThreshold(A number indicating the commonality threshold of this word.) Creates a word on <b>theUL</b> based on the passed in paramters. */
    public static function W( $theUL, $theString, $theCaseSensitive = null, $thePrimaryWord = null, $theCommonalityThreshold = null )
    {
        global $cMaster;

        $w = new self();

        $w->ul         = $theUL;
        $w->wordString = $theString;

        if( isset($theCaseSensitive) && isset($thePrimaryWord) && isset($theCommonalityThreshold) )
        {
            $w->caseSensitive        = $theCaseSensitive;
            $w->primaryWord          = $thePrimaryWord;
            $w->commonalityThreshold = $theCommonalityThreshold;
        }
        else
        {
            if( $call = $cMaster->APICall('0.9.1/db/' . $theUL->db->ID . '/ul/' . $theUL->ID, array('wordSpecific' => $theString) ) )
            {
                $details = json_decode( $call, true );

                $w->caseSensitive        = $details['caseSensitive'];
                $w->primaryWord          = $details['primaryWord'];
                $w->commonalityThreshold = $details['commonalityThreshold'];
            }
            else
            {
                $w->caseSensitive        = 0;
                $w->primaryWord          = 0;
                $w->commonalityThreshold = 0;

                $w->dirty = true;
            }
        }

        return $w;
    }

    /* GROUP(Class Functions) ul(Ultralink) word(A JSON object representing the Word.) Creates a word on based on the state in <b>word<b> object passed in. */
    public static function wordFromObject( $ul, $word ){ return Word::W( $ul, $word['word'], $word['caseSensitive'], $word['primaryWord'], $word['commonalityThreshold'] ); }

    public function __destruct(){ if( isset($this->ul) ){ unset($this->ul); } }

    /* GROUP(Information) Returns a string describing this word. */
    public function description(){ return "Word " . $this->wordString . " / " . $this->caseSensitive . " / " . $this->primaryWord . " / " . $this->commonalityThreshold; }

    /* GROUP(Information) Returns a string that can be used for hashing purposes. */
    public function hashString(){ return $this->wordString; }

    /* GROUP(Information) Returns this word's string. */
    public function           wordString(){ return $this->wordString;           }

    /* GROUP(Information) Returns this word's case sensitivity value. */
    public function        caseSensitive(){ return $this->caseSensitive;        }

    /* GROUP(Information) Returns this word's primary word value. */
    public function          primaryWord(){ return $this->primaryWord;          }

    /* GROUP(Information) Returns this word's commonality threshold value. */
    public function commonalityThreshold(){ return $this->commonalityThreshold; }

    /* GROUP(Information) v(Boolean. Indicates whether this word is case-sensitive.) Sets this word's case sensitivity value to <b>v</b>. */
    public function        setCaseSensitive( $v ){ if( $this->caseSensitive        != $v ){ $this->dirty = true; } $this->caseSensitive        = $v; }

    /* GROUP(Information) v(Boolean. Indicates whether the word is primary.) Sets this word's primary word value to <b>v</b>. */
    public function          setPrimaryWord( $v ){ if( $this->primaryWord          != $v ){ $this->dirty = true; } $this->primaryWord          = $v; }

    /* GROUP(Information) v(A number indicating the commonality threshold of this word.) Sets this word's commonality threshold value to <b>v</b>. */
    public function setCommonalityThreshold( $v ){ if( $this->commonalityThreshold != $v ){ $this->dirty = true; } $this->commonalityThreshold = $v; }

    /* GROUP(Representations) Returns a JSON string representation of this word. */
    public function json(){ return json_encode( $this->objectify() ); }

    /* GROUP(Representations) Returns a serializable object representation of the word. */
    public function objectify(){ return array( 'word' => $this->wordString(), 'caseSensitive' => $this->caseSensitive(), 'primaryWord' => $this->primaryWord(), 'commonalityThreshold' => $this->commonalityThreshold() ); }

    /* GROUP(Actions) other(Word) Performs a value-based equality check. */
    public function isEqualTo( $other )
    {
        if( ( $this->wordString           == $other->wordString()           ) &&
            ( $this->caseSensitive        == $other->caseSensitive()        ) &&
            ( $this->primaryWord          == $other->primaryWord()          ) &&
            ( $this->commonalityThreshold == $other->commonalityThreshold() ) &&
            ( $this->ul->ID               == $other->ul->ID                 ) &&
            ( $this->ul->db->ID           == $other->ul->db->ID             ) )
        { return true; }
        return false;
    }

    /* GROUP(Actions) Syncs the status of this word to disk in an efficient way. */
    public function sync()
    {
        global $cMaster;

        if( $this->dirty )
        {
            if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'setWord' => $this->json() ) ) ){ commandResult( 500, "Could not set word " . $this->description() . " on to " . $this->ul->ID . " - " . $this->ul->db->name ); }
            $this->dirty = false;

            return true;
        }

        return false;
    }

    /* GROUP(Actions) Deletes this word. */
    public function nuke()
    {
        global $cMaster;

        if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'removeWord' => $this->json() ) ) ){ commandResult( 500, "Could not remove word " . $this->description() . " from " . $this->ul->ID . " - " . $this->ul->db->name ); }
    }
}

?>
