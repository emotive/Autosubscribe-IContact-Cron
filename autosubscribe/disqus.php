<?php 


// Include config and icontact class
require_once( 'config.inc' );
require_once( 'includes/Icontact.php' );

// Set the iContact Class

$icontact = new Icontact(
	'https://app.icontact.com/icp',
	$icontact_login,
	$icontact_pass,
	$icontact_apikey
);

// Grab the data from Disqus

$request_url = "http://disqus.com/api/get_forum_posts/?user_api_key=".$disqus_apikey."&forum_id=".$disqus_forumid."&api_version=1.1&limit=30";

$json = file_get_contents($request_url, true); //getting the file content

$decode = json_decode($json, true);  // create JSON in array





try {

	foreach($decode['message'] as $disqus_comment)
	{

	
	// Required identifiers
	$account_id = $icontact->LookUpAccountId();
	$client_folder_id = $icontact->LookUpClientFolderId();
	
	$email = $disqus_comment["anonymous_author"]["email"];
	
	if ($email != "") {
		
		// Add contact to icontact
		$contact_id = $icontact->AddContact( array(
			'firstName' =>  substr($disqus_comment["anonymous_author"]["name"], 0, 49),
			'email' =>  $disqus_comment["anonymous_author"]["email"]
		));
	}
	
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


