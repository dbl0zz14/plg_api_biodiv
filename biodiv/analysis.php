<?php

// No direct access to this file
defined('_JEXEC') or die;

// Include Auth and biodiv files
$component_path = JPATH_SITE . '/components/com_biodiv';

//error_log("Component path = " . $component_path );

require_once($component_path.'/BiodivAuth.php');
require_once($component_path.'/BiodivHelper.php');

use Joomla\CMS\Response\JsonResponse;


class BiodivApiResourceAnalysis extends ApiResource
{
	
	public function post()
	{
		$auth = new BiodivAuth();
		$helper = new BiodivHelper();
		$scopestem = $helper->getScopeStem();
		
		$isok = false;
		
		try {
			$isok = $auth->checkToken( $scopestem."/create_analysis");
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
			
			// Assumes first parameter is id
			$analysisType = $input->getString('id', null);
			
			if ( $analysisType == 'ruleofthumb' ) {
		
				// data = {
					  // ‘origin: ‘MammalWeb’,
					  // ‘ai_type: ‘CAI’,
					  // ‘ai_version’: ‘UK Mammals V7’,
					  // ‘analysis_version’: ‘Rule of thumb 1’, 
					  // ‘sequence_id’: 1234567,
					  // ‘species’:[34,20,20]
				// }

				
				// Do some validation
				$isValid = true;
				$err_msg = null;
				$err_code = 100000;
				if (!array_key_exists("origin", $inArray)) {
					$isValid = false;
					$err_msg = "No origin system supplied";
					$err_code = 100005;
				}
				else if (!array_key_exists("ai_type", $inArray)) {
					$isValid = false;
					$err_msg = "No AI type supplied";
					$err_code = 100017;
				}
				else if (!array_key_exists("ai_version", $inArray)) {
					$isValid = false;
					$err_msg = "No AI version supplied";
					$err_code = 100018;
				}
				else if (!array_key_exists("analysis_version", $inArray)) {
					$isValid = false;
					$err_msg = "No analysis version supplied";
					$err_code = 100019;
				}
				else if (!array_key_exists("sequence_id", $inArray)) {
					$isValid = false;
					$err_msg = "No sequence_id supplied";
					$err_code = 100020;
				}
				else if (!array_key_exists("species", $inArray)) {
					$isValid = false;
					$err_msg = "No species supplied";
					$err_code = 100012;
				}
				
				$species = null;
				
				if ( $isValid ) {
					$species = $inArray["species"];
					if ( ! is_array($species) ) {
						$isValid = false;
						$err_msg = "Species should be array";
						$err_code = 100021;
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
					$aiType = $inArray["ai_type"];
					$aiVersion = $inArray["ai_version"];
					$analysisVersion = $inArray["analysis_version"];
					$sequenceId = $inArray["sequence_id"];
					
					
					// Write to database
					$analysisId = $helper->ruleOfThumb( $origin, 
														$aiType,
														$aiVersion, 
														$analysisVersion, 
														$sequenceId, 
														$species );
					
					// Return response
					if ( !$analysisId ) {
						
						$isValid = false;
						$err_msg = "Failed to write to database for sequence " . $sequenceId;
						$err_code = 100016;
				
						error_log ("Classify API error - " . $err_msg );
						
						header('HTTP/1.1 400 Bad Request', true, 400);
						
						ApiError::raiseError($err_code, "Write to database failed - " . $err_msg, 'APIValidationException');
					
					}
					else {
						
						header('Access-Control-Allow-Credentials: true');
						header('Access-Control-Allow-Origin: *');

						$result = new \stdClass;
						$result->id = $analysisId;
						
						$this->plugin->setResponse( $result );
						
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