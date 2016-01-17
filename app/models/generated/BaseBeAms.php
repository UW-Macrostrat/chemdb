<?php

/**
 * BaseBeAms
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $id
 * @property integer $analysis_id
 * @property integer $be_ams_std_id
 * @property integer $ams_lab_id
 * @property date $date
 * @property string $lab_num
 * @property float $r_to_rstd
 * @property float $interror
 * @property float $exterror
 * @property float $truefrac
 * @property string $notes
 * @property Analysis $Analysis
 * @property BeAmsStd $BeAmsStd
 * @property AmsLab $AmsLab
 * @property Doctrine_Collection $AmsCurrent
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class BaseBeAms extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('be_ams');
        $this->hasColumn('id', 'integer', 4, array(
             'type' => 'integer',
             'unsigned' => '1',
             'primary' => true,
             'autoincrement' => true,
             'length' => '4',
             ));
        $this->hasColumn('analysis_id', 'integer', 2, array(
             'type' => 'integer',
             'unsigned' => '1',
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '2',
             ));
        $this->hasColumn('be_ams_std_id', 'integer', 4, array(
             'type' => 'integer',
             'unsigned' => '1',
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '4',
             ));
        $this->hasColumn('ams_lab_id', 'integer', 4, array(
             'type' => 'integer',
             'unsigned' => '1',
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '4',
             ));
        $this->hasColumn('date', 'date', 25, array(
             'type' => 'date',
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '25',
             ));
        $this->hasColumn('lab_num', 'string', 255, array(
             'type' => 'string',
             'fixed' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '255',
             ));
        $this->hasColumn('r_to_rstd', 'float', 2147483647, array(
             'type' => 'float',
             'unsigned' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '2147483647',
             ));
        $this->hasColumn('interror', 'float', 2147483647, array(
             'type' => 'float',
             'unsigned' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '2147483647',
             ));
        $this->hasColumn('exterror', 'float', 2147483647, array(
             'type' => 'float',
             'unsigned' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '2147483647',
             ));
        $this->hasColumn('truefrac', 'float', 2147483647, array(
             'type' => 'float',
             'unsigned' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '2147483647',
             ));
        $this->hasColumn('notes', 'string', 700, array(
             'type' => 'string',
             'fixed' => 0,
             'primary' => false,
             'notnull' => false,
             'autoincrement' => false,
             'length' => '700',
             ));
    }

    public function setUp()
    {
        parent::setUp();
        $this->hasOne('Analysis', array(
             'local' => 'analysis_id',
             'foreign' => 'id'));

        $this->hasOne('BeAmsStd', array(
             'local' => 'be_ams_std_id',
             'foreign' => 'id'));

        $this->hasOne('AmsLab', array(
             'local' => 'ams_lab_id',
             'foreign' => 'id'));

        $this->hasMany('AmsCurrent', array(
             'local' => 'id',
             'foreign' => 'be_ams_id'));
    }
}