<?php

/**
 * Performs operations on a batch object, primarily calculations.
 */
class Batch extends BaseBatch
{

    public function getBlank($type='Be')
    {
        // first find our blank
        foreach ($this->Analysis as $tmpan) {
            if ($tmpan->sample_type == 'BLANK') {
                $blank = $tmpan;
                break;
            }
        }

        if (isset($blank) && $type=='Al' && $blank->wt_al_carrier == '0') {
            // try to find the most recent Aluminum blank with this carrier
            $blank = Doctrine_Core::getTable('Analysis')
                ->createQuery('a')
                ->leftJoin('a.Batch b')
                ->leftJoin('a.AlAms ams')
                ->select('a.*')
                ->where('b.al_carrier_id = ?', $blank->Batch->al_carrier_id)
                ->andWhere('a.sample_type = ?', 'BLANK')
                ->andWhere('a.wt_al_carrier > ?', 0)
                ->andWhere('b.start_date < ?', $blank->Batch->start_date)
                ->orderBy('b.start_date DESC')
                ->limit(1)
                ->fetchOne();
        }

        return $blank;
    }

    /**
     * Do calculations for our reports. The batch must already have nearly all its data
     * to do these calculations. Populate the data structure first. If you specify that
     * you want statistics with the first parameter, the resulting array will have
     * additional fields:
     *
     * Standard deviations for Al and Be
     * al_sd
     * be_sd are
     *
     * Percentage errors from the expected values for each element:
     * al_pct_err
     * be_pct_err
     *
     * Percentage recoveries:
     * be_recovery
     * al_recovery
     *
     * @param bool $stats default is false, true if you want statistics calculated.
     * @return array
     **/
    public function getReportArray($stats = false)
    {
        // get an array representation of the batch
        $batch = $this->toArray();
        // do some math for the derived weights and statistics
        $batch['nsamples'] = count($batch['Analysis']);
        $batch['max_nsplits'] = 0;
        $batch['max_nruns'] = 0;
        $batch['wt_be_carrier_disp'] = 0;
        $batch['wt_al_carrier_disp'] = 0;
        foreach ($batch['Analysis'] as &$a) {
            $a['nsplits'] = count($a['Split']);
            if ($a['nsplits'] > $batch['max_nsplits']) {
                $batch['max_nsplits'] = $a['nsplits'];
            }
            $a['wt_sample'] = $a['wt_diss_bottle_sample'] - $a['wt_diss_bottle_tare'];
            $a['wt_HF_soln'] = $a['wt_diss_bottle_total'] - $a['wt_diss_bottle_tare'];
            $batch['wt_be_carrier_disp'] += $a['wt_be_carrier'];
            $batch['wt_al_carrier_disp'] += $a['wt_al_carrier'];

            // calculate weights and dilution factors
            foreach ($a['Split'] as &$s) {
                $s['nruns'] = count($s['IcpRun']);
                if ($s['nruns'] > $batch['max_nruns']) {
                    $batch['max_nruns'] = $s['nruns'];
                }

                $s['wt_split'] = $s['wt_split_bkr_split'] - $s['wt_split_bkr_tare'];
                $s['wt_icp'] = $s['wt_split_bkr_icp'] - $s['wt_split_bkr_tare'];
                $s['tot_df'] = safe_divide($s['wt_icp'], $s['wt_split']) * $a['wt_HF_soln'];
            }
            
            if (isset($batch['BeCarrier'])) {
                $a['wt_be'] = $a['wt_be_carrier'] * $batch['BeCarrier']['be_conc'];
            } else {
                $a['wt_be'] = 0.0;
            }

            if (isset($batch['BeCarrier'])) {
                $a['wt_al_fromc'] = $a['wt_al_carrier'] * $batch['AlCarrier']['al_conc'];
            } else {
                $a['wt_al'] = 0.0;
            }

            // Do the usual thing with the Al checks database --
            // Attempt to obtain Al/Fe/Ti concentrations
            $precheck = Doctrine_Query::create()
                ->from('AlcheckAnalysis a')
                ->leftJoin('a.AlcheckBatch b')
                ->select('a.sample_name, a.icp_al, a.icp_fe, a.icp_ti, a.wt_bkr_tare, '
                       . 'a.wt_bkr_sample, a.wt_bkr_soln, b.prep_date')
                ->where('a.sample_name = ?', $a['sample_name'])
                ->andWhere('a.alcheck_batch_id = b.id')
                ->orderBy('b.prep_date DESC')
                ->limit(1)
                ->fetchOne();

            if ($precheck) { // we found it
                $alcheck_df = safe_divide(
                                ($precheck['wt_bkr_soln'] - $precheck['wt_bkr_tare']),
                                ($precheck['wt_bkr_sample'] - $precheck['wt_bkr_tare'])
                              );
                $check_al = $precheck['icp_al'] * $alcheck_df * $a['wt_sample'];
                $a['check_fe'] = $precheck['icp_fe'] * $alcheck_df * $a['wt_sample'];
                $a['check_ti'] = $precheck['icp_ti'] * $alcheck_df * $a['wt_sample'];
                $a['check_tot_al'] = $a['wt_al_carrier'] * $batch['AlCarrier']['al_conc'] + $check_al;
            } elseif ($a['sample_type'] === 'BLANK') { // sample is a blank
                if ($a['wt_al_carrier'] > 0) {
                    $a['check_tot_al'] = $a['wt_al_carrier'] * $batch['AlCarrier']['al_conc'];
                } else {
                    $a['check_tot_al'] = 0;
                }
                $a['check_fe'] = 0;
                $a['check_ti'] = 0;
            } else { // not in the database
                $a['check_tot_al'] = 0;
                $a['check_fe'] = 0;
                $a['check_ti'] = 0;
            }

            if ($stats) {
                $this->calcAnalysisStats($a);
            }

        } // end analysis loop
        unset($a);

        // other calculations
        $batch['wt_be_carrier_diff'] = $batch['wt_be_carrier_init'] - $batch['wt_be_carrier_final'];
        $batch['wt_al_carrier_diff'] = $batch['wt_al_carrier_init'] - $batch['wt_al_carrier_final'];

        return $batch;
    }

    /**
     * @param array $a the analysis we're doing calculations on and writing to
     */
    private function calcAnalysisStats(&$a)
    {
        // Calculate average weight
        $temp_tot_al = 0;
        $temp_tot_be = 0;
        $n_al = 0;
        $n_be = 0;
        foreach ($a['Split'] as &$s) {
            foreach ($s['IcpRun'] as &$r) {
                $r['al_tot'] = $r['al_result'] * $s['tot_df'];
                $r['be_tot'] = $r['be_result'] * $s['tot_df'];

                if ($r['use_al'] == 'y') {
                    $temp_tot_al += $r['al_tot'];
                    ++$n_al;
                }

                if ($r['use_be'] == 'y') {
                    $temp_tot_be += $r['be_tot'];
                    ++$n_be;
                }
            } unset($r);
        } unset($s);
        $a['al_avg'] = safe_divide($temp_tot_al, $n_al);
        $a['be_avg'] = safe_divide($temp_tot_be, $n_be);

        $temp_sd_al = 0;
        $temp_sd_be = 0;
        // Calculate the standard deviation
        foreach ($a['Split'] as $s) {
            foreach ($s['IcpRun'] as $r) {
                if ($r['use_al'] == 'y') {
                    $temp_sd_al += pow(($r['al_tot'] - $a['al_avg']), 2);
                }
                if ($r['use_be'] == 'y') {
                    $temp_sd_be += pow(($r['be_tot'] - $a['be_avg']), 2);
                }
            }
        }

        $a['al_sd'] = sqrt(safe_divide($temp_sd_al, $n_al - 1));
        $a['be_sd'] = sqrt(safe_divide($temp_sd_be, $n_be - 1));

        // Calculate the percentage error
        $a['al_pct_err'] = 100 * safe_divide($a['al_sd'], $a['al_avg']);
        $a['be_pct_err'] = 100 * safe_divide($a['be_sd'], $a['be_avg']);

        // Calculate the percent recovery
        $a['be_recovery'] = 100 * safe_divide($a['be_avg'], $a['wt_be']);
        $a['al_recovery'] = 100 * safe_divide($a['al_avg'], $a['check_tot_al']);
    }

    /**
     * Creates splits and runs for all the analyses in the batch if none exist yet.
     *
     * @return bool true if changes were made to the object
     */
    public function initializeSplitsRuns()
    {
        $changes = false;
        $nanalyses = $this->Analysis->count();
        for ($a = 0; $a < $nanalyses; $a++) {
            $nsplits = $this->Analysis[$a]->Split->count();
            if ($nsplits == 0) {
                // no splits in db, add the splits and their icp runs too
                for ($s = 1; $s <= 2; $s++) {
                     $newsplit = new Split();
                     $newsplit->split_num = $s;
                     for ($r = 1; $r <= 2; $r++) {
                         $newrun = new IcpRun();
                         $newrun->run_num = $r;
                         $newsplit->IcpRun[] = $newrun;
                     } // run loop
                     $this->Analysis[$a]->Split[] = $newsplit;
                } // split loop
                $changes = true;
            }
        } // analysis loop

        return $changes;
    }

    /**
     * Creates text for input boxes on the ICP results page.
     *
     * @return array of generated aluminum text, beryllium text, and the number of rows in the text
     **/
    public function generateIcpResultsText()
    {
        $al_text = '';
        $be_text = '';
        $nrows = 0;
        foreach ($this['Analysis'] as $a) {
            foreach ($a['Split'] as $s) {
                $bkr_text = "\n" . $s['SplitBkr']['bkr_number'];
                $al_text .= $bkr_text;
                $be_text .= $bkr_text;
                foreach ($s['IcpRun'] as $r) {
                    $al_text .= ' ' . $r['al_result'];
                    $be_text .= ' ' . $r['be_result'];
                }
                ++$nrows;
            }
        }

        return array($al_text, $be_text, $nrows);
    }

    /**
     * Sets ICP results supplied as parameters on the Batch object.
     *
     * @param array @al_arr Al ICP results
     * @param array @be_arr Be ICP results
     * @return Batch $this, allows method chaining
     **/
    public function &setIcpResults($al_arr, $be_arr)
    {
        $al_count = count($al_arr);
        $be_count = count($be_arr);

        // change the batch
        foreach ($this->Analysis as &$a) {
            foreach ($a->Split as &$s) {
                $bkr_num = $s->SplitBkr->bkr_number;
                $nRunsDb = $s->IcpRun->count();
                $nRuns = count($al_arr[$bkr_num]);

                // what if a run was removed by the user
                if ($nRunsDb > $nRuns) {
                    $nDeleted = Doctrine_Core::getTable('IcpRun')->removeExcessRuns($s, $nRuns);
                    // update $nRunsDb to new value
                    $nRunsDb = $nRuns;
                    $this->refreshRelated();
                }

                for ($r = 0; $r < $nRuns; $r++) {
                    if ($r >= $nRunsDb) {
                        $newRun = new IcpRun();
                        $newRun->run_num = $r + 1;
                        $s->IcpRun[] = $newRun;
                    }
                    if (isset($al_arr[$bkr_num][$r])) {
                        if ($al_arr[$bkr_num][$r] < 0) {
                            $s->IcpRun[$r]->al_result = 0;
                        } else {
                            $s->IcpRun[$r]->al_result = $al_arr[$bkr_num][$r];
                        }
                        $s->IcpRun[$r]->use_al = 'y';
                    }
                    if (isset($be_arr[$bkr_num][$r])) {
                        if ($be_arr[$bkr_num][$r] < 0) {
                            $s->IcpRun[$r]->be_result = 0;
                        } else {
                            $s->IcpRun[$r]->be_result = $be_arr[$bkr_num][$r];
                        }
                        $s->IcpRun[$r]->use_be = 'y';
                    }
                }
            } unset($s);
        } unset($a);

        return $this;
    }

    /**
     * Sets the use_be and use_al fields, indicating whether or not to use a ICP
     * result in calculations. If an IcpRun id is within the $use_be array then
     * that run's use_be field will be set to 'y', otherwise it is set to 'n'. The
     * same holds for $use_al.
     *
     * @param array $use_be array containing run id values for Be ICP results deemed OK
     * @param array $use_al array containing run id values for Al ICP results deemed OK
     * @throws InvalidArgumentException if either argument is not an array
     * @return Batch reference to this batch object
     */
    public function &setIcpOKs($use_be, $use_al)
    {
        if ( !is_array($use_be) || !is_array($use_al)) {
            throw new InvalidArgumentException('Both arguments must be arrays.');
        }

        foreach ($this->Analysis as &$an) {
            foreach ($an->Split as &$sp) {
                foreach ($sp->IcpRun as &$run) {

                    if (in_array($run->id, $use_be)) {
                        $run->use_be = 'y';
                    } else {
                        $run->use_be = 'n';
                    }

                    if (in_array($run->id, $use_al)) {
                        $run->use_al = 'y';
                    } else {
                        $run->use_al = 'n';
                    }

                }
            } unset($sp);
        } unset($an);

        return $this;
    }
}
