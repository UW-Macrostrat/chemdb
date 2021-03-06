<?php

class SampleTable extends Doctrine_Table
{
    public function countLike($name)
    {
        return Doctrine_Query::create()
               ->from('Sample s')
               ->select('COUNT(s.id) num_samples')
               ->where('s.name LIKE ?', "%$name%")
               ->execute(array(), Doctrine_Core::HYDRATE_SINGLE_SCALAR);
    }

    public function fetchViewdataById($id)
    {
        return Doctrine_Query::create()
            ->from('Sample s')
            ->leftJoin('s.Project')
            ->leftJoin('s.Analysis a')
            ->leftJoin('s.AlcheckAnalysis aa')
            ->leftJoin('a.Batch b')
            ->leftJoin('b.BeCarrier')
            ->leftJoin('b.AlCarrier')
            ->leftJoin('b.Analysis ban')
            ->leftJoin('ban.BeAms bams')
            ->leftJoin('bams.BeAmsStd bamstd')
            ->leftJoin('bamstd.BeStdSeries')
            ->leftJoin('ban.AlAms aams')
            ->leftJoin('aams.AlAmsStd aamstd')
            ->leftJoin('aamstd.AlStdSeries')
            ->orderBy('ban.id ASC')
            ->addOrderBy('bams.date DESC')
            ->where('s.id = ?', $id)
            ->fetchOne();
    }
}