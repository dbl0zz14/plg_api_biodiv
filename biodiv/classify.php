<?php

// No direct access to this file
defined('_JEXEC') or die;

// Include Auth and biodiv files
$component_path = JPATH_SITE . '/components/com_biodiv';

//error_log("Component path = " . $component_path );

require_once($component_path.'/BiodivAuth.php');
require_once($component_path.'/BiodivHelper.php');

use Joomla\CMS\Response\JsonResponse;


class BiodivApiResourceClassify extends ApiResource
{
	
	public function post()
	{
		$auth = new BiodivAuth();
		$helper = new BiodivHelper();
		$scopestem = $helper->getScopeStem();
		
		$isok = false;
		
		try {
			$isok = $auth->checkToken( $scopestem."/create_classification");
		}
		catch (Exception $e ) {
			error_log("Exception caught out of BiodivAuth");
			
			header('HTTP/1.1 403 Forbidden', true, 403);
			ApiError::raiseError(11001, "Not authorised", 'APIUnauthorisedException');
			
		}	
		if ( $isok ) {
			$app = JFactory::getApplication();
		
			$input = $app->input;
		
			// Get the details from the request.
			$inArray = $input->json->getArray();
			
			// Do some validation
			$isValid = true;
			$err_msg = null;
			$err_code = 100000;
			if (!array_key_exists("origin", $inArray)) {
				$isValid = false;
				$err_msg = "No origin system supplied";
				$err_code = 100005;
			}
			else if (!array_key_exists("sensor_id", $inArray)) {
				$isValid = false;
				$err_msg = "No sensor_id supplied";
				$err_code = 100009;
			}
			else if (!array_key_exists("filename", $inArray)) {
				$isValid = false;
				$err_msg = "No filename supplied";
				$err_code = 100010;
			}
			else if (!array_key_exists("species_type", $inArray)) {
				$isValid = false;
				$err_msg = "No species_type supplied";
				$err_code = 100011;
			}
			else if (!array_key_exists("species", $inArray)) {
				$isValid = false;
				$err_msg = "No species supplied";
				$err_code = 100012;
			}
			else if (!array_key_exists("probability", $inArray)) {
				$isValid = false;
				$err_msg = "No probability supplied";
				$err_code = 100013;
			}
			
			if ( $isValid ) {
				
				// Check sensor id and filename are valid
				$siteId = $inArray["sensor_id"];
				$filename = $inArray["filename"];
				$speciesType = $inArray["species_type"];
				$species = $inArray["species"];
				
				$photoDetails = $helper->getPhotoDetails($siteId, $filename);
				$imageId = null;
				$sequenceId = null;
				if ( $photoDetails ) {
					if ( property_exists($photoDetails, "photo_id") ) {
						$imageId = $photoDetails->photo_id;
					}
					if ( property_exists($photoDetails, "sequence_id") ) {
						$sequenceId = $photoDetails->sequence_id;
					}
				}
				
				if ( !$imageId  ) {
					
					$isValid = false;
					$err_msg = "Cannot find image for sensor id (" . $siteId . ") and filename (" . $filename . ") in database";
					$err_code = 100014;
				}
				else if ( !$sequenceId  ) {
					
					$isValid = false;
					$err_msg = "Cannot find sequence for sensor id (" . $siteId . ") and filename (" . $filename . ") in database";
					$err_code = 100014;
				}
				else {
					// Check species is valid 
					$speciesId = $helper->getSpecies ( $speciesType, $species );
					if ( !$speciesId ) {
						
						$isValid = false;
						$err_msg = "Species invalid, type = " . $speciesType . ", species = " . $species;
						$err_code = 100015;
						
					}
				}
			}
			
			if (!$isValid) {
				error_log ("Incorrect parameters - " . $err_msg );
				
				header('HTTP/1.1 400 Bad Request', true, 400);
				
				ApiError::raiseError($err_code, "Bad Request - " . $err_msg, 'APIValidationException');
				//$this->data = new JsonResponse(null, $err_msg, true);
				//header('HTTP/1.1 400 Bad request', true, 400);
			}
			else {
				
				$origin = $inArray["origin"];
				$probability = $inArray["probability"];
				
				$originRef = null;
				if (array_key_exists("origin_ref", $inArray)) {
					$originRef = $inArray["origin_ref"];
				}
				$model = null;
				if (array_key_exists("model_version", $inArray)) {
					$model = $inArray["model_version"];
				}
				$siteId = null;
				if (array_key_exists("sensor_id", $inArray)) {
					$siteId = $inArray["sensor_id"];
				}
					
				$xmin = null;
				$ymin = null;
				$xmax = null;
				$ymax = null;
				
				// Do we have boundng box coordinates?
				if (array_key_exists("xmin", $inArray) && array_key_exists("ymin", $inArray) && 
					array_key_exists("xmax", $inArray) && array_key_exists("ymax", $inArray)) {
					
					$xmin = $inArray["xmin"];
					$ymin = $inArray["ymin"];
					$xmax = $inArray["xmax"];
					$ymax = $inArray["ymax"];
					
				}
				
				// Write to database
				$classifyId = $helper->classify( $sequenceId, 
													$imageId,
													$origin, 
													$model, 
													$originRef, 
													$siteId, 
													$filename, 
													$speciesType, 
													$species, 
													$speciesId, 
													$probability, 
													$xmin, 
													$ymin, 
													$xmax, 
													$ymax );
				
				// Return response
				if ( !$classifyId ) {
					
					$isValid = false;
					$err_msg = "Failed to write to database for imageId " . $imageId;
					$err_code = 100016;
			
					error_log ("Classify API error - " . $err_msg );
					
					header('HTTP/1.1 400 Bad Request', true, 400);
					
					ApiError::raiseError($err_code, "Write to database failed - " . $err_msg, 'APIValidationException');
				
				}
				else {
					
					header('Access-Control-Allow-Credentials: true');
					header('Access-Control-Allow-Origin: *');

					$result = new \stdClass;
					$result->id = $classifyId;
					
					$this->plugin->setResponse( $result );
					
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