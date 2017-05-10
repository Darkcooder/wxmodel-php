<?php
//ini_set('error_reporting', E_ALL);
//ini_set('display_errors', 1);
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

class WXTable{
    protected $table;
    protected $db;

     function __construct($table){
            $this->table=$table;
            $this->$fields=$fields;
            $this->db=JFactory::getBbo();
     }

     public function add($element){

        $this->db->insertObject($this->table, $element);
        $q="SELECT LAST_INSERT_ID()";
        $element->id=$this->db->setQuery($q)->loadResult();
        return $element;
    }

    public function get($properities, $conditions, $fields_prefix=null){
        $fields=$properities;
        if($fields_prefix){
            $fields=array();
            foreach($properities as $field){array_push($fields, $fields_prefix."_".$field);}
        }
        $q=$this->db->getQuery(true);
        $q->select($fields)
            ->from($this->table);
        if(!empty($conditions))$q->where($this->quoteWhere($conditions));
        $result=$this->db->setQuery($q)->loadObjectList();
        if($fields_prefix){
            $formated_result=array();
            foreach($result as $row){
                $formated_row=(object)array();
                foreach($properities as $field){
                    $fieldname=$fields_prefix."_".$field;
                    $formated_row->$field=$row->$fieldname;
                }
                array_push($formated_result, $formated_row);
            }
            return $formated_result;
        }
        return $result;
    }

    public function remove($conditions){
        $q=$this->db->getQuery(true);
        $q->delete($this->table)
            ->where($this->quoteWhere($conditions));
        return $this->db->setQuery($q)->loadResult();
    }

    public function edit($properities, $conditions){
        $q=$this->db->getQuery(true);
        $pro_string=null;
        foreach($properities as $key=>$value){
            if($pro_string)$pro_string.=", ";
           $pro_string.=$key."=".$this->_db->quote($value);
        }
        $q->update($this->table)
            ->set($pro_string)
            ->where($this->quoteWhere($conditions));
        return $this->db->setQuery($q)->loadObjectList();
    }

    private function quoteWhere($where){
        $output=array();
        if(is_array($where)){
            if(count($where)){
                foreach($where as $key=>$value){
                    array_push($output, $key."=".$this->_db->quote($value));
                }
            }

        }

        return $output;
    }
}
?>