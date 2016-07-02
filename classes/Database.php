<?php

// Copyright © 2016 Ultralink Inc.

require_once classesPath() . '/ULBase.php';
require_once classesPath() . '/User.php';
require_once classesPath() . '/Ultralink.php';
require_once classesPath() . '/Job.php';

class Database extends ULBase
{
    public $ID;
    public $name;

    public static $dbsID   = array();
    public static $dbsName = array();

    public $urlIDs = array();

    protected function populateDetail( $key, $value )
    {
        switch( $key )
        {
            case 'affiliateKeys':
            {
                if( empty($value) ){ $this->$key = array(); }
                else if( gettype($value) == 'string' ){ $this->$key = json_decode( $value, true ); }
                else{ $this->$key = $value; }
            } break;

            default: { $this->$key = $value; }
        }
    }

    protected function getDetail( $key )
    {
        switch( $key )
        {
            case 'affiliateKeys':
            {
                return json_encode($this->$key);
            } break;

            default: { return $this->$key; }
        }
    }

    protected function populateDetails(){ parent::populateDetails('0.9.1/db/' . $this->ID); }

    /* GROUP(On Disk Databases) Returns the ID and name of every database in the Master. */
    public static function all()
    {
        global $cMaster;

        if( $call = $cMaster->APICall('0.9.1/db') )
        {
            $result = array();
            foreach( json_decode( $call, true ) as $db ){ array_push( $result, Database::DBWithIDName( $db['ID'], $db['name'] ) ); }
            return $result;
        }
        else{ commandResult( 500, "Could not lookup databases" ); }
    }

    /* GROUP(On Disk Databases) name(A database name string.) Examines a newly proposed database name to see if it fits the criteria for database names: <ul><li>Cannot start with a number.</li><li>Must be 16 characters or less.</li><li>Must contain only lower case alphanumeric characters.</li></ul>  If <b>name</b> passes these tests and there is not already an existing database with this name, then it will return with success to indicate availability. */
    public static function nameAvailable( $name )
    {
        Database::all();

        if( !(preg_replace("/[^0-9]+/", "", $name) == $name) )
        {
            if( (count($name) <= 16) && (count($name) > 0) )
            {
                if( preg_replace("/[^a-z0-9]+/", "", $name) == $name )
                {
                    if( !Database::DB( $name ) )
                    {
                        return true;
                    }
                    else{ commandResult( 403, "Database " . $name . " already exists." ); }
                }
                else{ commandResult( 400, "Database name (" . $name . ") must be lower case alphanumeric." ); }
            }
            else{ commandResult( 400, "Database name (" . $name . ") cannot be longer than 16 characters." ); }
        }
        else{ commandResult( 400, "Database name (" . $name . ") cannot be a number alone." ); }

        return false;
    }

    /* GROUP(On Disk Databases) name(A database name string.) Creates a new database with <b>name</b>. */
    public static function create( $name ){ return Database::APICallUp( array('create' => $name), "Could not create database " . $name ); }

    /* GROUP(Database Listings) identifier(<database identifier>) Returns whether <b>identifer</b> is a string that legitimately identifies the Mainline database. */
    protected static function mainlineIdentifier( $identifier )
    {
        global $cMaster;

        if( ($identifier == "") ||
            ($identifier === 0) ||
            ($identifier === '0') ||
            ($identifier == $cMaster->masterDomain) ||
            ($identifier == null) ||
            ($identifier == "undefined") ||
            ($identifier == "Mainline") ||
            (empty($identifier)) )
        {
            return true;
        }

        return false;
    }

    /* GROUP(Database Listings) db(Database) Enters <b>db</b> into the database cache. */
    protected static function enterDB( $db )
    {
        self::$dbsID[$db->ID]     = $db;
        self::$dbsName[$db->name] = $db;

        return $db;
    }

    protected static function DBWithIDName( $ID, $name )
    {
             if( !empty(self::$dbsID[$ID])     ){ return self::$dbsID[$ID];     }
        else if( !empty(self::$dbsName[$name]) ){ return self::$dbsName[$name]; }
        else
        {
            $db = new self();
            $db->loadByIDName( $ID, $name );

            return Database::enterDB( $db );
        }
    }

    /* GROUP(Database Listings) identifier(<database identifier>) Returns the database associated with <b>identifier</b>. */
    public static function DB( $identifier = "" )
    {
        if( Database::mainlineIdentifier( $identifier ) ){ $identifier = 0; }

             if( !empty(self::$dbsID[$identifier])   ){ return self::$dbsID[$identifier];   }
        else if( !empty(self::$dbsName[$identifier]) ){ return self::$dbsName[$identifier]; }
        else
        {
            $db = new self();
            if( $db = $db->loadByIdentifer( $identifier ) ){ return Database::enterDB( $db ); }
        }

        return null;
    }

    /* GROUP(Database Listings) identifier(<database identifier>) Loads the database information for <b>indentifier</b>. */
    protected function loadByIdentifer( $identifier = "" )
    {
        global $cMaster;

        if( Database::mainlineIdentifier( $identifier ) )
        {
            $this->ID   = 0;
//            $this->name = $cMaster->masterDomain;
            $this->name = "Mainline";
        }
        else if( is_numeric($identifier) )
        {
            $name = Database::nameForDBID( $identifier );

            if( $name != "" )
            {
                $this->ID   = intval($identifier);
                $this->name = $name;
            }
            else{ return null; }
        }
        else
        {
            $db_ID = Database::IDForDBName( $identifier );

            if( $db_ID != -1 )
            {
                $this->ID   = $db_ID;
                $this->name = $identifier;
            }
            else{ return null; }
        }

        return $this;
    }

    /* GROUP(Database Listings) ID(A database ID.) name(A database name.) Sets the information for the database from <b>ID</b> and <b>name</b>. */
    protected function loadByIDName( $ID, $name )
    {
        if( $ID === 0 ){ $this->ID = 0;           $this->name = "Mainline"; }
                   else{ $this->ID = intval($ID); $this->name = $name;      }
    }

    /* GROUP(Working Databases) identifier(<database identifier>) Sets the current database to the one identified by <b>identifier</b>. */
    public static function currentDB( $identifier = "" ){ if( $theDB = Database::DB( $identifier ) ){ $theDB->setCurrent(); return $theDB; } return null;  }

    /* GROUP(Working Databases) Sets this database to be the current database. */
    public function setCurrent(){ global $cDB; $cDB = $this; }

    /* GROUP(Information) db_ID(A database ID.) Returns the name of the database with the ID <b>db_ID</b>. */
    public static function nameForDBID( $db_ID ){ return Database::APICallUp( array( 'nameForDBID' => $db_ID ), "Could not lookup database with ID " . $db_ID ); }

    /* GROUP(Information) name(A database name.) Returns the ID of the database with the name <b>name</b>. */
    public static function IDForDBName( $name ){ return Database::APICallUp( array( 'IDForDBName' => $name ), "Could not lookup database with name " . $name ); }

    /* GROUP(Information) Returns a string describing this database. */
    public function description(){ return $this->name . " [" . $this->ID . "]"; }

    /* GROUP(Information) Returns a URL postfix string for use in identifying this database. */
    public function postfix(){ $dbPostfix = ""; if( $this->ID != 0 ){ $dbPostfix = $this->name; } if( $dbPostfix != "" ){ $dbPostfix = "/" . $dbPostfix; } return $dbPostfix; }

    /* GROUP(Information) Returns the number of ultralinks currently in this database. */
    public function ultralinkCount(){ return $this->APICall( 'ultralinkCount', "Could not retrieve the ultralink count" ); }

    /* GROUP(Information) Returns the remote roots that this Database has associated with it. */
    public function remoteRoots(){ return $this->APICall( 'remoteRoots', "Failed to get the remote roots for " . $this->description() ); }

    /* GROUP(Vanity Names) name(A vanity name string.) Returns the ultralink ID for a given vanity name or 0 if it does not exist. */
    public function lookupVanityName( $name ){ return $this->APICall( array('lookupVanityName' => $name), "Failed to get the vanity ID for " . $name ); }

    /* GROUP(Vanity Names) ulID(An Ultralink ID.) Returns the vanity string for an ultralink or a null string if it does not exist. */
    public function lookupVanityDescription( $ulID ){ return $this->APICall( array('lookupVanityDescription' => $ulID), "Failed to get the vanity name for " . $ulID ); }

    /* GROUP(Modification) sourceDatabase(Database) Changes the source database. A value of -1 indicates that there is no source database. */
    public function changeSourceDatabase( $sourceDatabase )
    {
        if( ($sourceDatabase != -1) && !Database::DB( $sourceDatabase ) ){ commandResult( 401, "Database " . $sourceDatabase . " does not exist" ); }
        $this->sourceDatabase = $sourceDatabase;

        return $this->APICall( array('sourceDatabase' => $sourceDatabase), "Could not change the source database for " . $this->description() );
    }

    /* GROUP(Modification) update(An object with new affiliate key settings.) Sets the affilliate keys for this database to the given values. */
    public function updateAffiliateKeys( $update )
    {
        $changed = false; foreach( $update as $service => $key ){ if( $key != $this->affiliateKeys[$service] ){ $changed = true; } $this->affiliateKeys[$service] = $key; }

        if( $changed == true )
        {
            return $this->APICall( array('updateAffiliateKeys' => json_encode( $this->affiliateKeys )), "Could not update the affiliate keys for " . $this->description() );
        }
    }

    /* GROUP(Modification) Destroys the database and all related data. */
    public function nuke(){ return $this->APICall( 'nuke', "Could not nuke database " . $this->description() ); }

    /* GROUP(Actions) initialCategory(Category string. Optionally set if you are creating a new Ultralink) needsReview(Review status number. 0 means no review needed)Creates a new ultrailnk in this database with the initial state optionally based on <b>initialCategory</b> and <b>needsReview</b>. */
    public function createUltralink( $initialCategory = '', $needsReview = 0 ){ return Ultralink::U( -1, $this, $initialCategory, $needsReview ); }

    /* GROUP(Actions) bannedWord(A word string.) Inserts a word into the 'banned' table. */
    public function banWord( $bannedWord ){ return $this->APICall( array( 'banWord' => $bannedWord ), "Could not insert banned autogenerated word " . $bannedWord ); }

    /* GROUP(Actions) newc(A content fragment.) contentURL(A URL string.) affiliateOverrides(An array of affiliate overrides.) Filters fragment content through this database. */
    public function UltralinksInContent( $newc, $contentURL, $affiliateOverrides = true ){ return $this->APICall( array('UltralinksInContent' => $newc, 'contentURL' => $contentURL, 'affiliateOverrides' => $affiliateOverrides), "Could not get the Ultralinks in content at " . $contentURL ); }

    /* GROUP(Content) unfilteredContent(A content fragment.) contentURL(A URL string.) contentTitle(The title of the URL.) hyperlinks(An array of hyperlinks present in the fragment.) Filters fragment content through this master (meaning through any source database and then this database). */
    public function ULFilterContent( $unfilteredContent, $contentURL, $contentTitle, $hyperlinks ){ return $this->APICall( array('ULFilterContent' => $unfilteredContent, 'contentURL' => $contentURL, 'contentTitle' => $contentTitle, 'hyperlinks' => $hyperlinks), "Could not filter the content at " . $contentURL ); }

    /* GROUP(Authorization) Returns the auth level for this database for the current user. */
    public function auth(){ global $cUser; return $this->authForUser( $cUser ); }
    /* GROUP(Authorization) user(User) Returns the auth level for this database for <b>user</b>. */
    public function authForUser( $user ){ return $user->authForDB( $this ); }

    /* GROUP(Websites) website(A website URL.) Returns the ID, time and blacklisted status for the given website. */
    public function websiteInfo( $website ){ return $this->APICall( array('websiteInfo' => $website), "Couldn't get " . $website . " information" ); }

    /* GROUP(Websites) website(A website ID.) Returns the ID for <b>website</b> or 0 if it doesn't exist. */
    public function websiteID( $website ){ return $this->APICall( array('website_ID' => $website), "Could not lookup website " . $website . " in " . $this->description() ); }

    /* GROUP(Pages) URL(A page URL.) websiteID(A website ID.) title(The title of the page.) Returns information on the page at <b>URL</b> on <b>websiteID</b>. */
    public function pageInfo( $URL, $websiteID, $title ){ return $this->APICall( array('pageInfo' => $URL, 'websiteID' => $websiteID, 'title' => $title), "Couldn't get " . $URL . " information" ); }

    /* GROUP(Categories) category(A category string.) Gets a list of all the subcategories under the given category string as well as the number of subcategories and ultralinks. Ordered by the count of ultralinks in the category descending. */
    public function categoryTree( $category ){ return $this->APICall( array('categoryTree' => $category), "Couldn't get the category tree for " . $category ); }

    /* GROUP(Categories) existingCategory(A category string.) newCategory(A category string.) Modifies all applicable ultralinks to change an existing category to a given new category. */
    public function changeCategory( $existingCategory, $newCategory ){ return $this->APICall( array('changeCategory' => $existingCategory, 'newCategory' => $newCategory), "Couldn't change existing category " . $existingCategory . " to " . $newCategory ); }

    /* GROUP(URLs) url_ID(A URL ID.) Returns the URL for the link associated with the ID <b>url_ID</b>. */
    public function getURL( $url_ID ){ return $this->APICall( array( 'url_ID' => $url_ID ), "Could not lookup the URL for url ID " . $url_ID ); }

    /* GROUP(URLs) url(A URL string.) Returns the ID for <b>url</b>. */
    public function getURLID( $url )
    {
        if( !empty($this->urlIDs[ $url ]) ){ return $this->urlIDs[ $url ]; }
        else
        {
            $theURLID = $this->APICall( array( 'url' => $url ), "Could not lookup the URL ID for url " . $url );
            $this->urlIDs[ $url ] = $theURLID;
            return $theURLID;
        }
    }

    /* GROUP(Queries) URL(A URL string.) trimset(A character to trim on.) Returns the first ultralink that has <b>URL</b> attached to it. */
    public function ulFromURL( $URL, $trimset = "" ){ return Ultralink::U( $this->APICall( array( 'ulFromURL' => $URL, 'trimset' => $trimset ), "Could not lookup the ultralink for URL " . $URL ) ); }

    /* GROUP(Queries) word(A word string.) case(Indicates whether the search should be case sensitive.) category(A category string.) recent(Boolean. If true, then restrict search to recent ultralinks.) Returns the first ultralink that has <b>word</b> attached to it. You can optionally use <b>case</b>, <b>category</b> and <b>recent</b> to further narrow down your results. */
    public function ulFromWord( $word, $caseSensitive = false, $category = "", $recent = false ){ return Ultralink::U( $this->APICall( array( 'ulFromWord' => $word, 'case' => $caseSensitive, 'category' => $category, 'recent' => $recent ), "Could not lookup the ultralink for word " . $word ) ); }

    /* GROUP(Queries) connection(A Connection type string.) offset(Pagination offset.) limit(Pagination limit.) Returns a set of ultralinks that have a connection string that begins with $connection ordered by primary instance count descending. */
    public function connectionUltralinks( $connection, $offset = 0, $limit = 100 ){ return $this->APICall( array( 'connectionUltralinks' => $connection, 'offset' => $offset, 'limit' => $limit ), "Could not lookup ultralinks for connection " . $connection ); }

    /* GROUP(Queries) category(A category string.) offset(Pagination offset.) limit(Pagination limit.) Returns a set of ultralinks that have a category string that begins with $category ordered by primary instance count descending. */
    public function categoryUltralinks( $category, $offset = 0, $limit = 100 ){ return $this->APICall( array( 'categoryUltralinks' => $category, 'offset' => $offset, 'limit' => $limit ), "Could not lookup ultralinks for category " . $category ); }

    /* GROUP(Queries) query(A search string.) wordSearch(Search for <b>query</b> in words.) categorySearch(Search for <b>query</b> in categories.) exact(Boolean. If true the match cannot be a substring.) sortType(What way to sort the results.) includePages(Boolean. If true, include the pages that the Ultralink is on.) offset(Pagination offset.) limit(Pagination limit.) Returns a set of ultralinks based on a query string and various search attributes. Searches can examine ultralink words and category strings or both. Matches can be required to be exact or not. Sorting can be by instance count, exact matching, alphabetical word order, word length or alphabetical category order. Results can optinally include information on what pages the ultralinks resides as well. Results are paged at 100 results by default. */
    public function search( $query, $wordSearch = true, $categorySearch = true, $exact = false, $sortType = 'instanceCount', $includePages = false, $offset = 0, $limit = 100 ){ return $this->APICallSub( '/ul', array( 'search' => $query, 'wordSearch' => $wordSearch, 'categorySearch' => $categorySearch, 'sortType' => $sortType, 'exact' => $exact, 'includePages' => $includePages, 'offset' => $offset, 'limit' => $limit ), "Could not perform ultralink search" ); }

    /* GROUP(Queries) likeString(A LIKE string to match the URL against.) type(The like type to match against.) language(A langauge bias if any.) country(A country bias if any.) primaryLink(Indication if the link should be the primary one or not.) Returns the Ultralink IDs that have links that fit the given criteria. */
    public function linkQuery( $likeString = "", $type = "", $language = "", $country = "", $primaryLink = "" ){ return $this->APICallSub( '/ul', array( 'linkQuery' => $likeString, 'type' => $type, 'language' => $language, 'country' => $country, 'primaryLink' => $primaryLink ), "Could not run the link query" ); }

    /* GROUP(Queries) word(A word string.) Returns the most recently modified ultrailnk within the last day that has the given word attached to it. */
    public function recentUltralink( $word ){ if( $ul = $this->ulFromWord( $word, false, "", true ) ){ return $ul; } else{ commandResult( 404, "Could not find recent ultralink for " . $word ); } }

    /* PRIVATE GROUP(Queries) Returns the examination status for a given image or 'false' if it does not exist. */
    public function imageExaminationStatus( $image ){ return $this->APICall( array( 'imageExaminationStatus' => $image ), "Could not look up image status for " . $image ); }

    /* GROUP(Jobs) creationParameters(A JSON object describing the Job.) Creates a new job based on the creation parameters passed in. */
    public function createJob( $creationParameters ){ return $this->APICallSub( '/jobs', array( 'create' => $creationParameters ), "Could not create new job with query " . $creationParameters['query'] . " and operation " . $creationParameters['operation'] ); }

    /* GROUP(Jobs) Returns a complete set of all the job queries and operations currently defined in this database. */
    public function getJobQueriesOperations(){ return $this->APICallSub( '/jobs', 'tools', "Couldn't get the queries and operations." ); }

    /* GROUP(Jobs) Returns a list of information on every job defined. */
    public function getJobsInfo(){ return $this->APICallSub( '/jobs', '', "Couldn't lookup the job list" ); }

    /* GROUP(Hardcoded) Returns a list of all the websites currently with custom url classifiers or selector overrides alone with the website ID and URL. */
    public function customized(){ return $this->APICall( 'customized', "Could not retrieve the website customizations" ); }

    /* GROUP(Hardcoded) websiteID(A website ID.) overrides() Sets a given website's selector overrides to the set passed in. */
    public function saveOverrides( $websiteID, $overrides ){ return $this->APICall( array('saveOverrides' => $overrides, 'websiteID' => $websiteID), "Could not save the website overrides" ); }

    /* GROUP(Hardcoded) websiteID(A website ID.) classifiers() Sets a given website's url classifiers to the set passed in. */
    public function saveClassifiers( $websiteID, $classifiers ){ return $this->APICall( array('saveClassifiers' => $classifiers, 'websiteID' => $websiteID), "Could not save the url classifiers" ); }

    /* GROUP(Analytics) dataHash() timeScale(The time scale of the data we are looking at. Values can be <b>monthly</b>, <b>daily</b> or <b>hourly</b>.) timeDuration(The numeric length of the time slice that the data should examine in units defined by <b>timeScale</b>.) analyticsBreakdown(The type of analytics data that should be returned.) numericType(The type of numeric data that should be returned. Values can be <b>absolute</b> or <b>percent</b>.) statusType(A user status type to filter the data though. Values can be <b>active</b>, <b>enabled</b> or <b>installed</b>.) osType(A OS type to filter the data though. Values can be <b>all</b>, <b>mac</b>, <b>windows</b>, <b>linux</b>, <b>android</b> or <b>unknown</b>.) browserType(A browser to filter the data though. Values can be all, <b>safari</b>, <b>chrome</b>, <b>firefox</b>, <b>opera</b> or <b>operanext</b>.) authType(A user authentication status to filter the data though. Values can be <b>all</b>, <b>authenticated</b> or <b>anonymous</b>.) actionType(A action to filter the data though. Values can be <b>all</b>, <b>blackshadowauto</b>, <b>blackshadow</b> or <b>blueshadow</b>.) linkType(A link type to filter the data through. Valuse can be <b>all</b> or any valid link type in this database.) Returns a set of chart data based on the parameters passed in. Can leverage the analyticsCache with a resultant data hash. If the data has is identical to the one in the cache then it will block and wait on one of the analytics queues so that web services can long poll on this. */
    public function historicalAnalytics( $dataHash, $timeScale, $timeDuration, $analyticsBreakdown, $numericType, $statusType, $osType, $browserType, $authType, $actionType, $linkType ){ return $this->APICall( array('historicalAnalytics' => $dataHash, 'timeScale' => $timeScale, 'timeDuration' => $timeDuration, 'analyticsBreakdown' => $analyticsBreakdown, 'numericType' => $numericType, 'statusType' => $statusType, 'osType' => $osType, 'browserType' => $browserType, 'authType' => $authType, 'actionType' => $actionType, 'linkType' => $linkType), "Could not get the historical analytics" ); }

    /* GROUP(Analytics) association(An association identifier.) startDate(A starting time to the session as a UNIX timestamp.) endDate(An ending time to the session as a UNIX timestamp.) eventNum(The number of events expected in the session as given by <b>associationSessions</b>.) associationType(	The type of the above identifier if given.) dataHash(A resultant data hash so as to leverage the analytics cache if possible.) Returns a set of events for an association identifer of a given type from a start date to an end date with a known number of events. Based on these paramters and a hash of the resultant data it wil try to lean on the analyticsCache if possible. */
    public function associationHistory( $association, $startDate, $endDate, $eventNum, $associationType, $dataHash ){ return $this->APICall( array('associationHistory' => $association, 'startDate' => $startDate, 'endDate' => $endDate, 'eventNum' => $eventNum, 'associationType' => $associationType, 'dataHash' => $dataHash), "Could not get the association history" ); }

    /* GROUP(Analytics) associationType(The type of the above identifier if given.) association(An association identifier.) Returns a list of activity sessions for a given association type and identifier. Sessions are defined as activity clusters at least 60 minutes apart from each other. */
    public function associationSessions( $associationType, $association ){ return $this->APICall( array('associationSessions' => $association, 'associationType' => $associationType), "Could not get the association sessions" ); }

    /* GROUP(Analytics) pagePath(A URL fragment that defines the scope of desired data.) contentsType(Indicates what kind of content data is desired. Values can be <b>catpresent</b>, <b>catclicked</b>, <b>ulpresent</b> or <b>ulclicked</b>.) timeRestrict(Determines if the results should be restricted in any way. Values can be <b>cache</b> or <b>alltime</b>.) restrictToThis(A boolean indicating that results should only match the exact URL of <b>pagePath</b>.) timeScale(The time scale of the data we are looking at. Values can be <b>monthly</b>, <b>daily</b> or <b>hourly</b>.) timeDuration(The numeric length of the time slice that the data should examine in units defined by <b>timeScale</b>.) offset(Pagination offset.) limit(Pagination limit. Max <b>100</b>.) Gets ultralink content and interaction information within a given time range at a given URL path fragment which can also be the value "all". You can specify what kind of ultralink content you want and select prescence of ultralinks, categories or click data on both those as well. Can restrict some configurations to only look at data connected to what is in the current content cache through resultRestrict. Can also restrict the results to an exact URL path fragment match instead of including everything under it as well. Can be paged through an offset and limit. */
    public function myContents( $pagePath, $contentsType, $timeRestrict, $restrictToThis, $timeScale, $timeDuration, $offset = 0, $limit = 10 ){ return $this->APICall( array('myContents' => $pagePath, 'contentsType' => $contentsType, 'resultRestrict' => $timeRestrict, 'restrictToThis' => $restrictToThis, 'timeScale' => $timeScale, 'timeDuration' => $timeDuration, 'offset' => $offset, 'limit' => $limit), "Could not get contents analytics" ); }

    /* GROUP(Analytics) pagePath(A URL fragment that defines the scope of desired data.) orderBy(How to structure and order the results. Values can be <b>usage</b>, <b>hosted</b>, <b>pages</b> or <b>clicks</b>.) timeRestrict(Determines if the results should be restricted in any way. Values can be <b>cache</b> or <b>alltime</b>.) timeScale(The time scale of the data we are looking at. Values can be <b>monthly</b>, <b>daily</b> or <b>hourly</b>.) timeDuration(The numeric length of the time slice that the data should examine in units defined by <b>timeScale</b>.) search(A search string to restrict the entires under <b>pagePath</b> can match against.) offset(Pagination offset.) limit(Pagination limit. Max <b>100</b>.) Gets a statistical look within a time period at a given URL Path fragment which can also be "". Results can be ordered and organized by usage frequency, whether or not the ultralinks are hosted natively, number of pages or numbers of clicks. Can restrict some configurations to only look at data connected to what is in the current content cache. Can also restrict results to be limited to a search as we well. Can be paged by a given offset and limit. */
    public function myWebsites( $pagePath, $orderBy, $timeRestrict, $timeScale, $timeDuration, $search = "", $offset = 0, $limit = 10 ){ return $this->APICall( array('myWebsites' => $pagePath, 'orderBy' => $orderBy, 'resultRestrict' => $timeRestrict, 'timeScale' => $timeScale, 'timeDuration' => $timeDuration, 'search' => $search, 'offset' => $offset, 'limit' => $limit), "Could not get website analytics" ); }

    /* GROUP(Holding Tank) Returns the entries currently in the holding tank. */
    public function holdingTank(){ return $this->APICall( 'holdingTank', "Could note retrieve the web holding tank rows" ); }

    /* GROUP(Holding Tank) Returns the number of items currently in the holding tank. */
    public function holdingTankCount(){ return $this->APICall( 'holdingTankCount', "Could not lookup web holding tank count" ); }

    /* GROUP(Holding Tank) category(A category string.) URL(The URL for an initial link to be attached.) type(The link type of above URL.) word(A an initial word to be attached.) caseSensitive(Case-sensitivty of the above word. 1 for case-sensitive, 0 otherwise.) Submits information about a proposed ultralink into the holding tank for review. */
    public function submitUltralink( $category, $URL, $type, $word, $caseSensitive = false ){ return $this->APICall( array('submitUltralink' => $category, 'URL' => $URL, 'urlType' => $type, 'word' => $word, 'caseSensitive' => $caseSensitive ), "Could not submit the ultralink" ); }

    /* GROUP(Holding Tank) resolution(The descision string whether to <b>accept</b> or <b>reject</b> the submitted Ultralink.) category(A category string.) URL(The URL for an initial link to be attached.) urlType(The link type of above URL.) word(A an initial word to be attached.) contributor(<user identifier>) caseSensitive(Case-sensitivty of the above word. 1 for case-sensitive, 0 otherwise.) Removes the submission entry for the new ultralink. If the resolution is 'accept' then it creates the suggested ultralink. */
    public function resolveNewUltralink( $resolution, $category, $URL, $urlType, $word, $contributor, $caseSensitive = false ){ return Ultralink::U( $this->APICall( array('resolveNewUltralink' => $resolution, 'contributor' => $contributor, 'category' => $category, 'URL' => $URL, 'urlType' => $urlType, 'word' => $word, 'caseSensitive' => $caseSensitive ), "Could not resolve the submitted ultralink" ), $this ); }

    public function APICallSub( $sub, $fields, $error )
    {
        global $cMaster;

        if( $call = $cMaster->APICall('0.9.1/db/' . $this->ID . $sub, $fields ) )
        {
            if( $call === true ){ return $call; }
                            else{ return json_decode( $call, true ); }
        }
        else{ commandResult( 500, $error ); }
    }
    public function APICall( $fields, $error ){ return $this->APICallSub( '', $fields, $error ); }

    public static function APICallUp( $fields, $error )
    {
        global $cMaster;

        if( $call = $cMaster->APICall('0.9.1/db', $fields ) )
        {
            if( $call === true ){ return $call; }
                            else{ return json_decode( $call, true ); }
        }
        else{ commandResult( 500, $error ); }
    }
}

$cDB = Database::DB();

?>
