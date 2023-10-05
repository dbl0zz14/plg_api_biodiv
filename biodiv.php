<?php

// No direct access to this file
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

// API class
class plgAPIBiodiv extends ApiPlugin
{
	public function __construct(&$subject, $config = array())
	{
		parent::__construct($subject, $config = array());
		
		// Set resource path
		ApiResource::addIncludePath(dirname(__FILE__).'/biodiv');
		
		// Load language files
		$lang = JFactory::getLanguage(); 
		$lang->load('com_biodiv', JPATH_ADMINISTRATOR, '', true);
		
		// Set the user resources to be public - we will handle the OAuth2 client credentials flow rather than using com_api and Joomla access
		$this->setResourceAccess('user', 'public', 'get');
		$this->setResourceAccess('user', 'public', 'post');
		$this->setResourceAccess('classify', 'public', 'post');
	}
}

?>