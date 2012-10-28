liphop
======

PHP class library for accessing the LinkedIn API using the Oauth PECL extension

The static methods in the Li class provide the most accessible way to use the library.

These methods are:

	Li::auth_request($credentials, $api_name, $callback_func, $callback_url='', $scope=array()) - Get perms from LI user
	Li::auth_token($credentials, $api_name) - Get an access token from LI
	Li::call($credentials, $api_name, $arg1, $arg2,...) - Make an API call with the given args
	Li::init($credentials, $api_name) - Create an API instance

FILES

	classes/li.php 					Class of static methods to authorize and call the LinkedIn API
	classes/li_oauth.php 			Class that handles the LinkedIn Oauth authorization
	classes/li_people.php 			Class that implements the LI People API, well the profile bit at least

	examples/api_keys.example.php 	You'll need to pass your API keys and Oauth tokens to every instance.  
									This file saves them as constants and an array and will be used by the examples.
	examples/li.init.php			Create an instance of the People API class
	examples/li.auth.php			Get access token from the LI user and LinkedIn
	examples/li.call.php			Make a call to the People profile API
	examples/li_oauth/				A couple of examples of using the Li_Oauth class directly 

	tests							Unit tests are coming
