<?php
namespace App\Components\MenuCmp\Model;

class PolozkyMenu extends \App\Model\Table{
    /** @var string */
    protected $tableName = 'cmp_polozkymenu';
    
    public function insert($values)
    {
        return $this->getTable()
                    ->insert(array(
                        $values
        ));
    }
}
