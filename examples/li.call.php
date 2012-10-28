<?php
/**
* Example of the using the Li class to fetch current user's resume profile data.
*
* @author Dan Mills <dan@lunamouse.com>
* @license http://opensource.org/licenses/bsd-license.php
*/

// Load the Li class
require_once('../classes/li.php');

// Set your LinkedIn application's API Key and Secret Key.
include('./api_keys.php');

// You'll need to have an access token for the user.
// In the li.auth.php example we saved one in the sesion.
session_start();
if (isset($_SESSION['user_token']) and isset($_SESSION['user_secret']))
{
	$credentials['user_token'] = $_SESSION['user_token'];
	$credentials['user_secret'] = $_SESSION['user_secret'];
}

// This array defines the profile fields we want to fetch. Sub fields are also
// arrays.  See https://developer.linkedin.com/documents/profile-fields.  This
// class fetches LI data in JSON format, so use camel-case names rather than
// the hyphens shown in the LI docs (e.g. 'formattedName', not 'formatted-name').
$fields = array(
	'formattedName',
	'headline',
	'summary',
	'emailAddress',
	'phoneNumbers',
	'positions' => array(
	                 'title','company'=>array('name'),'startDate','endDate',
	                 'isCurrent','summary'
	               ),
	'educations' => array(
	                  'schoolName','fieldOfStudy','degree','startDate','endDate'
	                ),
	'skills' => array('skill'=>array('name'), 'proficiency')
);

// Li:call calls the given class::method and provides exception handling.  Here
// we pass our API and access toekn credentials to a call to people::profile().
// The fields array we set above will be passed on to profile().
$person = Li::call($credentials, 'people::profile', $fields);

// If $person has an error_code property, there must have been an oopsie.
// But if everything worked, let's take a better look at $person.
echo isset($person->error_code) ?
     "<p>Error: {$person->error_message}</p>" :
	 '<pre>$person = '.print_r($person,1).'</pre>';
?>