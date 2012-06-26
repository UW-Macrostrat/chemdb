<?php
/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class SplitBkrTable extends Doctrine_Table
{
    /**
     *
     * @return Doctrine_Collection of SplitBkr
     */
    public function getList()
    {
        return Doctrine_Query::create()
            ->from('SplitBkr b')
            ->select('b.id, b.bkr_number')
            ->orderBy('b.id ASC')
            ->execute();
    }
    
    /**
     * Finds and returns an array of beakers missing from the database.
     *
     * @return array of missing beaker names
     **/
    public function findMissingBkrs($bkrList)
    {
        $query = $this->createQuery('b');
        foreach ($bkrList as $bkr) {
            $query->orWhere('b.bkr_number = ?', $bkr);
        }
        $result = $query->execute();
        $found = $result->toArray();
        foreach ($found as $entry) {
            $dbBkrs[] = $entry['bkr_number'];
        }
        $ret = array();
        $diff = array_diff($bkrList, $dbBkrs);
        foreach ($diff as $value) {
            $ret[] = $value;
        }
        return $ret;
    }

}