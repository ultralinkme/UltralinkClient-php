#!/usr/bin/env php
<?php

// Copyright © 2016 Ultralink Inc.

namespace UL;

// Including this file will subsequently include all the classes needed for operation and set things up in a default state.
require_once 'Ultralink-API.php';

$APIKey = "<Enter your API Key here>"; // You can get an API Key from your Profile pane in the Ultralink Dashboard.

// There are a couple of variables that are useful and occassionally come into play.
echo "\n";
echo "Current Master: \t"   . Master::$cMaster->description() . "\n"; // The current Ultralink Master you are pointing to.
echo "Current Database: \t" . Database::$cDB->description()   . "\n"; // The current default Database within cMaster that you are working in.
echo "Current User: \t\t"   . User::$cUser->description()     . "\n"; // The current User that will perform API calls by default.

// This is a global variable that indicates whether the process should exit or keep going if a failure occurs.
// This is set to false only for the purposes of this example code.
$shouldExitOnFail = false;

// By default, things are set up to use an anonymous User with the Mainline Database at the https://ultralink.me/ Master.
// So right off the bat you can do anything an unauthenticated User can. For instance, you can load an Ultralink:

// This line below creates an object representing the Ultralink with ID number 58 in $cDB residing at $cMaster.
// You can use Ultralink ID numbers or vanity names to specify which Ultralink you want.
// You can also specify which specific database the Ultralink resides in.
$ul = Ultralink::U( 58 );

// Just creating an Ultralink object doesn't actually perform any API calls or load any data.
// The relevant data is only loaded in on-demand when appropriate.
// Simply referencing the different parts of an Ultralink is enough force it to page in the data behind the scenes.

echo "\nWords:\n";
foreach( $ul->words as $word ){ echo "  " . $word->description() . "\n"; }

echo "\nCategories:\n";
foreach( $ul->categories as $category ){ echo "  " . $category->description() . "\n"; }

echo "\nLinks:\n";
foreach( $ul->links as $link ){ echo "  " . $link->description() . "\n"; }

echo "\nConnections:\n";
foreach( $ul->connections as $connection ){ echo "  " . $connection->description() . "\n"; }

echo "\nPage Feedback:\n";
foreach( $ul->pageFeedback as $pf ){ echo "  " . $pf->description() . "\n"; }

// You can iterate over these object arrays like above, but most often you will want to get and set various things on an Ultralink using get<class name> methods.
echo "\n";
$w = null;
if( $w = $ul->getWord("Spencer Nielsen") ){ echo "Lookup by word string: " . $w->description() . "\n"; }
if(     !$ul->getWord("Some other name") ){ echo "This word was not found on this Ultralink.\n"; }

// Once you have an Ultralink component object, you can modify them.
echo "\n";
echo "Before modification:\t" . $w->description() . "\n";
$w->setCaseSensitive( 0 );
echo "After modification:\t" . $w->description() . "\n";

// There are also set<class name> methods which you can use to add new objects to an Ultralink or easily change the properties of existing ones.
$ul->setWord("Spencer For Hire");   // Adds a new Word object for the string "Spencer For Hire".
$ul->setWord("Spence", 0, 0, 3);    // Adds a new Word object for the string "Spence", not case-sensitive, not the primary Word and with a commonality threshold of 3.
$ul->setWord("Spencer Nielsen", 1); // Changes the existing Word object on this Ultralink for the string "Spencer Nielsen" back to being case-sensitive again.

// The in-memory Ultralink object here has changed to reflect the modifications we performed above.
echo "\nWords:\n";
foreach( $ul->words as $word ){ echo "  " . $word->description() . "\n"; }

// We can examine the exact changes that we have made on the Ultralink.
echo "\nModifications:\n";
$ul->printCurrentModifications();

// To actually write all these pending modifications to the Database in the Master all we need to do is call the sync() method.
echo "\nAttempting to sync\n";
$ul->sync();

// Oh, oops. Looks like an anonymous User is not allowed to actually make changes to the Ultralinks on the Master.
// To actually write changes back to the Master, we need to authenticate.
$me = Master::$cMaster->login($APIKey);
echo "Me: " . $me->description() . "\n";

// Logging in to a Master will automatically set your User to the current User if the current User is anonymous.
echo "Current User: " . User::$cUser->description() . "\n";

// Now that you have actually authenticated, you can actually use sync() to write changes to the database.
// Check out documentation/Documentation.html and browse the each of the classes to get a good idea of what kinds of things you can do.

?>
