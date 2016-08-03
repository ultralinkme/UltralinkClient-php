<?php

// Copyright © 2016 Ultralink Inc.

namespace UL;

require_once configPath() . '/linkTypes.php';

require_once classesPath() . '/Database.php';

require_once classesPath() . '/Word.php';
require_once classesPath() . '/Category.php';
require_once classesPath() . '/Link.php';
require_once classesPath() . '/Connection.php';
require_once classesPath() . '/PageFeedback.php';

class Ultralink
{
    private $preload = array();
    private $fieldLocks = array();

    public $db;
    public $ID;

    private $dirtyNeedsReview = false;

    public function &__get( $name ){ $this->populateDetails( $name ); return $this->$name; }
    public function __set( $name, $value ){ $this->populateDetails( $name ); $this->$name = $value; }

    /* GROUP(Class Functions) ID(<ultralink identifier> or -1 which indicates a new Ultralink) db(Database, <database identifier> or "" which indicates cDB) initialCategory(Category string. Optionally set if you are creating a new Ultralink) needsReview(Review status number. 0 means no review needed) Produces an Ultralink object for the parameters specified.<br><br>Call with the <b>ID</b> parameter (and <b>db</b> if you need to) to simply get an object representing an Ultralink.<br><br>Call with no parameters to create a new Ultralink in cDB (can fill in <b>initialCategory</b> or <b>needsReview</b> if desired).*/
    public static function U( $ID = -1, $db = '', $initialCategory = '', $needsReview = 0 )
    {
        $u = new self();

             if( $db === '' ){ $db = Database::$cDB; }
        else if( gettype($db) != "object" ){ $db = Database::DB( $db ); }
        $u->db = $db;

        if( !is_numeric($ID) ){ $ID = $u->APICall( array( 'lookupVanityName' => $ID ), "Could not lookup vanity name for " . $ID ); }
        $u->ID = $ID;

        if( $needsReview           ){ $u->setNeedsReview( $needsReview );        }
        if( $initialCategory != '' ){ $u->setCategory( $initialCategory, true ); }

        return $u;
    }

    /* GROUP(Class Functions) ID(<ultralink identifier>) db(Database, <database identifier> or "" which indicates cDB) Immediately checks for the existance of the Ultralink specified by <b>ID</b> and returns it. Errors out if it does not exist. */
    public static function existingU( $ID, $db = '' )
    {
        $ul = Ultralink::U( $ID, $db );
        if( !$ul->doesExist() ){ commandResult( 404, $ul->description() . " does not exist." ); }
        return $ul;
    }

    public function __destruct()
    {
        if( property_exists( $this, 'categories' ) )
        {
            if( isset($this->categoriesDead) ){ unset( $this->categoriesDead ); }
            if( isset($this->categories)     ){ unset( $this->categories );     }
        }

        if( property_exists( $this, 'words' ) )
        {
            if( isset($this->wordsDead) ){ unset( $this->wordsDead ); }
            if( isset($this->words)     ){ unset( $this->words );     }
        }

        if( property_exists( $this, 'links' ) )
        {
            if( isset($this->linksDead) ){ unset( $this->linksDead ); }
            if( isset($this->links)     ){ unset( $this->links );     }
        }

        if( property_exists( $this, 'connections' ) )
        {
            if( isset($this->connectionsDead) ){ unset( $this->connectionsDead ); }
            if( isset($this->connections)     ){ unset( $this->connections );     }
        }

        if( property_exists( $this, 'pageFeedback' ) )
        {
            if( isset($this->pageFeedbackDead) ){ unset( $this->pageFeedbackDead ); }
            if( isset($this->pageFeedback)     ){ unset( $this->pageFeedback );     }
        }

        if( isset($this->db) ){ unset( $this->db ); }
    }

    protected function populateDetails( $fieldName )
    {
        $fieldName = str_replace( "Dead", '', $fieldName );

        if( empty($this->fieldLocks[$fieldName]) )
        {
            $this->fieldLocks[$fieldName] = true;

            switch( $fieldName )
            {
                case 'words':
                {
                    if( $this->ID == -1 ){ $this->words = array(); }else{ if( isset($this->preload['words']) ){ $this->words = $this->preload['words']; unset($this->preload['words']); }else{ $this->words = Word::getWords( $this ); } }
                    $this->wordsDead = array();

                    return $this->words;
                } break;

                case 'categories':
                {
                    if( $this->ID == -1 ){ $this->categories = array(); }else{ if( isset($this->preload['categories']) ){ $this->categories = $this->preload['categories']; unset($this->preload['categories']); }else{ $this->categories = Category::getCategories( $this ); } }
                    $this->categoriesDead = array();

                    return $this->categories;
                } break;

                case 'links':
                {
                    if( $this->ID == -1 ){ $this->links = array(); }else{ if( isset($this->preload['links']) ){ $this->links = $this->preload['links']; unset($this->preload['links']); }else{ $this->links = Link::getLinks( $this ); } }
                    $this->linksDead = array();

                    return $this->links;
                } break;

                case 'connections':
                {
                    $this->connections = array();
                    if( $this->ID != -1 ){ if( isset($this->preload['connections']) ){ $this->connections = $this->preload['connections']; unset($this->preload['connections']); }else{ foreach( Connection::getConnections( $this ) as $connection ){ $this->connections[ $connection->hashString() ] = $connection; } } }
                    $this->connectionsDead = array();

                    return $this->connections;
                } break;

                case 'pageFeedback':
                {
                    if( $this->ID == -1 ){ $this->pageFeedback = array(); }else{ if( isset($this->preload['pageFeedback']) ){ $this->pageFeedback = $this->preload['pageFeedback']; unset($this->preload['pageFeedback']); }else{ $this->pageFeedback = PageFeedback::getPageFeedback( $this ); } }
                    $this->pageFeedbackDead = array();

                    return $this->pageFeedback;
                } break;

                case 'time':
                {
                    if( $this->ID == -1 ){ $this->time = 0; }
                    else
                    {
                        if( isset($this->preload['time']) ){ $this->time = $this->preload['time']; unset($this->preload['time']); }
                        else{ $this->time = $this->APICall( 'time', "Could not lookup time for " . $this->description() ); }
                    }

                    return $this->time;
                } break;

                case 'needsReview':
                {
                    if( $this->ID == -1 ){ $this->needsReview = 0; }
                    else
                    {
                        if( isset($this->preload['needsReview']) ){ $this->needsReview = $this->preload['needsReview']; unset($this->preload['needsReview']); }
                        else{ $this->needsReview = $this->APICall( 'needsReview', "Could not lookup needsReview for " . $this->description() ); }
                    }

                    return $this->needsReview;
                } break;
            }
        }

        return null;
    }

    /* GROUP(Information) Returns whether this Ultralink exists in the master's storage. */
    public function doesExist()
    {
        if( $this->ID != -1 )
        {
            $ulObject = $this->APICall( '', "Could not test for existence for " . $this->description() );
//            print_r( $ulObject );

            $theWords        = array(); if( !empty($ulObject['words'])           ){ foreach( $ulObject['words']           as $word       ){ array_push( $theWords,                        Word::wordFromObject( $this, $word       ) ); } }
            $theCategories   = array(); if( !empty($ulObject['category'])        ){ array_push( $theCategories, Category::categoryFromObject( $this, array( 'category' => $ulObject['category'], 'primaryCategory' => '1' ) ) ); }
                                        if( !empty($ulObject['extraCategories']) ){ foreach( $ulObject['extraCategories'] as $category   ){ array_push( $theCategories,           Category::categoryFromObject( $this, array( 'category' => $category, 'primaryCategory' => '0' ) ) ); } }
            $theLinks        = array(); if( !empty($ulObject['urls'])            ){ foreach( $ulObject['urls']            as $type => $linkArray ){ foreach( $ulObject['urls'][$type] as $link ){ $link['type'] = $type; array_push( $theLinks, Link::linkFromObject( $this, $link ) ); } } }
            $theConnections  = array(); if( !empty($ulObject['connections'])     ){ foreach( $ulObject['connections']     as $connection ){ array_push( $theConnections,      Connection::connectionFromObject( $this, $connection ) ); } }
            $thePageFeedback = array(); if( !empty($ulObject['pageFeedback'])    ){ foreach( $ulObject['pageFeedback']    as $pf         ){ array_push( $thePageFeedback, PageFeedback::pageFeedbackFromObject( $this, $pf         ) ); } }

            $this->preload['words']        = $theWords;
            $this->preload['categories']   = $theCategories;
            $this->preload['links']        = $theLinks;
            $this->preload['connections']  = $theConnections;
            $this->preload['pageFeedback'] = $thePageFeedback;

            $this->preload['time']         = $ulObject['time'];
            $this->preload['needsReview']  = $ulObject['needsReview'];

            return true;
        }

        return false;
    }

    /* GROUP(Information) Returns a string that can be used to identify this Ultralink. */
    public function indicatorString(){ return $this->db->ID . "." . $this->ID; }

    /* GROUP(Information) Returns a string describing this Ultralink. */
    public function description(){ return "Ultralink " . $this->db->name . "/" . $this->ID; }

    /* PRIVATE GROUP(Representations) toArray(Array to write the preview info to.) reducedFormat(Boolean. If true, writes back preview info in a reduced format) Adds the preview information returned from previewInfo to the provided associative array. */
    public function addPreviewInfo( &$toArray, $reducedFormat = false )
    {
        $needsReviewSignifier = 'needsReview';
        $categorySignifier    = 'category';
        $primaryWordSignifier = 'primaryWord';
        $imageSignifier       = 'image';
        $metaInfoSignifier    = 'metaInfo';

        if( $reducedFormat )
        {
            $needsReviewSignifier = 'nr';
            $categorySignifier    = 'cat';
            $primaryWordSignifier = 'pri';
            $imageSignifier       = 'img';
            $metaInfoSignifier    = 'meta';
        }

        $pi = $this->previewInfo( $reducedFormat );

        if( !empty($pi[$needsReviewSignifier]) ){ $toArray[$needsReviewSignifier] = $pi[$needsReviewSignifier]; }
//        if( !empty($pi[$primaryWordSignifier]) ){ $toArray[$primaryWordSignifier] = $pi[$primaryWordSignifier]; }
        if( $pi[$primaryWordSignifier] !== ''  ){ $toArray[$primaryWordSignifier] = $pi[$primaryWordSignifier]; }
        if( !empty($pi[$categorySignifier])    ){ $toArray[$categorySignifier]    = $pi[$categorySignifier];    }
        if( !empty($pi[$imageSignifier])       ){ $toArray[$imageSignifier]       = $pi[$imageSignifier];       }
        if( !empty($pi[$metaInfoSignifier])    ){ $toArray[$metaInfoSignifier]    = $pi[$metaInfoSignifier];    }
    }

    /* GROUP(Representations) reducedFormat(Boolean. If true, returns preview info in a reduced format) Returns the set of information sufficient to presenting a small preview of an Ultralink: Primary Word, Primary Category, primary image, primary image meta info and whether it needs review or not. */
    public function previewInfo( $reducedFormat = false ){ return $this->APICall( array( 'previewInfo' => $reducedFormat ), "Could not get preview info for " . $this->description() ); }

    /* GROUP(Representations) Returns a string representing the status of this Ultralink at this point in time. */
    public function statusRecord()
    {
        $record = $this->objectify( true, true );
        unset($record['time']);
        return json_encode( $record );
    }

    /* GROUP(Representations) withPageFeedback(Boolean. True will add all page feedback for this Ultralink to the result.) addConnectionInfo(Boolean. True will add preview info to every Connection.) addAffiliateKeys(Boolean. True will add the Database affiliate keys to the result.) removeDefaultValues(Boolean. True will remove attributes that are set to the default values.) addPermissions(Boolean. True will add the relevant permission data for cUser on this Ultralink.) reducedFormat(Boolean. If true, returns the object in a reduced format) Returns a JSON string representation of this Ultralink. */
    public function json( $withPageFeedback = false, $addConnectionInfo = false, $addAffiliateKeys = false, $addPermissions = false, $removeDefaultValues = false, $reducedFormat = false ){ return json_encode( $this->objectify( $withPageFeedback, $addConnectionInfo, $addAffiliateKeys, $addPermissions, $removeDefaultValues, $reducedFormat ) ); }

    /* GROUP(Representations) withPageFeedback(Boolean. True will add all page feedback for this Ultralink to the result.) addConnectionInfo(Boolean. True will add preview info to every Connection.) addAffiliateKeys(Boolean. True will add the Database affiliate keys to the result.) removeDefaultValues(Boolean. True will remove attributes that are set to the default values.) addPermissions(Boolean. True will add the relevant permission data for cUser on this Ultralink.) reducedFormat(Boolean. If true, returns the object in a reduced format) Returns a serializable object representation of the Ultralink. Parameters indicate what sets of additional information should be included and in what format.<br><br><b>addAffiliateKeys</b> and <b>addPermissions</b> are usually only used when visually editing the Ultralink.*/
    public function objectify( $withPageFeedback = false, $addConnectionInfo = false, $addAffiliateKeys = false, $addPermissions = false, $removeDefaultValues = false, $reducedFormat = false )
    {
        global $authLevels;

        // First test to see if the Ultralink is even there still
        if( !$this->doesExist() ){ return false; }

        $needsReviewSignifier = 'needsReview';
        $categorySignifier    = 'category';

        $wordSignifier                 = 'word';
        $caseSensitiveSignifier        = 'caseSensitive';
        $primarySignifier              = 'primaryWord';
        $commonalityThresholdSignifier = 'commonalityThreshold';

        $urlSignifier      = 'URL';
        $languageSignifier = 'language';
        $countrySignifier  = 'country';
        $metaInfoSignifier = 'metaInfo';

        $connectionSignifier = 'connection';

        $primaryWordSignifier = 'primaryWord';
        $primaryLinkSignifier = 'primaryLink';
        $imageSignifier       = 'image';

        if( $reducedFormat )
        {
            $needsReviewSignifier = 'nr';
            $categorySignifier    = 'cat';

            $caseSensitiveSignifier        = 'cs';
            $primarySignifier              = 'pri';
            $commonalityThresholdSignifier = 'ct';

            $languageSignifier = 'lang';
            $countrySignifier  = 'geo';
            $metaInfoSignifier = 'meta';

            $connectionSignifier = 'con';

            $primaryWordSignifier = 'pri';
            $primaryLinkSignifier = 'pri';
            $imageSignifier       = 'img';
        }

        // Categories
        $thePrimaryCategory = $this->getPrimaryCategory(); if( $thePrimaryCategory ){ $thePrimaryCategory = $thePrimaryCategory->categoryString(); }else{ $thePrimaryCategory = Category::$defaultCategory; }

        $fullResult = array( 'ID' => intval($this->ID), 'time' => $this->time, $needsReviewSignifier => intval($this->needsReview), $categorySignifier => $thePrimaryCategory );

        if( $removeDefaultValues )
        {
            if(                     empty($fullResult[$needsReviewSignifier]) ){ unset($fullResult[$needsReviewSignifier]); }
            if( $fullResult[$categorySignifier] == Category::$defaultCategory ){ unset($fullResult[$categorySignifier]);    }
        }

        $fullExtraCategories = array();
        foreach( $this->categories as $category )
        {
            if( !$category->primaryCategory() ){ array_push( $fullExtraCategories, $category->categoryString() ); }
        }
        if( !$removeDefaultValues || count($fullExtraCategories) ){ $fullResult['extraCategories'] = $fullExtraCategories; }

        // Words
        $fullWords = array();
        foreach( $this->words as $word )
        {
            $theWord = array( $wordSignifier => $word->wordString(), $caseSensitiveSignifier => intval($word->caseSensitive()), $primarySignifier => intval($word->primaryWord()), $commonalityThresholdSignifier => intval($word->commonalityThreshold()) );

            if( $removeDefaultValues )
            {
                if( empty($theWord[$caseSensitiveSignifier])        ){ unset($theWord[$caseSensitiveSignifier]); }
                if( empty($theWord[$primarySignifier])              ){ unset($theWord[$primarySignifier]); }
                if( empty($theWord[$commonalityThresholdSignifier]) ){ unset($theWord[$commonalityThresholdSignifier]); }
            }

            array_push( $fullWords, $theWord );
        }
        if( !$removeDefaultValues || count($fullWords) ){ $fullResult['words'] = $fullWords; }

        // Links
        $fullResult['urls'] = array();
        foreach( $this->links as $link )
        {
            if( empty($fullResult['urls'][$link->type()]) ){ $fullResult['urls'][$link->type()] = array(); }

            $theMetaInfo = json_encode( $link->metaInfo() ); if( $theMetaInfo == '""' ){ $theMetaInfo = ""; }
            $theLink = array( 'ID' => intval($link->url_ID()), $urlSignifier => $link->url(), $languageSignifier => $link->language(), $countrySignifier => $link->country(), $primarySignifier => intval($link->primaryLink()), $metaInfoSignifier => $theMetaInfo );

            if( $removeDefaultValues )
            {
                if( empty($theLink[$languageSignifier])    ){ unset($theLink[$languageSignifier]);    }
                if( empty($theLink[$countrySignifier])     ){ unset($theLink[$countrySignifier]);     }
                if( empty($theLink[$primaryLinkSignifier]) ){ unset($theLink[$primaryLinkSignifier]); }
                if( empty($theLink[$metaInfoSignifier])    ){ unset($theLink[$metaInfoSignifier]);    }
            }

            array_push( $fullResult['urls'][$link->type()], $theLink );
        }
        if( $removeDefaultValues && !count($fullResult['urls']) ){ unset( $fullResult['urls'] ); }

        //Connections
        $fullConnections = array();
        foreach( $this->connections as $connection )
        {
            $theConnection = array( 'aID' => intval($connection->ulA()->ID), $connectionSignifier => $connection->connection(), 'bID' => intval($connection->ulB()->ID) );

            // Get useful preview data for visualizing the connections to this Ultralink.
            if( $addConnectionInfo )
            {
                $connection->getOtherConnection( $this )->addPreviewInfo( $theConnection, $reducedFormat );
            }

            if( $removeDefaultValues )
            {
                if( empty($theConnection[$connectionSignifier]) ){ unset($theConnection[$connectionSignifier]); }
            }

            array_push( $fullConnections, $theConnection );
        }
        if( !$removeDefaultValues || count($fullConnections) ){ $fullResult['connections'] = $fullConnections; }

        // Page Feedback
        if( $withPageFeedback )
        {
            $fullPageFeedback = array();

            foreach( $this->pageFeedback as $pf )
            {
                array_push( $fullPageFeedback, array( 'page_ID' => $pf->page_ID(), 'word' => $pf->word(), 'feedback' => $pf->feedback() ) );
            }
            if( !$removeDefaultValues || count($fullPageFeedback) ){ $fullResult['pageFeedback'] = $fullPageFeedback; }
        }

        if( $addAffiliateKeys )
        {
            $fullResult['amazonAffiliateTag'] = '';
            $fullResult['linkshareID']        = '';
            $fullResult['phgID']              = '';
            $fullResult['eBayCampaign']       = '';

            $affiliateKeys = $this->db->affiliateKeys;

            if( empty($affiliateKeys['amazonAffiliateTag']) ){ $fullResult['amazonAffiliateTag'] = ''; }else{ $fullResult['amazonAffiliateTag'] = $affiliateKeys['amazonAffiliateTag']; }
            if( empty($affiliateKeys['linkshareID'])        ){ $fullResult['linkshareID']        = ''; }else{ $fullResult['linkshareID']        = $affiliateKeys['linkshareID']; }
            if( empty($affiliateKeys['phgID'])              ){ $fullResult['phgID']              = ''; }else{ $fullResult['phgID']              = $affiliateKeys['phgID']; }
            if( empty($affiliateKeys['eBayCampaign'])       ){ $fullResult['eBayCampaign']       = ''; }else{ $fullResult['eBayCampaign']       = $affiliateKeys['eBayCampaign']; }
        }

        if( $addPermissions )
        {
            if( (User::$cUser->ID != 0) && ($this->db->ID == 0) && (User::$cUser->mainlineAuth == $authLevels['Contributor']) )
            {
                $fullResult['grants'] = User::$cUser->grants;

                $currentDailyEditCount = User::$cUser->todaysEditCount();

                //$fullResult['hasSufficientGrant']    = User::$cUser->hasSufficientGrantForUltralinks( $this->ID );
                $fullResult['underEditLimit']        = User::$cUser->underEditLimit( $currentDailyEditCount );
                //$fullResult['underImpactLimit']      = User::$cUser->underImpactLimit( $this->ID );

                $fullResult['currentDailyEditCount'] = $currentDailyEditCount;
                $fullResult['currentDailyEditLimit'] = User::$cUser->dailyEditLimit;
            }
            else
            {
                $fullResult['auth'] = User::$cUser->authForDB( $this->db );
            }
        }

        return $fullResult;
    }

    /* GROUP(Analytics) Returns the number of websites this Ultralink is currently found on. */
    public function websiteCount(){ return $this->APICall( 'websiteCount', "Could not get the website count for " . $this->description() ); }

    /* GROUP(Analytics) Returns the number of pages this Ultralink is currently found on. */
    public function pageCount(){ return $this->APICall( 'pageCount', "Could not get the page count for " . $this->description() ); }

    /* GROUP(Analytics) Returns the number of instances this Ultralink are currently found. */
    public function instanceCount(){ return $this->APICall( 'instanceCount', "Could not get the instance count for " . $this->description() ); }

    /* GROUP(Analytics) Returns a list of users who have made manual contributions to this Ultralink. */
    public function contributors(){ return $this->APICall( 'contributors', "Could not get the contributors for " . $this->description() ); }

    /* GROUP(Analytics) Returns some statistical information about this Ultralink's occurrences. */
    public function stats(){ return $this->APICall( 'stats', "Could not retrieve stats for " . $this->description() ); }

    /* GROUP(Analytics) timeScale(The time scale of the data we are looking at. Values can be <b>monthly</b>, <b>daily</b> or <b>hourly</b>.) timeDuration(The numeric length of the time slice that the data should examine in units defined by <b>timeScale</b>.) Returns chart data for historical occurrence counts for a specified time period. */
    public function occurrences( $timeScale, $timeDuration ){ return $this->APICall( array('occurrences' => "", 'timeScale' => $timeScale, 'timeDuration' => $timeDuration), "Could not retrieve occurrences for " . $this->description() ); }

    /* GROUP(Analytics) pagePath(A URL path fragment determing the scope of the results.) restrictToThis(Boolean. Indicates whether the results should be restricted to only the exact pagePath) timeRestrict(Determines if the results should be restricted in any way. Values can be cache or alltime.) timeScale(The time scale of the data we are looking at. Values can be <b>monthly</b>, <b>daily</b> or <b>hourly</b>.) timeDuration(The numeric length of the time slice that the data should examine in units defined by <b>timeScale</b>.) getAggregation(Boolean. Determines if the extra aggreggation information should be include) Returns a set of data outlining click activity for this Ultralink in a specifc URL path fragment within a specific time span. Can set whether the results should be restricted to only data connected to what is in the current content cache. Can restrict the results to only the exact path instead of all the paths under it. Can also add specific data on aggreggation. */
    public function path( $pagePath, $restrictToThis, $timeRestrict, $timeScale, $timeDuration, $getAggregation ){ return $this->APICall( array('path' => "", 'timeScale' => $timeScale, 'timeDuration' => $timeDuration, 'pagePath' => $pagePath, 'restrictToThis' => $restrictToThis, 'resultRestrict' => $timeRestrict, 'getAggregation' => $getAggregation), "Could not retrieve path for " . $this->description() ); }

    /* GROUP(Analytics) website_ID(A website ID.) offset(Pagination offset.) limit(Pagination limit. Default: <b>100</b>, Max: <b>1000</b>.) Returns a list of pages on a given website that this Ultralink is known to be on. */
    public function instancePages( $website_ID, $offset = 0, $limit = 100 )
    {
        if( empty($offset) ){ $offset =   0; }
        if( empty($limit)  ){ $limit  = 100; } if( $limit > 1000 ){ $limit = 1000; }

        return $this->APICall( array('instancePages' => $website_ID, 'offset' => $offset, 'limit' => $limit), "Could not get the instance pages for " . $this->description() );
    }

    /* GROUP(Analytics) offset(Pagination offset.) limit(Pagination limit. Default: <b>100</b>, Max: <b>1000</b>.) Returns a list of websites that this Ultralink is known to be on. */
    public function instanceWebsites( $offset = 0, $limit = 100 )
    {
        if( empty($offset) ){ $offset =   0; }
        if( empty($limit)  ){ $limit  = 100; } if( $limit > 1000 ){ $limit = 1000; }

        return $this->APICall( array('instanceWebsites' => "", 'offset' => $offset, 'limit' => $limit), "Could not get the instance websites for " . $this->description() );
    }

    /* GROUP(Analytics) commons(An array of commonality description objects describing the calculations.) pullLinkType(A link type determining what link should be pulled out and included in the answer sets.) getIntersect(A boolean indicating if an intersection array of all the commonality sets should also be returned.) Returns a result set for each commonality description objects passed in. Returns resultant sets that include the desired link type. Can optionally return an intersection of the resultant sets as well. Resultant sets sorted by commonality value descending. */
    public function connectionCommon( $commons, $pullLinkType, $getIntersect = false ){ return $this->APICall( array('connectionCommon' => $commons, 'uID' => $this->ID, 'pullLinkType' => $pullLinkType, 'getIntersect' => $getIntersect), "Could not get the connection common for " . $this->description() ); }

    /* GROUP(Analytics) Returns a set of Ultralinks that have a common word with this one. */
    public function wordCommon(){ return $this->APICall( 'wordCommon', "Could not get common Ultralinks for " . $this->description() ); }

    /* GROUP(Analytics) Returns a top 20 list of Ultralinks that appear in the same fragments as this one ordered by occurrence number descending. */
    public function related(){ return $this->APICall( 'related', "Could not get related Ultralinks for " . $this->description() ); }

    /* GROUP(Actions) nr(Review status number. 0 means no review needed) Sets this Ultralink's needsReview value. */
    public function setNeedsReview( $nr = 0 ){ if( $nr != $this->needsReview ){ $this->dirtyNeedsReview = true; } $this->needsReview = $nr; }

    /* GROUP(Actions) modificationID(A determination status. Values can be <b>GOOD</b>, <b>BAD</b> and <b>REVERTED</b>.) determination() Sets the status of the specified modification and sets the state of the Ultralink to what it was before the modification if the determination is "REVERTED". */
    public function modificationDetermination( $modificationID, $determination ){ return $this->APICall( array( 'modificationDetermination' => $modificationID, 'determination' => $determination ), "Could not set determination on " . $this->description() ); }

    /* GROUP(Actions) destDB(<database identifier>) Creates a copy of this Ultralink in another specified database. */
    public function copyIntoDB( $destDB )
    {
        $nuUL = Ultralink::U( -1, $destDB );

        foreach( $this->words      as $word     ){ $nuUL->setWord( $word->wordString(), $word->caseSensitive(), $word->primaryWord(), $word->commonalityThreshold() ); }
        foreach( $this->categories as $category ){ $nuUL->setCategory( $category->categoryString(), $category->primaryCategory() ); }
        foreach( $this->links      as $link     ){ $nuUL->setLink( $link->url(), $link->type(), $link->language(), $link->country(), $link->primaryLink(), $link->metaInfo() ); }

        $nuUL->sync();
    }

    /* GROUP(Actions) mergeIDs(A JSON object listing the IDs of the Ultralinks to merge into this one.) Merges all the given Ultralinks into this one. */
    public function merge( $mergeIDs ){ return $this->APICall( array( 'merge' => $mergeIDs ), "Could not perform merge into " . $this->description() ); }

    /* GROUP(Actions) Removes everything from this Ultralink. */
    public function blankSlate( $pageFeedbackToo = false )
    {
        $this->removeAllWords();
        $this->removeAllCategories();
        $this->removeAllLinks();
        $this->removeAllConnections();

        if( $pageFeedbackToo ){ $this->removeAllPageFeedback(); }
    }

    /* GROUP(Actions) nuState(A JSON object representing the Ultralink state.) Sets the data for this Ultralink from the information found in <b>nuState</b>. */
    public function setFromObject( $nuState )
    {
        if( !empty($nuState['time']) ){ $this->time = $nuState['time']; }
        $this->needsReview = $nuState['needsReview'];

        $this->blankSlate( true );

        // Words
        if( !empty($nuState['words']) ){ foreach( $nuState['words'] as $word ){ $this->setWordFromObject( $word ); } }

        // Categories
        if( $nuState['category'] != Category::$defaultCategory ){ $this->setCategory( $nuState['category'], true ); }
        if( !empty($nuState['extraCategories']) ){ foreach( $nuState['extraCategories'] as $category ){ $this->setCategory( $category ); } }

        // Links
        if( !empty($nuState['urls']) ){ foreach( $nuState['urls'] as $type => $tlink ){ foreach( $tlink as $link ){ $link['type'] = $type; $this->setLinkFromObject( $link ); } } }

        // Connections
        if( !empty($nuState['connections']) ){ foreach( $nuState['connections'] as $connection ){ $this->setConnectionFromObject( $connection ); } }

        // Page Feedback
        if( !empty($nuState['pageFeedback']) ){ foreach( $nuState['pageFeedback'] as $pf ){ $this->setPageFeedbackFromObject( $pf ); } }
    }

    /* GROUP(Actions) nuState(A JSON object representing the Ultralink state.) Sets the data for this Ultralink from the information found in <b>nuState</b> and syncs the changes to disk. */
    public function syncFromObject( $nuState )
    {
        $this->setFromObject( $nuState );
        $this->sync();
    }

    /* GROUP(Actions) Prints currently un-sync'd changes. */
    public function printCurrentModifications()
    {
        if( $this->dirtyNeedsReview )
        {
            echo "\tDIFFERENCE: (" . $this->ID . ") - needsReview " . $this->needsReview . "\n";
        }

        if( property_exists( $this, 'categories' ) )
        {
            foreach( $this->categoriesDead as $i => &$theCategory ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theCategory->description() . " present here but not in master\n"; }
            foreach( $this->categories     as &$theCategory ){ if( $theCategory->dirty ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theCategory->description() . " not found\n"; } }
        }

        if( property_exists( $this, 'words' ) )
        {
            foreach( $this->wordsDead as $i => &$theWord ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theWord->description() . " present here but not in master\n"; }
            foreach( $this->words     as &$theWord ){ if( $theWord->dirty ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theWord->description() . " not found\n"; } }
        }

        if( property_exists( $this, 'links' ) )
        {
            foreach( $this->linksDead as $i => &$theLink ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theLink->description() . " present here but not in master\n"; }
            foreach( $this->links     as &$theLink ){ if( $theLink->dirty ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theLink->description() . " not found\n"; } }
        }

        if( property_exists( $this, 'connections' ) )
        {
            foreach( $this->connectionsDead as $i => &$theConnection ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theConnection->description() . " present here but not in master\n"; }
            foreach( $this->connections     as &$theConnection ){ if( $theConnection->dirty ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $theConnection->description() . " not found\n"; } }
        }

        if( property_exists( $this, 'pageFeedback' ) )
        {
            foreach( $this->pageFeedbackDead as $i => &$thePageFeedback ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $thePageFeedback->description() . " present here but not in master\n"; }
            foreach( $this->pageFeedback     as &$thePageFeedback ){ if( $thePageFeedback->dirty ){ echo "  DIFFERENCE: (" . $this->ID . ") - " . $thePageFeedback->description() . " not found\n"; } }
        }
    }

    /* GROUP(Actions) outputDifference(Boolean. If true, then echo the differences from the previous state.) Syncs the changes to this Ultralink to disk in an efficient manner. */
    public function sync( $outputDifference = false )
    {
        global $authLevels;

        if( User::$cUser->authForDB( $this->db ) <= $authLevels['Contributor'] )
        {
            if( $call = Master::$cMaster->APICall('0.9.1/db/' . $this->db->ID . '/ul/' . $this->ID, array( 'modify' => $this->json() ) ) )
            {
                $this->ID = intval($call);
            }
            else{ commandResult( 500, "Could not create a new Ultralink in " . $this->db->description() ); }
        }
        else
        {
            $wordDifferential         = array();
            $connectionDifferential   = array();
            $pageFeedbackDifferential = array();

            $needsReviewDifferent   = false;
            $categoriesDifferent    = false;
            $urlsDifferent          = false;

            // Freshly created Ultralink, here on this machine
            if( $this->ID == -1 )
            {
                $categoryString = Category::$defaultCategory; if( $primaryCategory = $this->getPrimaryCategory() ){ $categoryString = $primaryCategory->categoryString(); }

                if( $call = Master::$cMaster->APICall('0.9.1/db/' . $this->db->ID . '/ul', array( 'create' => "", 'category' => $categoryString, 'needsReview' => $this->needsReview ) ) )
                {
                    $this->ID = intval($call);
                }
                else{ commandResult( 500, "Could not create a new Ultralink in " . $this->db->description() ); }
            }

            if( $this->dirtyNeedsReview )
            {
                $this->APICall( array( 'reviweStatus' => $this->needsReview ), "Could not sync the state of needsReview on " . $this->description() );

                $this->dirtyNeedsReview = false;
                $needsReviewDifferent = true;

                if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - needsReview " . $this->needsReview . "\n"; }
            }

            if( property_exists( $this, 'categories' ) )
            {
                foreach( $this->categoriesDead as $i => &$theCategory ){ $theCategory->nuke(); array_splice( $this->categoriesDead, $i, 1 ); if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theCategory->description() . " present here but not in master\n"; } $categoriesDifferent = true; }
                foreach( $this->categories as &$theCategory ){ if( $theCategory->sync() ){ if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theCategory->description() . " not found\n"; } $categoriesDifferent = true; } }
            }

            if( property_exists( $this, 'words' ) )
            {
                foreach( $this->wordsDead as $i => &$theWord ){ $theWord->nuke(); array_splice( $this->wordsDead, $i, 1 ); if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theWord->description() . " present here but not in master\n"; } $wordDifferential[$theWord->wordString()] = 1; }
                foreach( $this->words as &$theWord ){ if( $theWord->sync() ){ if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theWord->description() . " not found\n"; } $wordDifferential[$theWord->wordString()] = 1;  } }
            }

            if( property_exists( $this, 'links' ) )
            {
                foreach( $this->linksDead as $i => &$theLink ){ $theLink->nuke(); array_splice( $this->linksDead, $i, 1 ); if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theLink->description() . " present here but not in master\n"; } $urlsDifferent = true; }
                foreach( $this->links as &$theLink ){ if( $theLink->sync() ){ if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theLink->description() . " not found\n"; } $urlsDifferent = true; } }
            }

            if( property_exists( $this, 'connections' ) )
            {
                foreach( $this->connectionsDead as $i => &$theConnection ){ $theConnection->nuke(); unset( $this->connectionsDead[ $i ] ); if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theConnection->description() . " present here but not in master\n"; } $connectionDifferential[$theConnection->getOtherConnection($this)->ID] = 1; }
                foreach( $this->connections as $i => &$theConnection ){ if( $theConnection->sync() ){ if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $theConnection->description() . " not found\n"; } $connectionDifferential[$theConnection->getOtherConnection($this)->ID] = 1; } }
            }

            if( property_exists( $this, 'pageFeedback' ) )
            {
                foreach( $this->pageFeedbackDead as $i => &$thePageFeedback ){ $thePageFeedback->nuke(); array_splice( $this->pageFeedbackDead, $i, 1 ); if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $thePageFeedback->description() . " present here but not in master\n"; } $pageFeedbackDifferential[$thePageFeedback->page_ID()] = 1; }
                foreach( $this->pageFeedback as &$thePageFeedback ){ if( $thePageFeedback->sync() ){ if( $outputDifference ){ echo "\tDIFFERENCE: (" . $this->ID . ") - " . $thePageFeedback->description() . " not found\n"; } $pageFeedbackDifferential[$thePageFeedback->page_ID()] = 1; } }
            }

            $wordDifferential         = array_keys($wordDifferential);         if( count($wordDifferential)         == 0 ){ $wordDifferential         = false; }
            $connectionDifferential   = array_keys($connectionDifferential);   if( count($connectionDifferential)   == 0 ){ $connectionDifferential   = false; }
            $pageFeedbackDifferential = array_keys($pageFeedbackDifferential); if( count($pageFeedbackDifferential) == 0 ){ $pageFeedbackDifferential = false; }

            $wordsChanged        =          ($wordDifferential != false) ?  1 : 0;
            $categoriesChanged   =       ($categoriesDifferent ==  true) ?  2 : 0;
            $urlsChanged         =             ($urlsDifferent ==  true) ?  4 : 0;
            $connectionsChanged  =    ($connectionDifferential != false) ?  8 : 0;
            $pageFeedbackChanged = ($pageFeedbackDifferential !=  false) ? 16 : 0;
            $needsReviewChanged  =      ($needsReviewDifferent ==  true) ? 32 : 0;

            return $wordsChanged + $categoriesChanged + $urlsChanged + $connectionsChanged + $pageFeedbackChanged + $needsReviewChanged;
        }
    }

    /* GROUP(Actions) Deletes this Ultralink. */
    public function nuke(){ return $this->APICall( 'nuke', "Could not nuke " . $this->description() ); }

// Words

    /* GROUP(Words) Returns the Primary Word if set. If not, returns the first word listed. */
    public function getFirstWord(){ $word = null; if( !($word = $this->getPrimaryWord()) ){ if( count($this->words) ){ $word = $this->words[0]; } } return $word; }

    /* GROUP(Words) Returns the Primary Word or null if not set. */
    public function getPrimaryWord(){ foreach( $this->words as &$theWord ){ if( $theWord->primaryWord() ){ return $theWord; } } return null; }

    /* GROUP(Words) string(A word string.) nuke(Boolean. If true then remove the Word object found from the Ultralink.) Returns the word on this Ultralink associated with <b>string</b>. */
    public function getWord( $wordString, $nuke = false ){ foreach( $this->words as $w => &$theWord ){ if( $theWord->wordString() == $wordString ){ if( $nuke ){ array_splice( $this->words, $w, 1 ); } return $theWord; } } return null; }

    /* GROUP(Words) string(A word string.) caseSensitive(Boolean. 1 indicates that this Word is case sensitive.) primaryWord(Boolean. 1 indicates that this word is the primary on this Ultralink.) commonalityThreshold(A number indicating the commonality threshold.) nuke(Boolean. If true then remove the Word object found from the Ultralink.) Returns the word on this Ultralink associated with the parameters. */
    public function getWordFull( $wordString, $caseSensitive, $primaryWord, $commonalityThreshold, $nuke = false ){ foreach( $this->words as $w => &$theWord ){ if( ($theWord->wordString() == $wordString) && ($theWord->caseSensitive() == $caseSensitive) && ($theWord->primaryWord() == $primaryWord) && ($theWord->commonalityThreshold() == $commonalityThreshold) ){ if( $nuke ){ array_splice( $this->words, $w, 1 ); } return $theWord; } } return null; }

    /* GROUP(Words) o(A JSON object representing the Word.) Sets a word on this Ultralink based on the information in <b>o</b>. */
    public function setWordFromObject( $o )
    {
        if( !empty($o['word']) )
        {
            $caseSensitive        = null;
            $primaryWord          = null;
            $commonalityThreshold = null;

            if( isset($o['caseSensitive'])        ){ $caseSensitive        = $o['caseSensitive'];        }
            if( isset($o['primaryWord'])          ){ $primaryWord          = $o['primaryWord'];          }
            if( isset($o['commonalityThreshold']) ){ $commonalityThreshold = $o['commonalityThreshold']; }

            return $this->setWord( $o['word'], $caseSensitive, $primaryWord, $commonalityThreshold );
        }

        return null;
    }

    /* GROUP(Words) string(A word string.) caseSensitive(Boolean. 1 indicates that this Word is case sensitive.) primaryWord(Boolean. 1 indicates that this word is the primary on this Ultralink.) commonalityThreshold(A number indicating the commonality threshold.) Sets a word on this Ultralink. Adds it if it does exist yet and modifies it if it does. */
    public function setWord( $wordString, $caseSensitive = null, $primaryWord = null, $commonalityThreshold = null )
    {
        if( $theWord = $this->getWord( $wordString ) )
        {
            if( !is_null($caseSensitive)        ){ $theWord->setCaseSensitive( $caseSensitive );               }
            if( !is_null($primaryWord)          ){ $theWord->setPrimaryWord( $primaryWord );                   }
            if( !is_null($commonalityThreshold) ){ $theWord->setCommonalityThreshold( $commonalityThreshold ); }

            return $theWord;
        }

        if( is_null($caseSensitive)        ){ $caseSensitive        = 0; }
        if( is_null($primaryWord)          ){ $primaryWord          = 0; }
        if( is_null($commonalityThreshold) ){ $commonalityThreshold = 0; }

        $newWord = Word::W( $this, $wordString, $caseSensitive, $primaryWord, $commonalityThreshold );
        $newWord->dirty = true;

        foreach( $this->wordsDead as $d => $deadWord )
        {
            if( $newWord->isEqualTo( $deadWord ) )
            {
                array_splice( $this->wordsDead, $d, 1 );
                array_push( $this->words, $deadWord );
                return $deadWord;
            }
        }

        array_push( $this->words, $newWord );
        return $newWord;
    }

    /* GROUP(Words) w(Word or a word string.) Removes word or string <b>w</b>. */
    public function removeWord( $w )
    {
        if( gettype($w) == "object" ){ $w = $w->wordString(); }
        array_push( $this->wordsDead, $this->getWord( $w, true ) );
    }

    /* GROUP(Words) Removes all existing words from this Ultralink. */
    public function removeAllWords(){ $this->wordsDead = array_merge( $this->wordsDead, $this->words ); $this->words = array(); }

    /* GROUP(Words) w(Word or a word string.) nuWord(Word or a word string.) caseSensitive(Boolean. 1 indicates that this Word is case sensitive.) primaryWord(Boolean. 1 indicates that this word is the primary on this Ultralink.) commonalityThreshold(A number indicating the commonality threshold.) Replaces word <b>w</b> with a new word. */
    public function replaceWord( $w, $nuWord, $caseSensitive = 0, $primaryWord = 0, $commonalityThreshold = 0 )
    {
        $this->removeWord( $w );

        if( gettype($nuWord) == "object" )
        {
            $nuWord->dirty = true;
            array_push( $this->words, $nuWord );
        }
        else{ $this->setWord( $nuWord, $caseSensitive, $primaryWord, $commonalityThreshold ); }
    }

// Categories

    /* GROUP(Categories) Returns the Primary Category for this Ultralink or returns null if it doesn't exist. */
    public function getPrimaryCategory(){ foreach( $this->categories as &$theCategory ){ if( $theCategory->primaryCategory() ){ return $theCategory; } } return null; }

    /* GROUP(Categories) string(A category string.) nuke(Boolean. If true then remove the Category object found from the Ultralink.) Returns the category on this Ultralink identified by <b>string</b>. */
    public function getCategory( $categoryString, $nuke = false ){ foreach( $this->categories as $c => &$theCategory ){ if( $theCategory->categoryString() == $categoryString ){ if( $nuke ){ array_splice( $this->categories, $c, 1 ); } return $theCategory; } } return null; }

    /* GROUP(Categories) string(A category string.) primaryCategory(Indicates whether this new Category should be the primary.) Sets a category based on <b>string</b>. Adds it if it doens't exist and modifies it if it does. */
    public function setCategory( $categoryString, $primaryCategory = null )
    {
        if( $theCategory = $this->getCategory( $categoryString ) )
        {
            if( !is_null($primaryCategory) ){ $theCategory->setPrimaryCategory( $primaryCategory ); }

            return $theCategory;
        }

        if( is_null($primaryCategory) ){ $primaryCategory = 0; }

        $newCategory = Category::C( $this, $categoryString, $primaryCategory );
        $newCategory->dirty = true;

        foreach( $this->categoriesDead as $d => $deadCategory )
        {
            if( $newCategory->isEqualTo( $deadCategory ) )
            {
                array_splice( $this->categoriesDead, $d, 1 );
                array_push( $this->categories, $deadCategory );
                return $deadCategory;
            }
        }

        array_push( $this->categories, $newCategory );
        return $newCategory;
    }

    /* GROUP(Categories) c(Category or category string.) Removes the category <b>c</b>. */
    public function removeCategory( $c )
    {
        if( gettype($c) == "object" ){ $c = $c->categoryString(); }
        array_push( $this->categoriesDead, $this->getCategory( $c, true ) );
    }

    /* GROUP(Categories) Removes all categories from this Ultralink. */
    public function removeAllCategories(){ $this->categoriesDead = array_merge( $this->categoriesDead, $this->categories ); $this->categories = array(); }

    /* GROUP(Categories) c(Category or category string.) nuCategory(A category string.) primaryCategory(Indicates whether this new Category should be the primary.) Replaces category <b>c</b> with the passed values. */
    public function replaceCategory( $c, $nuCategory, $primaryCategory = 0 )
    {
        $this->removeCategory( $c );

        if( gettype($nuCategory) == "object" )
        {
            $nuCategory->dirty = true;
            array_push( $this->categories, $nuCategory );
        }
        else{ $this->setCategory( $nuCategory, $primaryCategory ); }
    }

// Links

    /* GROUP(Links) type(A link type.) language(A language code string.) country(A country code string.) primaryLink(Indicates that the links are primary) Returns a set of links that fit the criteria of the parameters passed in. */
    public function queryLinks( $type = null, $language = null, $country = null, $primaryLink = null )
    {
        $filteredResults = array();

        foreach( $this->links as $link )
        {
            $isOK = true;

            if( ( (!is_null($type)       ) && ($type        != $link->type())        ) ||
                ( (!is_null($language)   ) && ($language    != $link->language())    ) ||
                ( (!is_null($country)    ) && ($country     != $link->country())     ) ||
                ( (!is_null($primaryLink)) && ($primaryLink != $link->primaryLink()) ) ){ $isOK = false; }

            if( $isOK ){ array_push( $filteredResults, $link ); }
        }

        return $filteredResults;
    }

    /* GROUP(Links) url_ID(A URL or URL ID.) type(A link type.) nuke(Boolean. If true then remove the Link object found from the Ultralink.) Returns the link attached to this Ultralink identified by <b>url_ID</b>. */
    public function getLink( $url_ID, $type = null, $nuke = false )
    {
        if( !is_numeric($url_ID) )
        {
            if( is_null($type) ){ $type = detectLinkType( $url_ID ); }
            $url_ID = $this->db->getURLID( $url_ID );
        }

        foreach( $this->links as $l => &$theLink )
        {
            if( ($theLink->url_ID() == $url_ID) &&
                (($theLink->type() == $type  ) || (is_null($type))) )
            {
                if( $nuke ){ array_splice( $this->links, $l, 1 ); }

                return $theLink;
            }
        }

        return null;
    }

    /* GROUP(Links) o(A JSON object representing the Link.) Sets a link on this Ultralink based on the information in <b>o</b>. */
    public function setLinkFromObject( $o )
    {
        if( !empty($o['ID']) && !empty($o['type']) )
        {
//            $type        = null;
            $language    = null;
            $country     = null;
            $primaryLink = null;
            $metaInfo    = null;

//            if( isset($o['type'])        ){ $type        = $o['type'];        }
            if( isset($o['language'])    ){ $language    = $o['language'];    }
            if( isset($o['country'])     ){ $country     = $o['country'];     }
            if( isset($o['primaryLink']) ){ $primaryLink = $o['primaryLink']; }
            if( isset($o['metaInfo'])    ){ $metaInfo    = $o['metaInfo'];    }

            return $this->setLink( $o['ID'], $o['type'], $language, $country, $primaryLink, $metaInfo );
        }

        return null;
    }

    /* GROUP(Links) url(A URL or URL ID.) type(A link type.) language(A language code string.) country(A country code string.) primaryLink(Indicates that the links are primary) metaInfo(A JSON object descriing extra meta info about the Link.) Sets a link on this Ultralink. Adds it if it doesn't exist and modifies it if it does. */
    public function setLink( $url, $type = null, $language = null, $country = null, $primaryLink = null, $metaInfo = null )
    {
        $url_ID = $url; if( !is_numeric($url) ){ $url_ID = $this->db->getURLID( $url ); if( is_null($type) ){ $type = detectLinkType( $url ); } }

        if( $theLink = $this->getLink( $url_ID, $type ) )
        {
//            if( !is_null($type)        ){ $theLink->setType( $type );               }
            if( !is_null($language)    ){ $theLink->setLanguage( $language );       }
            if( !is_null($country)     ){ $theLink->setCountry( $country );         }
            if( !is_null($primaryLink) ){ $theLink->setPrimaryLink( $primaryLink ); }
            if( !is_null($metaInfo)    ){ $theLink->setMetaInfo( $metaInfo );       }

            return $theLink;
        }

//        if( is_null($type)        ){ $type        = detectLinkType( $l->url ); }
        if( is_null($language)    ){ $language    = '';                  }
        if( is_null($country)     ){ $country     = '';                  }
        if( is_null($primaryLink) ){ $primaryLink = 0;                   }
        if( is_null($metaInfo)    ){ $metaInfo    = '';                  }

        $newLink = Link::L( $this, $url_ID, $type, $language, $country, $primaryLink, $metaInfo );
        $newLink->dirty = true;

        foreach( $this->linksDead as $d => $deadLink )
        {
            if( $newLink->isEqualTo( $deadLink ) )
            {
                array_splice( $this->linksDead, $d, 1 );
                array_push( $this->links, $deadLink );
                return $deadLink;
            }
        }

        array_push( $this->links, $newLink );
        return $newLink;
    }

    /* GROUP(Links) link(Link or URL string.) type(A link type.) Removes <b>link</b>. Can optionally specify the <b>type</b>. */
    public function removeLink( $link, $type = null )
    {
        if( gettype($link) != "object" )
        {
            if( $nulink = $this->getLink( $link, $type ) )
            {
                $link = $nulink;
            }
            else{ commandResult( 404, "No link: " . $link . " found on " . $this->description() ); }
        }

        if( $theLink = $this->getLink( $link->url_ID(), $link->type(), true ) ){ array_push( $this->linksDead, $theLink ); }
    }

    /* GROUP(Links) type(A link type.) Removes all links of <b>type</b> from this Ultralink. */
    public function removeLinksOfType( $type )
    {
        $deadLinks = array();

        foreach( $this->links as $link ){ if( $link->type() == $type ){ array_push( $deadLinks, $link ); } }
        foreach( $deadLinks as $link ){ $this->removeLink( $link ); }
    }

    /* GROUP(Links) Removes all links from this Ultralink. */
    public function removeAllLinks(){ $this->linksDead = array_merge( $this->linksDead, $this->links ); $this->links = array(); }

    /* GROUP(Links) link(Link or URL string.) nuURL(A URL or URL ID.) type(A link type.) Replaces <b>link</b> with another link based on <b>nuURL</b>. */
    public function replaceLinkURL( $link, $nuURL, $type = null )
    {
        if( gettype($link) != "object" ){ $link = $this->getLink( $link, $type ); }

        if( $link )
        {
            $this->setLink( $nuURL, $link->type(), $link->language(), $link->country(), $link->primaryLink(), $link->metaInfo() );
            $this->removeLink( $link );
        }
    }

// Connections

    /* GROUP(Connections) ulA(Ultralink or Ultralink ID.) ulB(Ultralink or Ultralink ID.) nuke(Boolean. If true then remove the Connection object found from the Ultralink.) Returns the connection associated with Ultralinks or IDs <b>ulA</b> and <b>ulB</b>. */
    public function getConnection( $ulA, $ulB, $nuke = false )
    {
        if( gettype($ulA) != "object" ){ $ulA = Ultralink::U( $ulA, $this->db ); }
        if( gettype($ulB) != "object" ){ $ulB = Ultralink::U( $ulB, $this->db ); }

        $conHash = $ulA->db->ID . "_" . $ulA->ID . "_" . $ulB->db->ID . "_" . $ulB->ID;

        if( !empty($this->connections[ $conHash ]) )
        {
            $theConnection = $this->connections[ $conHash ];
            if( $nuke ){ unset($this->connections[ $conHash ]); }
            return $theConnection;
        }

        return null;
    }

    /* GROUP(Connections) connection(A connection type string.) Returns an array of connections on this Ultralink that have the connection type string <b>connection</b>. */
    public function queryConnections( $connection )
    {
        $filteredResults = array();

        foreach( $this->connections as &$theConnection )
        {
            if( $theConnection->connection() == $connection ){ array_push( $filteredResults, $theConnection ); }
        }

        return $filteredResults;
    }

    /* GROUP(Connections) o(A JSON object representing the Connection.) Sets a connection on this Ultralink based on the information in <b>o</b>. */
    public function setConnectionFromObject( $o )
    {
        $aID        = null;
        $bID        = null;
        $connection = null;

        if( isset($o['aID'])        ){ $aID        = $o['aID'];        }
        if( isset($o['bID'])        ){ $bID        = $o['bID'];        }
        if( isset($o['connection']) ){ $connection = $o['connection']; }

        return $this->setConnection( $aID, $bID, $connection );
    }

    /* GROUP(Connections) ulAIn(Connection or Ultralink ID) ulBIn(Connection or Ultralink ID) connection(A connection type string) otherDid(Boolean. If true, sets the Connection on the other Ultralink.) Sets a connection for the Ultralinks or IDs <b>ulAIn</b> and <b>ulBIn</b>. Adds it if it doesn't exist and modifies it if it doesn't. */
    public function setConnection( $ulAIn, $ulBIn, $connection = "", $otherDid = false )
    {
        $ulA = "";
        $ulB = "";

        if( gettype($ulAIn) != "object" ){ if( $ulAIn == -1 ){ $ulA = $this; }else{ $ulA = Ultralink::U( $ulAIn, $this->db ); } }else{ $ulA = $ulAIn; }
        if( gettype($ulBIn) != "object" ){ if( $ulBIn == -1 ){ $ulB = $this; }else{ $ulB = Ultralink::U( $ulBIn, $this->db ); } }else{ $ulB = $ulBIn; }

        if( $theConnection = $this->getConnection( $ulA, $ulB ) )
        {
            $theConnection->setConnection( $connection );
            return $theConnection;
        }

        $newConnection = Connection::C( $ulA, $ulB, $connection );
        $newConnection->dirty = true;

        $conHash = $newConnection->hashString();

        if( !empty($this->connectionsDead[ $conHash ]) )
        {
            $deadConnection = $this->connectionsDead[ $conHash ];
            unset( $this->connectionsDead[ $conHash ] );
            $this->connections[ $conHash ] = $deadConnection;

            return $deadConnection;
        }

        $this->connections[ $conHash ] = $newConnection;

        if( $otherDid == false )
        {
                 if( $ulA->ID == $this->ID ){ if( gettype($ulBIn) == "object" ){ $ulB->setConnection( $this,  $ulB, $connection, true ); } }
            else if( $ulB->ID == $this->ID ){ if( gettype($ulAIn) == "object" ){ $ulA->setConnection(  $ulA, $this, $connection, true ); } }
        }

        return $newConnection;
    }

    /* GROUP(Connections) ulB(Connection or Ultralink ID) connection(A connection type string) Adds a connection between this Ultralink and <b>ulB</b>. */
    public function addConnection( $ulB, $connection = "" ){ return $this->setConnection( $this, $ulB, $connection ); }

    /* GROUP(Connections) c(Connection or Ultralink ID) connection(A connection type string) Removes the connection <b>c</b>. */
    public function removeConnection( $c, $connection = "" )
    {
        if( gettype($c) == "object" )
        {
            $this->connectionsDead[ $c->hashString() ] = $this->getConnection( $c->ulA(), $c->ulB(), true );
        }
        else
        {
            $ulB = ULtralink::U( $c, $this->db );

                 if( $dc = $this->getConnection( $this, $ulB, true ) ){ $this->connectionsDead[ $dc->hashString() ] = $dc; }
            else if( $dc = $this->getConnection( $ulB, $this, true ) ){ $this->connectionsDead[ $dc->hashString() ] = $dc; }
        }
    }

    /* GROUP(Connections) Removes all connections from this Ultralink. */
    public function removeAllConnections()
    {
        $this->connectionsDead = array_merge( $this->connectionsDead, $this->connections );
        $this->connections = array();
    }

// Page Feedback

    /* GROUP(Page Feedback) page_ID(A page ID) word(A word string.) nuke(Boolean. If true then remove the PageFeedback object found from the Ultralink.) Returns the page feedback for this Ultralink on a given page ID for a word string. The word string may be "". Can optionally remove it from the Ultralink. */
    public function getPageFeedback( $page_ID, $word, $nuke = false ){ foreach( $this->pageFeedback as $pf => &$thePageFeedback ){ if( ($thePageFeedback->page_ID() == $page_ID) && ($thePageFeedback->word() == $word) ){ if( $nuke ){ array_splice( $this->pageFeedback, $pf, 1 ); } return $thePageFeedback; } } return null; }

    /* GROUP(Page Feedback) o(A JSON object representing the PageFeedback.) Sets a page feedback on this Ultralink based on the information in <b>o</b>. */
    public function setPageFeedbackFromObject( $o )
    {
        if( !empty($o['page_ID']) )
        {
            $feedback = null;

            if( isset($o['feedback']) ){ $feedback = $o['feedback']; }

            return $this->setPageFeedback( $o['page_ID'], $o['word'], $feedback );
        }

        return null;
    }

    /* GROUP(Page Feedback) page_ID(A page ID) word(A word string.) feedback(A non-zero feedback number.) Sets the page feedback for <b>page_ID</b>, <b>word</b> and <b>feedback</b>. Adds it if it doesn't exist, modifies it if it does. */
    public function setPageFeedback( $page_ID, $word, $feedback = null )
    {
        if( $thePageFeedback = $this->getPageFeedback( $page_ID, $word ) )
        {
            if( !is_null($feedback) )
            {
                if( $feedback == 0 ){ $this->removePageFeedback( $thePageFeedback ); return null; }
                else{ $thePageFeedback->setFeedback( $feedback ); }
            }

            return $thePageFeedback;
        }

        if( is_null($feedback) ){ $feedback = -1; }

        if( $feedback != 0 )
        {
            $newPageFeedback = PageFeedback::PF( $this, $page_ID, $word, $feedback );
            $newPageFeedback->dirty = true;

            foreach( $this->pageFeedbackDead as $d => $deadPageFeedback )
            {
                if( $newPageFeedback->isEqualTo( $deadPageFeedback ) )
                {
                    array_splice( $this->pageFeedbackDead, $d, 1 );
                    array_push( $this->pageFeedback, $deadPageFeedback );
                    return $deadPageFeedback;
                }
            }

            array_push( $this->pageFeedback, $newPageFeedback );
            return $newPageFeedback;
        }

        return null;
    }

    /* GROUP(Page Feedback) pf(PageFeedback) Removes page feedback <b>pf</b> from this Ultralink. */
    public function removePageFeedback( $pf ){ array_push( $this->pageFeedbackDead, $this->getPageFeedback( $pf->page_ID(), $pf->word(), true ) ); }

    /* GROUP(Page Feedback) Removes all page feedback from this Ultralink. */
    public function removeAllPageFeedback(){ $this->pageFeedbackDead = array_merge( $this->pageFeedbackDead, $this->pageFeedback ); $this->pageFeedback = array(); }

//

    /* GROUP(Annotation) Returns the URL for this Ultralink's annotation link. */
    public function annotationLink(){ global $masterPath; return $masterPath . "annotation/" . $this->db->ID . "/" . $this->ID; }

    /* GROUP(Annotation) language(A language code string.) country(A country code string.) Gets the annotation content for this Ultralink for a given language and country bias if any. */
    public function annotation( $language = "", $country = "" ){ return $this->APICallSub('/annotation', array( 'language' => $language ,'country' => $country ), "Could not retrieve annotation for " . $this->description()); }

    /* GROUP(Annotation) text(Annotation text.) type(The type of content being stored.) language(A language code string.) country(A country code string.) Sets the annotation data for this Ultralink to <b>text</b> on <b>langauge</b> and <b>country</b>. */
    public function setAnnotation( $text, $type="text", $language = "", $country = "" ){ return $this->APICall( array( 'set' => $text, 'type' => $type, 'language' => $language ,'country' => $country ), "Could not insert/update annotation for " . $this->description() ); }

    /* GROUP(Holding Tank) word(A word string.) caseSensitive(Boolean. 1 indicates that this Word is case sensitive.) resolution(The decision string whether to <b>accept</b> or <b>reject</b> the submitted word.) contributor(<user identifier>) Removes the submission entry for the new word. If the resolution is 'accept' then it adds the word. */
    public function resolveWord( $word, $caseSensitive, $resolution, $contributor ){ return $this->APICall( array( 'resolveWord' => $word, 'resolution' => $resolution ,'caseSensitive' => $caseSensitive, 'contributor' => $contributor ), "Couldn't resolve the submitted word (word: " . $word . ", resolution: " . $resolution . ")" ); }

    /* GROUP(Holding Tank) category(A category string.) resolution(The decision string whether to <b>accept</b> or <b>reject</b> the submitted word.) contributor(<user identifier>) Removes the submission entry for the new category. If the resolution is 'accept' then it adds the category. */
    public function resolveCategory( $category, $resolution, $contributor ){ return $this->APICall( array( 'resolveCategory' => $category, 'resolution' => $resolution, 'contributor' => $contributor ), "Couldn't resolve the submitted category (category: " . $category . ", resolution: " . $resolution . ")" ); }

    /* GROUP(Holding Tank) URL(A URL string.) type(A link type.) resolution(The decision string whether to <b>accept</b> or <b>reject</b> the submitted word.) contributor(<user identifier>) Removes the submission entry for the new link. If the resolution is 'accept' then it adds the link. */
    public function resolveLink( $URL, $type, $resolution, $contributor ){ return $this->APICall( array( 'resolveLink' => $URL, 'type' => $type, 'resolution' => $resolution, 'contributor' => $contributor ), "Couldn't resolve the submitted link (URL: " . $URL . ", type: " . $type . ", resolution: " . $resolution . ")" ); }

    /* GROUP(Holding Tank) page_ID(A page ID.) feedback(A non-zero feedback number.) resolution(The decision string whether to <b>accept</b> or <b>reject</b> the submitted word.) contributor(<user identifier>) Removes the submission entry for the new page feedback. If the resolution is 'accept' then it adds the page feedback. */
    public function resolvePageFeedback( $page_ID, $feedback, $resolution, $contributor ){ return $this->APICall( array( 'resolvePageFeedback' => $page_ID, 'feedback' => $feedback, 'resolution' => $resolution, 'contributor' => $contributor ), "Couldn't resolve the submitted page feedback (pageFeedback: " . $feedback . ", page_ID: " . $page_ID . ", resolution: " . $resolution . ")" ); }

    /* GROUP(Holding Tank) urlID(A URL ID) type(A link type.) problem(The problem of the above URL.) contributor(<user identifier>) Removes a submitted link from the holding tank or a specific type, problem type and contributor. */
    public function dismissReportedLink( $urlID, $type, $problem, $contributor ){ return $this->APICall( array( 'dismissReportedLink' => $urlID, 'type' => $type, 'problem' => $problem, 'contributor' => $contributor ), "Couldn't remove the reported link (urlID: " . $urlID . ", type: " . $type . ", problem: " . $problem . ")" ); }

    /* GROUP(Holding Tank) con_ID(An ID for a connected Ultralink.) problem(The problem with the connection.) Adds a connection complaint into the holding tank for this Ultralink. */
    public function reportConnection( $con_ID, $problem ){ return $this->APICall( array( 'reportConnection' => $con_ID, 'problem' => $problem ), "Could not enter in connection report description_ID: " . $this->description() . ", con_ID: " . $con_ID . ", problem: " . $problem ); }

    /* GROUP(Holding Tank) url_ID(A URL ID) type(A link type.) problem(The problem of the above URL.) Adds a link complaint into the holding tank for this Ultralink. */
    public function reportLink( $url_ID, $type, $problem ){ return $this->APICall( array( 'reportLink' => $url_ID, 'type' => $type, 'problem' => $problem ), "Could not enter in link report description_ID: " . $this->description() . ", url_ID: " . $url_ID . ", type: " . $type . ", problem: " . $problem ); }

    /* GROUP(Holding Tank) category(A category string.) Adds a category into the holding tank for this Ultralink. */
    public function submitCategory( $category ){ return $this->APICall( array( 'submitCategory' => $category ), "Could not enter in submitted category " . $category . " Ultralink: " . $this->description() ); }

    /* GROUP(Holding Tank) connection(A connection type string.) connection_ID(The ID of another Ultralink to connect to.) Adds a connection into the holding tank for this Ultralink. */
    public function submitConnection( $connection, $connection_ID ){ return $this->APICall( array( 'submitConnection' => $connection, 'connection_ID' => $connection_ID ), "Could not enter in submitted connection " . $connection . " Ultralink: " . $this->description() . " connection_ID: " . $connection_ID ); }

    /* GROUP(Holding Tank) URL(A URL string.) type(A link type.) Adds a link into the holding tank for this Ultralink. */
    public function submitLink( $URL, $type ){ return $this->APICall( array( 'submitLink' => $URL, 'type' => $type ), "Could not enter in submitted link Ultralink: " . $this->description() . ", URL: " . $URL . ", type: " . $type ); }

    /* GROUP(Holding Tank) word(A word string.) caseSensitive(Boolean. 1 indicates that this Word is case sensitive.) Adds a word into the holding tank for this Ultralink. */
    public function submitWord( $word, $caseSensitive ){ return $this->APICall( array( 'submitWord' => $word, 'caseSensitive' => $caseSensitive ), "Could not enter in submitted words Ultralink: " . $this->description() . ", word: " . $word . ", caseSensitive: " . $caseSensitive ); }

    /* GROUP(Holding Tank) pageURL(A URL that this Ultralink needs a bias on.) feedback(A non-zero feedback number.) Adds a page feedback value for the specified page into the holding tank for this Ultralink. */
    public function submitPageFeedback( $pageURL, $feedback ){ return $this->APICall( array( 'submitPageFeedback' => $pageURL, 'feedback' => $feedback ), "Could not enter in submitted page feedback " . $this->description() . " " . $pageURL . " " . $feedback ); }

    public function APICallSub( $sub, $fields, $error )
    {
        $call = Master::$cMaster->APICall('0.9.1/db/' . $this->db->ID . '/ul/' . $this->ID . $sub, $fields );
        
        if( $call !== "" )
        {
            if( $call === true ){ return $call; }
                            else{ return json_decode( $call, true ); }
        }
        else{ commandResult( 500, $error ); }
    }
    public function APICall( $fields, $error ){ return $this->APICallSub( '', $fields, $error ); }}

?>
