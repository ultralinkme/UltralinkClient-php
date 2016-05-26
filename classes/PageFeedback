<?php

// Copyright © 2016 Ultralink Inc.

class PageFeedback
{
    public $ul;
    private $page_ID;
    private $word;

    private $feedback;

    public $dirty = false;

    /* GROUP(Class Functions) ul(Ultralink) Returns an array of links for the ultralink <b>ul</b>. */
    public static function getPageFeedback( $ul )
    {
        global $cMaster;

        $thePageFeedback = array();

        if( $call = $cMaster->APICall('0.9.1/db/' . $ul->db->ID . '/ul/' . $ul->ID, 'pageFeedbacks' ) )
        {
            foreach( json_decode( $call, true ) as $pf ){ array_push( $thePageFeedback, PageFeedback::pageFeedbackFromObject( $ul, $pf ) ); }
        }
        else{ commandResult( 500, "Could not retrieve page feedbacks for " . $ul->ID . " - " . $ul->db->name ); }

        return $thePageFeedback;
    }

    /* GROUP(Class Functions) theUL(Ultralink) thePageID(A page ID.) theWord(A string of the word that the feedback is on.) theFeedback(A feedback number.) Creates a page feedback for ultralink <b>theUL</b>. */
    public static function PF( $theUL, $thePageID, $theWord, $theFeedback = null )
    {
        global $cMaster;

        $pf = new self();

        $pf->ul      = $theUL;
        $pf->page_ID = $thePageID;
        $pf->word    = $theWord;

        if( isset($theFeedback) )
        {
            $pf->feedback = $theFeedback;
        }
        else
        {
            if( $call = $cMaster->APICall('0.9.1/db/' . $theUL->db->ID . '/ul/' . $theUL->ID, array('pageFeedbackSpecific' => $pf->page_ID, 'word' => $pf->word ) ) )
            {
                $details = json_decode( $call, true );

                $pf->feedback = $details['feedback'];
            }
            else
            {
                $pf->feedback = -1;

                $pf->dirty = true;
            }
        }

        return $pf;
    }

    /* GROUP(Class Functions) ul(Ultralink) pf(A JSON object representing a PageFeedback object.) Creates a page feedback on based on the state in <b>pf<b> object passed in. */
    public static function pageFeedbackFromObject( $ul, $pf ){ return PageFeedback::PF( $ul, $pf['page_ID'], $pf['word'], $pf['feedback'] ); }

    public function __destruct(){ if( isset($this->ul) ){ unset($this->ul); } }

    /* GROUP(Information) Returns a string describing this page feedback. */
    public function description(){ return "Page Feedback " . $this->page_ID . " / " . $this->word . " / " . $this->feedback; }

    /* GROUP(Information) Returns the ID for this page feedback. */
    public function page_ID(){ return $this->page_ID; }

    /* GROUP(Information) Returns the word string for this page feedback. */
    public function word(){ return $this->word; }

    /* GROUP(Information) Returns the feedback value for this page feedback. */
    public function feedback(){ return $this->feedback; }

    /* GROUP(Information) v(A feedback number.) Sets the feedback value for this page feedback to <b>v</b>. */
    public function setFeedback( $v ){ if( $this->feedback != $v ){ $this->dirty = true; } $this->feedback = $v; }

    /* GROUP(Representations) Returns a JSON string representation of this page feedback. */
    public function json(){ return json_encode( $this->objectify() ); }

    /* GROUP(Representations) Returns a serializable object representation of the page feedback. */
    public function objectify(){ return array( 'page_ID' => $this->page_ID(), 'word' => $this->word(), 'feedback' => $this->feedback() ); }

    /* GROUP(Actions) other(PageFeedback) Performs a value-based equality check. */
    public function isEqualTo( $other )
    {
        if( ( $this->feedback == $other->feedback() ) )
        { return true; }
        return false;
    }

    /* GROUP(Actions) Syncs the status of this page feedback to disk in an efficient way. */
    public function sync()
    {
        global $cMaster;

        if( $this->dirty )
        {
            if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'setPageFeedback' => $this->json() ) ) ){ commandResult( 500, "Could not set page feedback " . $this->description() . " on to " . $this->ul->description() ); }
            $this->dirty = false;

            return true;
        }

        return false;
    }

    /* GROUP(Actions) Deletes this page feedback. */
    public function nuke()
    {
        global $cMaster;

        if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'removePageFeedback' => $this->json() ) ) ){ commandResult( 500, "Could not remove page feedback " . $this->description() . " from " . $this->ul->ID . " - " . $this->ul->db->name ); }
    }
}

?>
