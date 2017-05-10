<?php
/**

 (c) Vladimir Bugorkov
 http://bugorkov.ru
 */

defined('_JEXEC') or die();


class WxCmd {
    public static function get($name){
        return urldecode(vRequest::getCmd($name, ""));
    }

    public static function quote($name){
        $db=JFactory::getBbo();
        return $db->quote($this->get($name));
    }

    public static function getObject($prefix, $fields){
        $output=(object)array();
        foreach ($fields as $field){
            $output->$field=$this->get($prefix."_".$field);
        }
        return $output;
    }
}
?>