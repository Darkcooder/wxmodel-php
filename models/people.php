<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

if(!class_exists('VmModel')) require(VMPATH_ADMIN.DS.'helpers'.DS.'vmmodel.php');

/**
 * Model for VirtueMart Orders
 * WHY $this->db is never used in the model ?
 * @package VirtueMart
 */
class VirtueMartModelPeople extends VmModel {
    /**
	 * constructs a VmModel
	 * setMainTable defines the maintable of the model
	 * @author Vladimir Bugorkov
	 */
	function __construct() {
		parent::__construct();
		$this->setMainTable('intents', '#__iguide_intents');
        $this->setIdName('intent_id');
		$this->addvalidOrderingFieldName(array('intent_id','metodic_id','type','name' ) );
        $this->db = JFactory::getDBO();
        $this->setListTmp(array('id'=>'intent_id', 'type_id'=>'type', 'name'=>'name'), array('category'=>'name') );

        $this->userId=JFactory::getUser()->get('id');
        $this->id="source";
        $this->prefix="source";
        $this->me=(object)array("id"=>$this->userId, "type"=>"me", "info"=>array("name"=>"Это Я!"), "location"=>$this->getMyLocation(), "isee"=>$this->getMyIseeStatus());

        $this->mySlogin=(object)$this->getElementsByTableName(array("*"), "#__slogin_users", array("user_id"=>$this->userId))[0];
	}

}