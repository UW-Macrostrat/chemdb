<?php
/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class Addsplit extends Doctrine_Migration_Base
{
    public function up()
    {
        $this->createTable('split', array(
             'id' => 
             array(
              'type' => 'integer',
              'length' => 4,
              'fixed' => false,
              'unsigned' => true,
              'primary' => true,
              'autoincrement' => true,
             ),
             'analysis_id' => 
             array(
              'type' => 'integer',
              'length' => 2,
              'fixed' => false,
              'unsigned' => true,
              'primary' => false,
              'notnull' => true,
              'autoincrement' => false,
             ),
             'split_bkr_id' => 
             array(
              'type' => 'integer',
              'length' => 4,
              'fixed' => false,
              'unsigned' => true,
              'primary' => false,
              'notnull' => true,
              'autoincrement' => false,
             ),
             'split_num' => 
             array(
              'type' => 'integer',
              'length' => 1,
              'fixed' => false,
              'unsigned' => true,
              'primary' => false,
              'default' => '1',
              'notnull' => true,
              'autoincrement' => false,
             ),
             'split_bkr_name' => 
             array(
              'type' => 'string',
              'fixed' => false,
              'unsigned' => false,
              'primary' => false,
              'notnull' => false,
              'autoincrement' => false,
              'length' => NULL,
             ),
             'wt_split_bkr_tare' => 
             array(
              'type' => 'float',
              'length' => 18,
              'fixed' => false,
              'unsigned' => false,
              'primary' => false,
              'default' => '0.00',
              'notnull' => true,
              'autoincrement' => false,
             ),
             'wt_split_bkr_split' => 
             array(
              'type' => 'float',
              'length' => 18,
              'fixed' => false,
              'unsigned' => false,
              'primary' => false,
              'default' => '0.00',
              'notnull' => true,
              'autoincrement' => false,
             ),
             'wt_split_bkr_icp' => 
             array(
              'type' => 'float',
              'length' => 18,
              'fixed' => false,
              'unsigned' => false,
              'primary' => false,
              'default' => '0.00',
              'notnull' => true,
              'autoincrement' => false,
             ),
             ), array(
             'indexes' => 
             array(
             ),
             'primary' => 
             array(
              0 => 'id',
             ),
             ));
    }

    public function down()
    {
        $this->dropTable('split');
    }
}