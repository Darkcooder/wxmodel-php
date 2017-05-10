<?php
/**

 (c) Vladimir Bugorkov
 http://bugorkov.ru
 */

defined('_JEXEC') or die();

if(!class_exists('WxCmd')) require(VMPATH_ADMIN .DS. 'helpers' .DS. 'wxcmd.php');
if(!class_exists('WxTable')) require(VMPATH_ADMIN .DS. 'helpers' .DS. 'wxtable.php');

//define('USE_SQL_CALC_FOUND_ROWS' , true);

class WxModel {

    static private $_models = array();

    protected $table;
    protected $tableName;
    protected $action;
    protected $userId;
    protected $mainTable;

    public function __construct(){
        $this->action=WxCmd::get("action");
        $this->userId=JFactory::getUser()->get('id');
    }

    static function getModel($name=false){
        $name = strtolower($name);
		$className = 'WxModel'.ucfirst($name);

		if(empty(self::$_models[strtolower($className)])){
			if( !class_exists($className) ){

				$modelPath = VMPATH_ADMIN.DS."models".DS."wx".DS.$name.".php";

				if( file_exists($modelPath) ){
					require( $modelPath );
				}
				else{
					vmWarn( 'Model '. $name .' not found.' );
					echo 'File for Model '. $name .' not found.';
					return false;
				}
			}

			self::$_models[strtolower($className)] = new $className();
			return self::$_models[strtolower($className)];
		} else {
			return self::$_models[strtolower($className)];
		}

	}
/*
    protected $listTmp;
    protected $pageTmp;
    protected $userId;
    protected $id;


    protected function infoFormat($input){
        $output=array();
        foreach($input as $in){
             $ou=(object)array();
             $ou->id=null;
             $ou->info=(object)array();
             foreach($in as $key=>$value){
                 if($key=="id")$ou->id=$value;
                 else $ou->info->$key=$value;
             }
             $ou->actions=(object)array(); //Добавить доступные действия
             array_push($output, $ou);
        }
        return $output;
    }

    protected function groupElements($elements, $fieldGroups, $rootFields){
        $output=array();
        foreach($elements as $el){
            $outel=(object)array();
             foreach($rootFields as $field){
                 $outel->$field=$el->$field;
             }
             foreach($fieldGroups as $group=>$fields){
                 $outel->$group=(object)array();
                 foreach($fields as $field){
                     $outel->$group->$field=$el->$field;
                 }
             }
             array_push($output, $outel);
        }
        return $output;
    }
    protected function addStaticFields(&$elements, $fields){
        foreach($elements as &$el){
            foreach($fields as $key=>$value){$el->$key=$value;}
        }
    }
    protected function addDynamicFieldToGroup(&$elements, $group_name, $field_name, $function){
        foreach($elements as &$el){
             $el->$group_name->$field_name=$this->$function($el);
        }
    }
    protected function renameFields(&$elements, $fields){
        foreach($elements as &$el){
            foreach($fields as $before=>$after)$el->$after=$el->$before;
        }
        return $elements;
    }
    protected function selectString($str, $from){
        foreach($from as $val){
            if($str==$val) return $val;
        }
        return null;
    }

    protected function simpleAction($set){
        $fields=$set->fields;
        if(!is_int(array_search("id", $fields)))array_push($fields, "id");
        //return $set->static_conditions;

        switch ($this->action){
            case "get":
                $data=$this->infoFormat($this->getElementsByTableName($fields, $set->table, $this->getConditions($set)));
            break;
            case "edit":
                $data=$this->editElementsByTableName($this->getCmdObject($set->post_prefix, $set->fields), $set->table, $this->getConditions($set));
            break;
            case "add":
                $data=$this->addElementByTableName($this->getCmdObject($set->post_prefix, $set->fields), $set->table);
            break;
            case "remove":
                $data=$this->removeElementsByTableName($set->table, $this->getConditions($set));
            break;
        }

        return (object)array(
            "data"=>$data,
            "fields"=>$set->fields,
            "conditions"=>$set->conditions
        );
    }

    protected function getConditions($set){
        $this->conditions=(object)array();
        if($set->conditions)$this->conditions=$this->getCmdObject($set->post_prefix, $set->conditions);
        if($set->static_conditions)$this->conditions=(object)array_merge((array)$this->conditions, (array)$set->static_conditions);
        return $this->conditions;
    }

    protected function checkElementAccess($set){

        //Уязвимость: проверяется только перый элемент выборки, действие может производиться для всей выборки по условию
        //return $this->conditions;
        $element=$this->getElementsByTableName(array("id", "user_id"), $set->table, $this->getConditions($set))[0];
        $flag=$set->access_flag;
        $accountType=$this->getElementsByTableName(array($flag), "#__slogin_users", array("user_id"=>$this->userId))[0]->$flag;
        if($accountType==null)$accountType="default";
        $access=$set->access_policy->$accountType;

        //$this->why=array($accountType);
        //return false;
        //Если для меня задан иной идентефикатор доступа в таблице с суффиксом access, проверить блок с заданным идентефикатором объекта Access
        if(array_search($this->_db->replacePrefix($set->table, "#__")."_access", $this->_db->getTableList())!=false){     //проверка наличия access-таблицы
            $accResult=$this->getElementsByTableName(array("access"), $set->table."_access", array("user_id"=>$this->userId, "base_id"=>$element->id));
            if($accResult[0]){        //проверка наличие access-записи
                $accId=$accResult[0]->access;
                if(!(array_search($this->action, $access->$accId)!=false)) return true; else return false;       //проверка прав доступа
            }
        }

        //Если элемент принадлежит мне, проверить наличие действия в блоке "My" объекта Access
        if($element->user_id==$this->userId){
            if(is_integer(array_search($this->action, $access->my))) return true;  else return false;
        }

        //Элемент является чужим, проверить блок foreign объекта Access
        if(is_integer(array_search($this->action, $access->foreign))) return true;

        return false;
    }

    protected function setElementAccess($table, $conditions, $user_id, $access){
        $element=$this->getElementsByTableName(array("id", "user_id"), $table, $conditions)[0];
        if($element->user_id!=$this->userId)return array("status"=>"Acces denied!");
        if(array_search($this->_db->replacePrefix($table, "#__")."_access", $this->_db->getTableList())!=false){     //проверка наличия access-таблицы
            $accResult=$this->getElementsByTableName(array("access"), $table."_access", array("user_id"=>$user_id, "base_id"=>$element->id));
            if($accResult[0]){        //проверка наличие access-записи
                return $this->editElementsByTableName(array("access"=>$access), $table."_access", array("base_id"=>$element->id, "user_id"=>$user_id));
            }else{
                return $this->addElementByTableName((object)array("base_id"=>$element->id, "user_id"=>$user_id, "access"=>$access));
            }
        }
    }

    protected function privateAction($set){
        $this->why=null;
        //return $set->static_conditions;
        if($this->checkElementAccess($set))
        return $this->simpleAction($set);
        return array("status"=>"Access denied!", "why"=>$this->why);
    }

    protected function childPrivateAction($set){
        $accSet=$set->paerent;
        if($set->access_policy)$accSet=$set;
        if($this->checkElementAccess($accSet)) return $this->simpleAction($set);
        return array("status"=>"Access denied!");
    }

    protected function linkedPrivateAction($set){
        $link_key=$set->link->key_field;
        $data_key=$set->link_key;
        $links=$this->getElementsByTableName(array($link_key), $set->link->table, $this->getConditions($set->link));
        $output=(object)array();
        $data=array();
        $linkedSet=$set;
        //return $links;
        if(!$linkedSet->static_conditions)$linkedSet->static_conditions=(object)array();
        foreach($links as $link){
            $linkedSet->static_conditions->$data_key=$link->$link_key;
            $output=$this->privateAction($linkedSet);
            $data=array_merge($data, $output->data);
        }

        $output->data=$data;
        return $output;
    }

    protected function multiLinkedPrivateAction($set){
/*        foreach ($set->links as $key=>$linkset){
            $link_key=$linkset->key_field;
            $links=$this->getElementsByTableName(array($link_key), $linkset->table, $linkset->conditions);
        }

        $link_key=$set->link->key_field;
        $data_key=$set->link_key;
        $links=$this->getElementsByTableName(array($link_key), $set->link->table, $set->link->conditions);
        $output=(object)array();
        $data=array();
        $linkedSet=$set;

        foreach($links as $link){
            $linkedSet->conditions->$data_key=$link->$link_key;
            $output=$this->privateAction($linkedSet);
            $data=array_merge($data, $output->data);
        }
        $output->data=$data;
        return $output;
    }

    protected function getForm(){
        $fields=vRequest::getCmd("fields", array());
        $values=vRequest::getCmd("values", array());
        $form=array();
        if(sizeof($fields)==sizeof($values)){
         for($i=0; sizeof($fields)>$i; $i++){
             $field=$fields[$i];
            $form[$field]=urldecode($values[$i]);
         }
    }
         return $form;
    }

    protected function getMultiple($name){
        $fields=vRequest::getCmd("fields", array());
        $values=vRequest::getCmd("values", array());
        $multiple=array();
        if(sizeof($fields)==sizeof($values)){
         for($i=0; sizeof($fields)>$i; $i++){
             $field=$fields[$i];
            if ($field==$name)$multiple[]=urldecode($values[$i]);
         }
        }
        return $multiple;
    }

    protected function checkAccount($user_id, $type){
        $lics=$this->getElementsByTableName(array("type", "begin", "end", "priority"), "#__iguide_lics", array("user_id"=>$user_id));
        $now=date_timestamp_get(date_create());
        foreach ($lics as $lic){
             $begin=date_timestamp_get(date_create_from_format ("Y-m-d H:i:s", $lic->begin));
             $end=date_timestamp_get(date_create_from_format ("Y-m-d H:i:s", $lic->end));
             if($begin<$now&&$now<$end&&$priority<=$lic->priority){
                 if ($lic->type==$type) return true;
             }
        }
        return false;

    }

    public function checkMyAccount(){
        return $this->checkAccount($this->userId, $this->getCmd("type"));
    }

    protected function removeBlock($blockTmp){
        $q=$this->_db->getQuery(true);
        $q->delete(" #__iguide_".$this->prefix."_".$blockTmp['name']."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($blockTmp['id']));
        $result=$this->_db->setQuery($q)->loadResult();
        foreach($blockTmp["rows"] as $row){
             $q=$this->_db->getQuery(true);
             $q->delete(" #__iguide_".$this->prefix."_".$row['name']."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND ".$this->prefix."_".$blockTmp['name']."_id=".$this->_db->quote($blockTmp['id']));
            $result=$this->_db->setQuery($q)->loadResult();
            $q=$this->_db->getQuery(true);
            $q->delete(" #__iguide_".$this->prefix."_".$row['name']."_fields")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND ".$this->prefix."_".$blockTmp['name']."_id=".$this->_db->quote($blockTmp['id']));
            $result=$this->_db->setQuery($q)->loadResult();
        }
    }

    protected function removeRow($rowName, $rowId, $blockId){
        $q=$q=$this->_db->getQuery(true);
        $q->delete(" #__iguide_".$this->prefix."_".$rowName."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($rowId));
        $result=$this->_db->setQuery($q)->loadResult();
        $q=$q=$this->_db->getQuery(true);
        $q->delete(" #__iguide_".$this->prefix."_".$rowName."_fields")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND ".$this->prefix."_".$rowName."_id=".$this->_db->quote($rowId));
        return $this->_db->setQuery($q)->loadResult();
    }

    protected function removeField($rowName, $fieldId){
        $q=$q=$this->_db->getQuery(true);
        $q->delete(" #__iguide_".$this->prefix."_".$rowName."_fields")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($fieldId));
        return $this->_db->setQuery($q)->loadResult();
    }


    protected function pushPro($input, $fields){
        $output=array();
        foreach($input as $field){
            foreach($fields as $key=>$value){
                 $field->$key=$value;
            }
            array_push($output, $field);
        }
        return $output;
    }

    protected function editBlock($blockName, $blockId, $properities){
        $q=$this->_db->getQuery(true);
        $q->update(" #__iguide_".$this->prefix."_".$blockName."s")
            ->set($properities)
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($blockId));
        return $this->_db->setQuery($q)->loadResult();
        return $properities;
    }

    protected function editRow($rowName, $rowId, $properities){
        $q=$this->_db->getQuery(true);
        $q->update(" #__iguide_".$this->prefix."_".$rowName."s")
            ->set($properities)
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($rowId));
        return $this->_db->setQuery($q)->loadResult();
        //return array($q);
    }

    protected function editField($rowName, $fieldId, $name, $measure){
        $q=$this->_db->getQuery(true);
        $q->update(" #__iguide_".$this->prefix."_".$rowName."_fields")
            ->set(array("name=".$this->_db->quote($name), "measure=".$this->_db->quote($measure)))
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND id=".$this->_db->quote($fieldId));
        return $this->_db->setQuery($q)->loadResult();
    }

    protected function addUnion($unionName, $properities){
        $union=new stdClass();
        return $this->addElement($union, $properities, $unionName);
    }

    protected function addBlock($blockName, $properities, $union=null){
        $block=new stdClass();
        if($union!=null){
            $uanme=$union['name']."_id";
            $block->$uanme=$union['id'];
        }
        return $this->addElement($block, $properities, $blockName);
    }

    protected function addRow($rowName, $properities, $block){
        $row=new stdClass();
        $rowname=$this->prefix."_".$block['name']."_id";
        $row->$rowname=$block['id'];
        return $this->addElement($row, $properities, $rowName);
    }

    protected function addField($row, $name, $measure){
        $field=new stdClass();
        $rowname=$row['name']."_id";
        $field-> $rowname=$row['id'];
        return $this->addElement((object)$row, array("name"=>$name, "measure"=>$measure), $row["name"]."_field");
    }

    protected function addElement($element, $properities, $elementName){
        $idi=$this->prefix."_id";
        $element->$idi=$this->id;
        $element->user_id=$this->userId;
        foreach ($properities as $properity=>$value){
            $element->$properity=$value;
        }
        $this->db->insertObject("#__iguide_".$this->prefix."_".$elementName."s", $element);
        $q="SELECT LAST_INSERT_ID()";
        $element->id=$this->_db->setQuery($q)->loadResult();
        return $element;
    }



    protected function getElements($properities, $elementTreeName, $conditions){
        $q=$this->_db->getQuery(true);
        $q->select($properities)
            ->from(" #__iguide_".$this->prefix.$this->decodeTreeName($elementTreeName)."s")
            ->where(array_merge((count($elementTreeName))?$this->standartConditions():$this->rootConditions(), $this->quoteWhere($conditions)));
        return $this->_db->setQuery($q)->loadObjectList();
    }

    protected function getRootElements($properities, $conditions){
        $q=$this->_db->getQuery(true);
        $q->select($properities)
            ->from(" #__iguide_".$this->prefix."s")
            ->where(array_merge($this->rootConditions(), $this->quoteWhere($conditions)));
        return $this->_db->setQuery($q)->loadObjectList();
    }

    protected function removeElements($elementTreeName, $conditions){
        $q=$this->_db->getQuery(true);
        $q->delete(" #__iguide_".$this->prefix.$this->decodeTreeName($elementTreeName)."s")
            ->where(array_merge((count($elementTreeName))?$this->standartConditions():$this->rootConditions(), $this->quoteWhere($conditions)));
        return $this->_db->setQuery($q)->loadResult();
    }



     protected function editElements($properities, $elementTreeName, $conditions){
        $table="#__iguide_".$this->prefix.$this->decodeTreeName($elementTreeName)."s";
        $conditions=array_merge((count($elementTreeName))?$this->standartConditions():$this->rootConditions(), $conditions);
        return $this->editElementsByTableName($properities, $table, $conditions);
    }



    private function standartConditions(){
        return array($this->prefix."_id=".$this->_db->quote($this->id),"user_id=".$this->_db->quote($this->userId));
    }

    private function rootConditions(){
         return array("user_id=".$this->_db->quote($this->userId));
    }

    private function decodeTreeName($treeName){
        $output="";
        foreach($treeName as $el){
            $output=$output."_".$el;
        }
        return $output;
    }



    protected function getUnions($unionName, $properities, $blocks, $rows){
        $q=$this->_db->getQuery(true);
        $select=array("id");
        $select=array_merge($select, $properities);
        $q->select($select)
            ->from(" #__iguide_".$this->prefix."_".$unionName."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId));
        $unionsTable= $this->_db->setQuery($q)->loadObjectList();
        return $this->formatUnion($unionsTable, $unionName, $properities, $blocks, $rows);
    }

    protected function formatUnion($tableData, $unionName, $properities, $blocks, $rows){
        $unions=array();
        foreach($tableData as $tableUnion){
            $unios=$this->formatProperities($tableUnion, $properities);
            $union->id=$tableUnion->id;
            foreach($blocks as $block){
                $blockname=$block->name."s";
                $union->$blockname=$this->getBlocks($block->name, $block->properities, $rows, array("name"=>$unionName, "id"=>$union->id) );
            }
            array_push($unions, $union);
        }
        return $unions;
    }

    protected function getBlocks($blockName, $properities, $rows=null, $union=null){
        $q=$this->_db->getQuery(true);
        $select=array("id");
        $select=array_merge($select, $properities);
        $q->select($select)
            ->from(" #__iguide_".$this->prefix."_".$blockName."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId));
        if($union!=null)$q=$q." AND ".$union['name']."_id=".$union['id'];
        $blocksTable= $this->_db->setQuery($q)->loadObjectList();
        return $this->formatBlock($blocksTable, $blockName, $properities, $rows);
    }

    protected function formatProperities($object, $fields){
        $_object=new stdClass();
        $_object->properities=array();
        foreach($fields as $name){
            $field=new stdClass();
            $field->name=$name;
            $field->value=$object->$name;
            array_push($_object->properities, $field);
        }
        return $_object;
    }

    protected function formatBlock($tableData, $blockName, $properities, $rows){
        $blocks=array();
        foreach($tableData as $tableBlock){
            $block=$this->formatProperities($tableBlock, $properities);
            $block->id=$tableBlock->id;
            if($rows!=null){
              foreach($rows as $row){
                $rowname=$row->name."s";
                $block->$rowname=$this->getBlockRows($row->name, $blockName, $block->id, $row->properities);
              }
            }
            array_push($blocks, $block);
        }
        if(!count($blocks)){
            $init=new stdClass();
            $init->id=null;
            $init->properities=array();
            foreach($properities as $name){
                $field=new stdClass();
                $field->name=$name;
                $field->value=null;
                array_push($init->properities, $field);
            }
            array_push($blocks, $init);
        }
        return $blocks;
    }

    protected function formatRow($tableData, $rowsName, $properities){
        $rows=array();
        foreach($tableData as $tableRow){
            $row=$this->formatProperities($tableRow, $properities);
            $row->id=$tableRow->id;
            $row->fields=$this->getRowFields($rowsName, $row->id);
            array_push($rows, $row);
        }
        if(!count($rows)){
            $init=new stdClass();
            $init->id=null;
            $init->properities=array();
            foreach($properities as $name){
                $field=new stdClass();
                $field->name=$name;
                $field->value=null;
                array_push($init->properities, $field);
            }
            array_push($rows, $init);
        }
        return $rows;
    }

    protected function getBlockRows($rowsName, $blockName, $blockId, $properities){
        $q=$this->_db->getQuery(true);
        $select=array("id");
        $select=array_merge($select, $properities);
        $q->select($select)
            ->from(" #__iguide_".$this->prefix."_".$rowsName."s")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND ".$this->prefix."_".$blockName."_id=".$this->_db->quote($blockId));
        $rowsTable= $this->_db->setQuery($q)->loadObjectList();
        return $this->formatRow($rowsTable, $rowsName, $properities);
        //return $rowsTable;
    }

    protected function getRowFields($rowsName, $rowId){
        $q=$this->_db->getQuery(true);
        $q->select(array("id", "name", "measure"))
            ->from("#__iguide_".$this->prefix."_".$rowsName."_fields")
            ->where($this->prefix."_id=".$this->_db->quote($this->id)." AND user_id=".$this->_db->quote($this->userId)." AND ".$this->prefix."_".$rowsName."_id=".$this->_db->quote($rowId));
        return $this->_db->setQuery($q)->loadObjectList();
    } */
}
?>