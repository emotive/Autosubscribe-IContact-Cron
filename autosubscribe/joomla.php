<?php
// Information here: http://www.foundry26.com/2010/03/php-icontact-api-wrapper/

// Load config
require_once( 'config.inc' );


// Include Joomla database paths and icontact class
require_once( 'includes/Icontact.php' );
require_once( '../configuration.php' );

$icontact = new Icontact(
	'https://app.icontact.com/icp',
	$icontact_login,
	$icontact_pass,
	$icontact_apikey
);

//Get the Joomla host variables
$Joomla = new JConfig();


// Connect to Joomla DB
mysql_connect($Joomla->host, $Joomla->user, $Joomla->password) or die(mysql_error());
mysql_select_db($Joomla->db) or die(mysql_error());

// Grab the last 30 user records from the users table
$result = mysql_query("SELECT name, email FROM ".$Joomla->dbprefix."users order by id desc limit 30") or die(mysql_error());  

try {

	while($row = mysql_fetch_array( $result )) {

	//echo "Found ".$row['email']."<br>";
	
	// Required identifiers
	$account_id = $icontact->LookUpAccountId();
	$client_folder_id = $icontact->LookUpClientFolderId();
	
	// Add contact to icontact
	$contact_id = $icontact->AddContact( array(
		'firstName' =>  substr($row['name'], 0, 49),
		'email' =>  $row['email']
	));
	} 

	// List all contacts just added (not subscribed to a list)
	$contacts = $icontact->GetTodaysOrphanContacts();
	//print_r($contacts);
	
	// Subscribe the returned contacts to the list

	foreach($contacts as $unsubcontacts)
	{
		$subscribecontact = $icontact->SubscribeContactToList($unsubcontacts['contactId'],$icontact_listid);
		echo "Subscribed ".$unsubcontacts['email']." (";
		echo $unsubcontacts['contactId'].") to the list ".$icontact_listid."<br><br>";	
	}


} catch ( IcontactException $ex ) {
	// If anything goes wrong with the above this will
	// dump out the interesting parts of the repsonse
	print_r( $ex->GetErrorData() );
}

?>