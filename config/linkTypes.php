<?php

    $linkTypes = json_decode( file_get_contents( dirname(__FILE__) . '/linkTypes.json' ), true );

    $orderedCategories = array();

    function catOrder($a, $b)
    {
        $aVal = 0; if( !empty($linkTypes[$a]["order"]) ){ $aVal = intval($linkTypes[$a]["order"]); }
        $bVal = 0; if( !empty($linkTypes[$b]["order"]) ){ $bVal = intval($linkTypes[$b]["order"]); }
        return $aVal - $bVal;
    }

    function doOrderedCategories()
    {
        global $linkTypes;
        global $orderedCategories;

        $orderedCategories = array();

        foreach( $linkTypes as $cat => $val ){ array_push( $orderedCategories, $cat ); }

        uasort($orderedCategories, "catOrder");
    }
    doOrderedCategories();

    function categoryNumber( $tcat ){ global $orderedCategories; for( $n = 0; $n < count($orderedCategories); $n++ ){ if( $orderedCategories[$n] == $tcat ){ break; } } return $n; }

    function mergeLinkTypes( $customLinkTypes, $resourceLocation )
    {
        global $linkTypes;

        if( is_array($customLinkTypes) )
        {
            foreach( $customLinkTypes as $ccat => $customLinkCat )
            {
                if( !empty($linkTypes[$ccat]) )
                {
                    $existingLinkCat = $linkTypes[$ccat];

                    foreach( $customLinkCat['links'] as $itype => $customLinkType )
                    {
                        if( !empty($existingLinkCat['links'][$itype]) )
                        {
                            $existingLinkType = $existingLinkCat['links'][$itype];
                            foreach( $customLinkType as $setting => $val )
                            {
                                updateLinkType( $itype, $setting, $val );
                            }
                        }
                        else
                        {
                            $linkTypes[$ccat]['links'][$itype] = $customLinkType;
                            if( !empty($resourceLocation) ){ updateLinkType( $itype, "resourceLocation", $resourceLocation ); }
                        }
                    }
                }
                else
                {
                    $linkTypes[$ccat] = $customLinkCat;

                    if( !empty($resourceLocation) )
                    {
                        foreach( $customLinkCat['links'] as $itype => $val )
                        {
                            updateLinkType( $itype, "resourceLocation", $resourceLocation );
                        }
                    }
                }
            }

            doOrderedCategories();
        }
    }

    function linkTypeCondition( $cond, $extra = "" )
    {
        global $linkTypes;

        foreach( $linkTypes as $cat => $category )
        {
            foreach( $category['links'] as $linkType => $link )
            {
                $result = call_user_func_array( $cond, array($cat, $linkType, $link, $extra) );
                if( !empty($result) ){ return $result; }
            }
        }

        return null;
    }

    function typeCompare( $cat, $type, $link, $ltype ){ if( $ltype == $type ){ return $link; } }

    function getLinkType( $ltype )
    {
        return linkTypeCondition( "typeCompare", $ltype );
    }

    function updateLinkType( $ltype, $key, $value )
    {
        global $linkTypes;

        $linkType = getLinkType( $ltype, $key, $value );

        $gotIt = false;

        foreach( $linkTypes as $cat => $category )
        {
            foreach( $category['links'] as $linkType => $link )
            {
                if( $linkType == $ltype )
                {
                    $linkTypes[$cat]['links'][$linkType][$key] = $value;
                    $gotIt = true;
                    break;
                }
            }

            if( $gotIt ){ break; }
        }
    }

    function linkDetect( $cat, $type, $link, $theURL )
    {
        if( !empty($link['detectors']) )
        {
            foreach( $link['detectors'] as $detector )
            {
                if( preg_match( '#' . $detector . '#i', $theURL ) ){ return $type; }
            }
        }
    }

    function detectLinkType( $URL )
    {
        $result = linkTypeCondition( "linkDetect", $URL );

        if( empty($result) ){ $result = 'href'; }

        return $result;
    }

?>
