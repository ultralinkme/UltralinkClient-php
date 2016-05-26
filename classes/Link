<?php

// Copyright © 2016 Ultralink Inc.

class Link
{
    public $ul;
    private $url_ID;
    private $url;
    private $type;

    private $language;
    private $country;
    private $primaryLink;
    private $metaInfo;

    public $dirty = false;

    /* GROUP(Class Functions) ul(Ultralink) Returns an array of links for the ultralink <b>ul</b>. */
    public static function getLinks( $ul )
    {
        global $cMaster;

        $theLinks = array();

        if( $call = $cMaster->APICall('0.9.1/db/' . $ul->db->ID . '/ul/' . $ul->ID, 'links' ) )
        {
            foreach( json_decode( $call, true ) as $link ){ $ul->db->urlIDs[$link['URL']] = $link['ID']; array_push( $theLinks, Link::linkFromObject( $ul, $link ) ); }
        }
        else{ commandResult( 500, "Could not retrieve links for " . $ul->ID . " - " . $ul->db->name ); }

        return $theLinks;
    }

    /* GROUP(Class Functions) theUL(Ultralink) theURL(A URL string or URL ID.) type(A link type string.) language(A language code string.) country(A country code string.) primaryLink(Boolean. Indiciates whether the link is primary.) metaInfo(An object or JSON string representing the metaInfo.) theURL2(A URL string or URL ID.) Creates a link on the ultralink <b>theUL</b>. */
    public static function L( $theUL, $theURL, $type, $language, $country, $primaryLink, $metaInfo, $theURL2 = "" )
    {
        global $cMaster;

        $l = new self();

        $l->ul = $theUL;

        if( is_numeric($theURL) )
        {
            $l->url_ID = intval($theURL);
            if( $theURL2 != "" ){ $l->url = $theURL2; }else{ $l->url = $theUL->db->getURL( $l->url_ID ); }
        }
        else
        {
            if( $theURL2 != "" ){ $l->url_ID = $theURL2; }else{ $l->url_ID = intval($theUL->db->getURLID( $theURL )); }
            $l->url = $theURL;
        }

        if( (is_null($type)) || ($type === '') ){ $type = detectLinkType( $l->url ); }
        $l->type = $type;

        if( isset($language) && isset($country) && isset($primaryLink) && isset($metaInfo) )
        {
//            $l->type        = $type;
            $l->language    = $language;
            $l->country     = $country;
            $l->primaryLink = $primaryLink;
            if( gettype($metaInfo) == "string" ){ $l->metaInfo = $metaInfo; }else{ $l->metaInfo = json_encode( $metaInfo ); }
        }
        else
        {
            if( $call = $cMaster->APICall('0.9.1/db/' . $theUL->db->ID . '/ul/' . $theUL->ID, array('linkSpecific' => $l->url_ID) ) )
            {
                $details = json_decode( $call, true );

                $l->type        = $details['type'];
                $l->language    = $details['language'];
                $l->country     = $details['country'];
                $l->primaryLink = $details['primaryLink'];
                $l->metaInfo    = $details['metaInfo'];
            }
            else
            {
                $l->language    = '';
                $l->country     = '';
                $l->primaryLink = 0;
                $l->metaInfo    = '';

                $l->dirty = true;
            }
        }

        return $l;
    }

    /* GROUP(Class Functions) ul(Ultralink) link(A JSON object representing the link.) Creates a link on based on the state in <b>link<b> object passed in. */
    public static function linkFromObject( $ul, $link ){ return Link::L( $ul, $link['URL'], $link['type'], $link['language'], $link['country'], $link['primaryLink'], $link['metaInfo'], $link['ID'] ); }

    public function __destruct(){ if( isset($this->ul) ){ unset($this->ul); } }

    /* GROUP(Information) Returns a string describing this link. */
    public function description(){ return "Link " . $this->url_ID . " / " . $this->url . " / " . $this->type . " / " . $this->language . " / " . $this->country . " / " . $this->primaryLink . " / " . $this->metaInfo; }

    /* GROUP(Information) Returns a string that can be used for hashing purposes. */
    public function hashString(){ return $this->url_ID . "_" . $this->type; }

    /* GROUP(Information) Returns the ID for this link. */
    public function      url_ID(){ return $this->url_ID;      }

    /* GROUP(Information) Returns the URL for this link. */
    public function         url(){ return $this->url;         }

    /* GROUP(Information) Returns the link type. */
    public function        type(){ return $this->type;        }

    /* GROUP(Information) Returns the language bias for this link. */
    public function    language(){ return $this->language;    }

    /* GROUP(Information) Returns the country bias for this link. */
    public function     country(){ return $this->country;     }

    /* GROUP(Information) Returns whether this link is the primary link. */
    public function primaryLink(){ return $this->primaryLink; }

    /* GROUP(Information) Returns the meta info for this link. */
    public function    metaInfo(){ $v = json_decode( $this->metaInfo, true ); if( ($v == null) || ($v == '""') ){ $v = ""; } return $v; }

//    public function        setType( $v ){ if( $this->type        != $v  ){ $this->dirty = true; } $this->type        = $v; }
    /* GROUP(Information) v(A language code.) Sets the language bias for this link to <b>v</b>. */
    public function    setLanguage( $v ){ if( $this->language    != $v  ){ $this->dirty = true; } $this->language    = $v; }

    /* GROUP(Information) v(A country code.) Sets the country bias for this link to <b>v</b>. */
    public function     setCountry( $v ){ if( $this->country     != $v  ){ $this->dirty = true; } $this->country     = $v; }

    /* GROUP(Information) v(Boolean. Indiciates whether the link is primary.) Sets the primary link value for this link to <b>v</b>. */
    public function setPrimaryLink( $v ){ if( $this->primaryLink != $v  ){ $this->dirty = true; } $this->primaryLink = $v; }

    /* GROUP(Information) v(metaInfo(An object or JSON string representing the metaInfo.)) Sets the meta info for this link to <b>v</b>. */
    public function    setMetaInfo( $v ){ if( gettype($v) != "string" ){ $v = json_encode( $v ); } if( $v == '""' ){ $v = ""; } if( $this->metaInfo != $v ){ $this->dirty = true; } $this->metaInfo = $v; }

    /* GROUP(Representations) Returns a JSON string representation of this link. */
    public function json(){ return json_encode( $this->objectify() ); }

    /* GROUP(Representations) Returns a serializable object representation of the link. */
    public function objectify(){ return array( 'ID' => $this->url_ID(), 'URL' => $this->url(), 'type' => $this->type(), 'language' => $this->language(), 'country' => $this->country(), 'primaryLink' => $this->primaryLink(), 'metaInfo' => $this->metaInfo() ); }

    /* GROUP(Actions) string(A URL fragment string.) Returns whether the given string is contained in this Link's URL. */
    public function urlContains( $urlFragment ){ return strpos( $this->url(), $urlFragment ); }

    public static $defaultType = 'href';

    /* GROUP(Actions) other(Link) Performs a value-based equality check. */
    public function isEqualTo( $other )
    {
        $theMetaInfo = json_encode( $other->metaInfo() ); if( $theMetaInfo == '""' ){ $theMetaInfo = ""; }

        if( ( $this->url_ID      == $other->url_ID()      ) &&
            ( $this->type        == $other->type()        ) &&
            ( $this->language    == $other->language()    ) &&
            ( $this->country     == $other->country()     ) &&
            ( $this->primaryLink == $other->primaryLink() ) &&
            ( $this->metaInfo    == $theMetaInfo          ) &&
            ( $this->ul->ID      == $other->ul->ID        ) &&
            ( $this->ul->db->ID  == $other->ul->db->ID    ) )
        { return true; }
        return false;
    }

    /* GROUP(Actions) Syncs the status of this link to disk in an efficient way. */
    public function sync()
    {
        global $cMaster;

        if( $this->dirty )
        {
            if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'setLink' => $this->json() ) ) ){ commandResult( 500, "Could not set link " . $this->description() . " on to " . $this->ul->description() ); }

            $this->dirty = false;

            return true;
        }

        return false;
    }

    /* GROUP(Actions) Deletes this link. */
    public function nuke()
    {
        global $cMaster;

        if( !$cMaster->APICall('0.9.1/db/' . $this->ul->db->ID . '/ul/' . $this->ul->ID, array( 'removeLink' => $this->json() ) ) ){ commandResult( 500, "Could not remove link " . $this->description() . " from " . $this->ul->ID . " - " . $this->ul->db->name ); }
    }
}

?>
