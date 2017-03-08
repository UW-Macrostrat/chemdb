<?php

/**
 * BaseSplitBkr
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $id
 * @property string $bkr_number
 * @property Doctrine_Collection $Split
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class BaseSplitBkr extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('split_bkr');
        $this->hasColumn('id', 'integer', null, array(
             'type' => 'integer',
             'unsigned' => true,
             'primary' => true,
             'autoincrement' => true,
             ));
        $this->hasColumn('bkr_number', 'string', null, array(
             'type' => 'string',
             ));
    }

    public function setUp()
    {
        parent::setUp();
        $this->hasMany('Split', array(
             'local' => 'id',
             'foreign' => 'split_bkr_id'));
    }
}