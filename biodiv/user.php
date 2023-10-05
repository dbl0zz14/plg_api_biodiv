<?php

// No direct access to this file
defined('_JEXEC') or die;

// Include Auth and biodiv files
$component_path = JPATH_SITE . '/components/com_biodiv';

//error_log("Component path = " . $component_path );

require_once($component_path.'/BiodivAuth.php');
require_once($component_path.'/BiodivHelper.php');

use Joomla\CMS\Response\JsonResponse;


class BiodivApiResourceUser extends ApiResource
{
	public function get()
	{
		$auth = new BiodivAuth();
		$helper = new BiodivHelper();
		$scopestem = $helper->getScopeStem();
		
		//error_log ("Method = GET" );
	
		$isok = false;
		
		try {
			$isok = $auth->checkToken( $scopestem."/get_user");
		}
		catch (Exception $e ) {
			error_log("Exception caught out of BiodivAuth");
			error_log("Exception message: " . $e->getMessage() );
			
			header('HTTP/1.1 403 Forbidden', true, 403);
			ApiError::raiseError(11001, "Not authorised", 'APIUnauthorisedException');
			
		}
		
		error_log ("Auth check returns " . $isok );
	
		if ( $isok ) {
			
			$app = JFactory::getApplication();
		
			$input = $app->input;
		
			$user_id = $input->getInt('id', null);
			
			if ( !$user_id ) {
				error_log ("No user id" );
				
				header('HTTP/1.1 400 Bad Request', true, 400);
				
				ApiError::raiseError(10000, "Bad Request - No id supplied", 'APIValidationException');
			}
			else {
				error_log ( "Getting user details for id " . $user_id );
				$details = $helper->userExpertise($user_id);
				if ( $details ) {
					error_log ( "Got user details" );
					
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Origin: *');

					$result = new \stdClass;
					$result->id = $user_id;
					$result->expertise = json_encode($details);
					 
					$this->plugin->setResponse( $result );
				}
				else {
					error_log ( "No details for user id " . $user_id );
					
					header('HTTP/1.1 404 Not Found', true, 404);
					
					ApiError::raiseError(12001, "Record not found", 'APINotFoundException');
				}
				
			}
		}
		else {
			error_log ("Auth failed" );
			
			// For now respond Forbidden but could be not authorized and should distinguish between the two?
			header('HTTP/1.1 403 Forbidden', true, 403);
			
			error_log("Exception raised as auth check returned false");
			ApiError::raiseError(11001, "Not authorised", 'APIUnauthorisedException');
		}
		
	}

	public function post()
	{
		$auth = new BiodivAuth();
		$helper = new BiodivHelper();
		$scopestem = $helper->getScopeStem();
		
		error_log ("Method = POST" );
	
		$isok = false;
		
		try {
			$isok = $auth->checkToken( $scopestem."/create_user");
		}
		catch (Exception $e ) {
			error_log("Exception caught out of BiodivAuth");
			
			header('HTTP/1.1 403 Forbidden', true, 403);
			ApiError::raiseError(11001, "Not authorised", 'APIUnauthorisedException');
			
		}	
		if ( $isok ) {
			$app = JFactory::getApplication();
		
			$input = $app->input;
		
			// Get the user details from the request.
			$inArray = $input->json->getArray();
							
			// Do some validation
			$isValid = true;
			$err_msg = null;
			$err_code = 100000;
			if (!array_key_exists("username", $inArray)) {
				$isValid = false;
				$err_msg = "No username supplied";
				$err_code = 100001;
			}
			else if (!array_key_exists("email", $inArray)) {
				$isValid = false;
				$err_msg = "No email supplied";
				$err_code = 100002;
			}
			else if (!array_key_exists("name", $inArray)) {
				$isValid = false;
				$err_msg = "No name supplied";
				$err_code = 100003;
			}
			else if (!array_key_exists("password", $inArray)) {
				$isValid = false;
				$err_msg = "No password supplied";
				$err_code = 100004;
			}
			else if (!array_key_exists("origin", $inArray)) {
				$isValid = false;
				$err_msg = "No origin system supplied";
				$err_code = 100005;
			}
			else if (!array_key_exists("tos", $inArray)) {
				$isValid = false;
				$err_msg = "No terms of service supplied";
				$err_code = 100007;
			}
			else if ($inArray["tos"] != 1) {
				$isValid = false;
				$err_msg = "Terms of service not agreed";
				$err_code = 100008;
			}
			
			if (!$isValid) {
				error_log ("Incorrect parameters - " . $err_msg );
				
				header('HTTP/1.1 400 Bad Request', true, 400);
				
				ApiError::raiseError($err_code, "Bad Request - " . $err_msg, 'APIValidationException');
				//$this->data = new JsonResponse(null, $err_msg, true);
				//header('HTTP/1.1 400 Bad request', true, 400);
			}
			else {
				
				$password = $inArray["password"];
				/* password is encrypted on user save
				$salt   = JUserHelper::genRandomPassword(32);
				$crypted  = JUserHelper::getCryptedPassword($password, $salt);
				$cpassword = $crypted.':'.$salt;
				*/
				
				$username = $inArray["username"];
				$email = $inArray["email"];
				$name = $inArray["name"];
				
				// Does this user already exist? If email the same then yes.  Same username with different email will fail.
				$existingUserEmail = $helper->getUser ( $email );
				if ( $existingUserEmail ) {
					error_log ( "User with that email already exists, returning existing user details" );
					
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Origin: *');

					$result = new \stdClass;
					$result->id = $existingUserEmail->id;
					$result->username = $existingUserEmail->username;
					$result->email = $existingUserEmail->email;
					$result->newuser = 0;
					
					$this->plugin->setResponse( $result );
				}
				else if ( JUserHelper::getUserId($username) ) {
					$err_msg = "Username " . $username . " already in use";
					$err_code = 100007;
					error_log ("Incorrect parameters - " . $err_msg );
				
					header('HTTP/1.1 400 Bad Request', true, 400);
					
					ApiError::raiseError($err_code, "Bad Request - " . $err_msg, 'APIValidationException');
				}
				else {
				
					error_log("Creating user " . $email);
					
				
					$profileMW = array( 
						'tos'=>$inArray["tos"],
						'wherehear'=>$inArray["origin"],	// Could change this to a system identifier
						'subscribe'=>$inArray["subscribe"]
						);
					
					// Add to Registered group
					$groups = array("2"=>"2");
					
					// Can we find out any profile information
					/*
					$profile = JUserHelper::getProfile();
					$arrStr = json_encode($profile);
					error_log($arrStr);
					
					$grps = JUserHelper::getUserGroups(972);
					$arrStr1 = json_encode($grps);
					error_log($arrStr1);
					
					
					$temp = (array) JFactory::getApplication()->getUserState('com_users.edit.profile.data', array());
					$arrStr2 = json_encode($temp);
					error_log($arrStr2);
					*/
					
					$data = array(
					'name'=>$name,
					'username'=>$username,
					//'password'=>$cpassword,
					'password'=>$password,
					'email'=>$email,
					'block'=>0,
					'profileMW'=>$profileMW,
					'groups'=>$groups,
					);
					
					$user = new JUser;

					try{
						if (!$user->bind($data)){
							error_log("User bind returned false");
							error_log($user->getError());
							$err_msg = "User bind - " . $user->getError();
							error_log ( $err_msg );
					
							header('HTTP/1.1 400 Bad Request', true, 400);
					
							ApiError::raiseError(10000, "Bad Request - " . $err_msg, 'APIValidationException');
						}
						if (!$user->save()) {
							error_log($user->getError());
							error_log("User save returned false");
							$err_msg = "User save failed - " . $user->getError();
							
							header('HTTP/1.1 400 Bad Request', true, 400);
					
							ApiError::raiseError(10000, "Bad Request - " . $err_msg, 'APIValidationException');
						}
						if ( !$user->getError() ) {
							error_log("User saved");
							
							error_log ( "Got user details" );
						
							header('Access-Control-Allow-Credentials: true');
							header('Access-Control-Allow-Origin: *');

							$result = new \stdClass;
							$result->id = $user->id;
							$result->username = $user->username;
							$result->email = $user->email;
							$result->newuser = 1;
							
							$this->plugin->setResponse( $result );
						}
						
					}catch(Exception $e){
						error_log($e->getMessage());
						$err_msg = "User create failed with exception - " . $e->getMessage();
						error_log ( $err_msg );
				
						header('HTTP/1.1 400 Bad Request', true, 400);
				
						ApiError::raiseError(10000, "Bad Request - " . $err_msg, 'APIValidationException');
				
						// This is the non com_api version - kept for information
						//$this->data = new JsonResponse(null, $e->getMessage(), true);
						//header('HTTP/1.1 400 Bad Request', true, 400);
					}
				}
			}
		}
		else {
			// For now respond Forbidden but could be not authorized and should distinguish between the two.
			$err_msg = "Exception raised as auth check returned false";
			error_log ( $err_msg );
	
			header('HTTP/1.1 403 Forbidden', true, 403);
	
			ApiError::raiseError(11001, "Not authorised", 'APIUnauthorisedException');
		}
	}
}

?>