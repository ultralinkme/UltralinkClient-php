<?php

// Copyright © 2016 Ultralink Inc.

class Category
{
    public $ul;
    private $categoryString;

    private $primaryCategory;

    public $dirty = false;

    public static $defaultCategory = "(NEEDS CATEGORIZATION)";

    /* GROUP(Class Functions) ul(Ultralink) Returns an array of categories for the ultralink <b>ul</b>. */
    public static function getCategories( $ul )
    {
        global $cMaster;

        $theCategories = array();

        if( $call = $cMaster->APICall('0.9.1/db/' . $ul->db->ID . '/ul/' . $ul->ID, 'categories' ) )
        {
            foreach( json_decode( $call, true ) as $category ){ array_push( $theCategories, Category::categoryFromObject( $ul, $category ) ); }
        }
        else{ commandResult( 500, "Could not retrieve categories for " . $ul->ID . " - " . $ul->db->name ); }

        return $theCategories;
    }

    /* GROUP(Class Functions) theUL(Ultralink) theString(A category string.) thePrimaryCategory(Boolean. Indicates whether this category is primary.) Creates a category <b>theString</b> on the ultralink <b>theUL</b>. */
    public static function C( $theUL, $theString, $thePrimaryCategory = null )
    {
        global $cMaster;

        $c = new self();

        $c->ul             = $theUL;
        $c->categoryString = $theString;

        if( isset($thePrimaryCategory) )
        {
            $c->primaryCategory = $thePrimaryCategory;
        }
        else
        {
            if( $call = $cMaster->APICall('0.9.1/db/' . $theUL->db->ID . '/ul/' . $theUL->ID, array('categorySpecific' => $theString) ) )
            {
                $details = json_decode( $call, true );

                $c->primaryCategory = $details['primaryCategory'];
            }
            else
            {
                $c->primaryCategory = 0;
                
                $c->dirty = true;
            }
        }

        return $c;
    }

    /* GROUP(Class Functions) ul(Ultralink) category(A JSON representation of the Category object.) Creates a category on based on the state in <b>category<b> object passed in. */
    public static function categoryFromObject( $ul, $category ){ return Category::C( $ul, $category['category'], $category['primaryCategory'] ); }

    /* GROUP(Information) Returns the category string. */
    public function categoryString(){ return $this->categoryString; }

    /* GROUP(Information) Returns a string that can be used for hashing purposes. */
    public function hashString(){ return $this->categoryString; }

    /* GROUP(Information) Returns a string describing this category. */
    public function description(){ return "Category " . $this->categoryString . " / " . $this->primaryCategory; }

    /* GROUP(Representations) Returns a JSON string representation of this category. */
    public function json(){ return json_encode( $this->objectify() ); }

    /* GROUP(Representations) Returns a serializable object representation of the category. */
    public function objectify(){ return array( 'category' => $this->categoryString(), 'primaryCategory' => $this->primaryCategory() ); }

    /* GROUP(Primary) Returns whether this category is the primary category. */
    public function primaryCategory(){ return $this->primaryCategory; }

    /* GROUP(Primary) v(Boolean. Indicates whether the category is primary.) Sets this category to be the primary category. */
    public function setPrimaryCategory( $v ){ if( $this->primaryCategory != $v ){ if( $this->dirty ){ $this->dirty = false; }else{ $this->dirty = true; } } $this->primaryCategory = $v; }

    /* GROUP(Primary) Returns the primary category for the ultralink that this category is attached to. */
    public function getCurrentPrimary()
    {
        global $cMaster;

        if( $call = $cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, 'primaryCategory' ) )
        {
            return $call;
        }
        else{ commandResult( 500, "Could not get primary category for " . $this->ul->description() ); }
    }

    public function __destruct(){ if( isset($this->ul) ){ unset($this->ul); } }

    /* GROUP(Actions) other(Category) Performs a value-based equality check. */
    public function isEqualTo( $other )
    {
        if( ( $this->categoryString  == $other->categoryString()  ) &&
            ( $this->primaryCategory == $other->primaryCategory() ) &&
            ( $this->ul->ID          == $other->ul->ID            ) &&
            ( $this->ul->db->ID      == $other->ul->db->ID        ) )
        { return true; }
        return false;
    }

    /* GROUP(Actions) Syncs the status of this category to disk in an efficient way. */
    public function sync()
    {
        global $cMaster;
        
        if( $this->dirty )
        {
            if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'setCategory' => $this->json() ) ) ){ commandResult( 500, "Could not set category " . $this->description() . " on to " . $this->ul->description() ); }
            $this->dirty = false;

            return true;
        }

        return false;
    }

    /* GROUP(Actions) Deletes this category. */
    public function nuke()
    {
        global $cMaster;

        if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'removeCategory' => $this->json() ) ) ){ commandResult( 500, "Could not remove category " . $this->description() . " from " . $this->ul->description() ); }
    }
}

?>
