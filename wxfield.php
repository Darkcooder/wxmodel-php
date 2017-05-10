<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
if(!class_exists('VmModel')) require(VMPATH_ADMIN.DS.'helpers'.DS.'vmmodel.php');

class WXField extends VmModel{
     function __construct($table, $field){
         	parent::__construct();
            $this->table=$table;
            $this->$field=$field;
     }
}