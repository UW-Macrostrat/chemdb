 <?php
/**
 * Business logic for the quartz chemistry pages.
 *
 * Methods appear in the same order as they are listed in the front page for
 * the Quartz Chemistry section.
 */

class Quartz_chem extends MY_Controller
{

    const MSG_QUERY_FAILED = 'Batch query failed.';

    /**
     * Supplies data for the Al/Be Chemistry index page. Populates select boxes
     * with batches that the user can select to modify on the other pages.
     * @param void
     * @return void
     */
    public function index()
    {
        $batch_id = (int)$this->input->post('batch_id');
        if ($this->input->post('is_lock') === "true") {
            Doctrine_Core::getTable('Batch')->lock($batch_id);
        }

        $batches = Doctrine_Core::getTable('Batch')->findAllBatches();
        $data  = new stdClass();
        $data->open_batches = '';
        $data->all_batches = '';

        // build option tags for the select boxes
        foreach ($batches as $b) {
            $opt = "<option value=$b->id>$b->id $b->owner $b->start_date "
                . substr($b->description, 0, 65);
            $data->all_batches .= $opt;
            if ($b->completed == 'n') {
                $data->open_batches .= $opt;
            }
        }

        $data->title = 'Quartz Al-Be chemistry';
        $data->subtitle = 'Al-Be extraction from quartz:';
        $data->main = 'quartz_chem/index';
        $this->load->view('template', $data);
    }

    /**
     * Form for adding a batch or for editing the batch information after it
     * has been added.
     * @param void
     * @return void
     */
    public function new_batch()
    {
        $id = $this->input->post('batch_id');
        $is_edit = (bool)$id;
        $data = new stdClass();
        $data->allow_num_edit = !$is_edit;

        if ($is_edit) {
            // it's an existing batch, get it
            $batch = Doctrine_Core::getTable('Batch')->find($id);
            $this->_dieIfQueryFailed($batch);

            $data->numsamples = $batch->Analysis->count();
        } else {
            $batch = new Batch();
            $data->numsamples = null;
            $batch->start_date = date('Y-m-d');
        }

        if ($this->input->post('is_refresh')) {
            $is_valid = $this->form_validation->run('batches');
            $batch->id = $this->input->post('batch_id');
            $batch->owner = $this->input->post('owner');
            $batch->description = $this->input->post('description');
            $data->numsamples = $this->input->post('numsamples');

            if ($is_edit) {
                $batch->start_date = $this->input->post('start_date');
            }

            if ($is_valid) {
                $new_batch = (!(bool)$batch->id);
                if ($new_batch) {
                    // new batch: create the analyses linked to this batch
                    for ($i = 1; $i <= $data->numsamples; $i++) {
                        $analysis = new Analysis();
                        $analysis->number_within_batch = $i;
                        $batch->Analysis[] = $analysis;
                    }
                }
                $batch->save();
            }
        }

        // set the rest of the view data
        $data->title = 'Add a batch';
        $data->main = 'quartz_chem/new_batch';
        $data->batch = $batch;
        $this->load->view('template', $data);
    }

    /**
     * Shows the sample loading page.
     * @param void
     * @return void
     **/
    public function load_samples()
    {
        $refresh = (bool)$this->input->post('is_refresh');
        $batch_id = (int)$this->input->post('batch_id');

        // grab the batch with carrier data
        $batch = Doctrine_Core::getTable('Batch')->findWithCarriers($batch_id);
        $this->_dieIfQueryFailed($batch);

        $num_analyses = $batch->Analysis->count();
        $errors = false;

        // if this is a refresh we need to validate the data
        if ($refresh) {
            $is_valid = $this->form_validation->run('load_samples');

            // grab the submitted batch data
            $batch->notes = $this->input->post('notes');
            $batch->be_carrier_id = $this->input->post('be_carrier_id');
            $batch->al_carrier_id = $this->input->post('al_carrier_id');
            $batch->wt_be_carrier_init = $this->input->post('wt_be_carrier_init');
            $batch->wt_al_carrier_init = $this->input->post('wt_al_carrier_init');
            $batch->wt_be_carrier_final = $this->input->post('wt_be_carrier_final');
            $batch->wt_al_carrier_final = $this->input->post('wt_al_carrier_final');
            // and array fields for each analysis
            $sample_name = $this->input->post('sample_name');
            $sample_type = $this->input->post('sample_type');
            $diss_bottle_id = $this->input->post('diss_bottle_id');
            $wt_diss_bottle_tare = $this->input->post('wt_diss_bottle_tare');
            $wt_diss_bottle_sample = $this->input->post('wt_diss_bottle_sample');
            $wt_be_carrier = $this->input->post('wt_be_carrier');
            $wt_al_carrier = $this->input->post('wt_al_carrier');

            for ($a = 0; $a < $num_analyses; $a++) { // analysis loop
                $analysis = &$batch->Analysis[$a];
                $analysis->sample_name = $sample_name[$a];
                $analysis->sample_type = $sample_type[$a];
                $analysis->diss_bottle_id = $diss_bottle_id[$a];
                $analysis->wt_diss_bottle_tare = $wt_diss_bottle_tare[$a];
                $analysis->wt_diss_bottle_sample = $wt_diss_bottle_sample[$a];
                $analysis->wt_be_carrier = $wt_be_carrier[$a];
                $analysis->wt_al_carrier = $wt_al_carrier[$a];
            } unset($analysis);

            if ($is_valid) {
                // data is valid
                // link each analysis to a sample first if it can be found
                foreach ($batch->Analysis as &$a) {
                    $sample = Doctrine_Query::create()
                        ->from('Sample s')
                        ->select('s.id')
                        ->where('s.name = ?', $a->sample_name)
                        ->andWhere("s.name != ''")
                        ->fetchOne();

                    if ($sample) {
                        $a->Sample = $sample;
                    } else {
                        $a->sample_id = null;
                    }
                } unset($a);

                $batch->save();
                $batch = Doctrine_Core::getTable('Batch')->findWithCarriers($batch_id);
            } else {
                // validation failed
                $errors = true;
            }
        }

        // initialize carrier weights and Alcheck data arrays
        $be_tot_wt = 0;
        $al_tot_wt = 0;
        $diss_bottle_options = array();
        $prechecks = array();
        // get all the dissolution bottle numbers
        $diss_bottles = Doctrine_Core::getTable('DissBottle')->findAll();

        for ($a = 0; $a < $num_analyses; $a++) {
            $an = $batch->Analysis[$a];
            // create diss bottle dropdown options for each sample
            $html = '';
            foreach($diss_bottles as $bottle) {
                $html .= '<option value='.$bottle->id;
                if ($bottle->id == $an->diss_bottle_id) {
                    $html .= ' selected';
                }
                $html .= "> $bottle->bottle_number";
            }
            $diss_bottle_options[$a] = $html;

            $temp_sample_wt = $an->getSampleWt();

            // get cations while we're at it
            if (isset($an->Sample)) {
                $sample_name = $an->Sample->name;
            } else {
                $sample_name = $an->sample_name;
            }

            $precheck = Doctrine_Query::create()
                ->from('AlcheckAnalysis a, a.AlcheckBatch b')
                ->select('a.icp_al, a.icp_fe, a.icp_ti, a.wt_bkr_tare, a.wt_bkr_sample, '
                    . 'a.wt_bkr_soln, b.prep_date')
                ->where('a.sample_name = ?', $sample_name)
                ->andWhere("a.sample_name != ''")
                ->orderBy('b.prep_date DESC')
                ->limit(1)
                ->fetchOne();

            if ($precheck AND $batch->AlCarrier) {
                // there's data, calculate the concentrations
                $prechecks[$a]['show'] = true;
                $temp_df = safe_divide(
                    ($precheck['wt_bkr_soln'] - $precheck['wt_bkr_tare']),
                    ($precheck['wt_bkr_sample'] - $precheck['wt_bkr_tare']));

                $temp_al = $precheck['icp_al'] * $temp_df * $temp_sample_wt / 1000;
                $temp_fe = $precheck['icp_fe'] * $temp_df * $temp_sample_wt / 1000;
                $temp_ti = $precheck['icp_ti'] * $temp_df * $temp_sample_wt / 1000;
                $temp_tot_al = $temp_al +
                    ($an->wt_al_carrier * $batch->AlCarrier->al_conc) / 1000;

                $prechecks[$a]['conc_al'] = sprintf('%.1f', $precheck['icp_al'] * $temp_df);
                $prechecks[$a]['conc_fe'] = sprintf('%.1f', $precheck['icp_fe'] * $temp_df);
                $prechecks[$a]['conc_ti'] = sprintf('%.1f', $precheck['icp_ti'] * $temp_df);
            } else {
                $prechecks[$a]['show'] = false;
                $temp_fe = '--';
                $temp_ti = '--';
                $temp_tot_al = '--';

                if ($batch->AlCarrier AND $an->wt_al_carrier > 0) {
                    $temp_tot_al = $an->wt_al_carrier * $batch->AlCarrier->al_conc / 1000;
                }
            }

            $prechecks[$a]['m_al'] = $temp_tot_al;
            $prechecks[$a]['m_fe'] = $temp_fe;
            $prechecks[$a]['m_ti'] = $temp_ti;

            $be_tot_wt += $an->wt_be_carrier;
            $al_tot_wt += $an->wt_al_carrier;
        }

        $data = new stdClass();
        // get previous carrier weights
        $data->be_prev = Doctrine_Core::getTable('Batch')->findPrevBeCarrierWt(
                                    $batch->be_carrier_id, $batch->start_date);
        $data->al_prev = Doctrine_Core::getTable('Batch')->findPrevAlCarrierWt(
                                    $batch->al_carrier_id, $batch->start_date);

        // create the lists of carrier options
        $data->be_carrier_options = Doctrine_Core::getTable('BeCarrier')
                                    ->getSelectOptions($batch->be_carrier_id);
        $data->al_carrier_options = Doctrine_Core::getTable('AlCarrier')
                                    ->getSelectOptions($batch->al_carrier_id);

        // set display variables
        $data->batch = $batch;
        $data->num_analyses = $num_analyses;
        $data->prechecks = $prechecks;
        $data->diss_bottle_options = $diss_bottle_options;
        $data->errors = $errors;

        // and calculated metadata for display
        $data->be_diff_wt = $batch->wt_be_carrier_init - $batch->wt_be_carrier_final;
        $data->al_diff_wt = $batch->wt_al_carrier_init - $batch->wt_al_carrier_final;
        $data->be_diff = $data->be_diff_wt - $be_tot_wt;
        $data->al_diff = $data->al_diff_wt - $al_tot_wt;
        $data->be_tot_wt = $be_tot_wt;
        $data->al_tot_wt = $al_tot_wt;

        // Set up our javascript
        $btnId = $this->input->post('hash');
        $data->extraHeadContent = '
            <script type="text/javascript" src="js/sample_search.js"></script>
            <script type="text/javascript">

            $(document).ready(function() {
                // When one of the submit buttons is clicked, add a hidden hash field
                // that can be used to redirect the user back to this button.
                $(".ancBtn").click(function() {
                    var id = $(this).attr("id");
                    $(this).after("<input type=hidden name=hash value="+id+">");
                });

                // If any sample type is set to "BLANK" we make its sample_wt
                // to its tare wt and disable the sample weight input box.
                // Likewise, if it is set to "SAMPLE" then the sample weight
                // input box is made editable again.
                $(".type").change(function() {
                    // get the sample index
                    var i = $(".type").index(this);
                    var sampleWt = $(".sampleWt").eq(i);
                    if ($(this).val() == "BLANK") {
                        weight = $(".tareWt").eq(i).val();
                        sampleWt.val(weight);
                        sampleWt.attr("disabled", true);
                        sampleWt.after("<input type=hidden value=" + weight + " name=wt_diss_bottle_sample[] id=hidWt" + i + ">");
                    } else {
                        sampleWt.attr("disabled", false);
                        $("#hidWt" + i).remove();
                    }
                }).change();

                // Whenever the a blank tare weight is updated
                // update the corresponding sample weight to be the same.
                $(".tareWt").bind("change keyup", function() {
                    var i = $(".tareWt").index(this);
                    var sampleWt = $(".sampleWt").eq(i);
                    if (sampleWt.attr("disabled")) {
                        sampleWt.val($(this).val());
                        $("#hidWt" + i).val($(this).val());
                    }
                }).change();

            });

            // Scroll the window down to the last-pressed button.
            $(window).load(function() {
                var id = "'.$btnId.'";
                if (id != "") {
                    // Get the offset down the page of the pressed button.
                    var btnOffset = $("\#" + id).offset().top;
                    // Scroll the window so that the previously pressed button is
                    // near the bottom of the screen.
                    window.scrollTo(0, btnOffset - window.innerHeight * 4 / 5);
                }
            });
            </script>
            ';

        $data->title = 'Sample weighing and carrier addition';
        $data->main = 'quartz_chem/load_samples';
        $this->load->view('template', $data);
    }

    /**
     * Produces a sheet to track the progress of samples through the rest of the process.
     * @param void
     * @return void
     */
    public function print_tracking_sheet()
    {
        $batch_id = $this->input->post('batch_id');
        $batch = Doctrine_Query::create()
            ->from('Batch b')
            ->leftJoin('b.Analysis a')
            ->leftJoin('b.AlCarrier ac')
            ->leftJoin('b.BeCarrier bc')
            ->leftJoin('a.DissBottle db')
            ->where('b.id = ?', $batch_id)
            ->orderBy('a.id ASC')
            ->limit(1)
            ->fetchOne();
        $this->_dieIfQueryFailed($batch);

        $numsamples = $batch->Analysis->count();
        $HF_additions = array();
        for ($a = 0; $a < $numsamples; $a++) {

            $pquery = Doctrine_Query::create()
                ->from('AlcheckAnalysis a')
                ->leftJoin('a.AlcheckBatch b')
                ->select('a.sample_name, a.icp_al, a.icp_fe, a.icp_ti, a.wt_bkr_tare, '
                    . 'a.wt_bkr_sample, a.wt_bkr_soln, b.prep_date')
                ->where('a.alcheck_batch_id = b.id')
                ->orderBy('b.prep_date DESC')
                ->limit(1);
            if (isset($batch->Analysis[$a]->Sample)) {
                $pquery->where('a.sampled_id = ?', $batch->Analysis[$a]->Sample->id);
            } else {
                $pquery->where('a.sample_name = ?', $batch->Analysis[$a]->sample_name);
            }
            $precheck = $pquery->fetchOne();

            $tmpa[$a]['tmpSampleWt'] = $batch->Analysis[$a]->getSampleWt();

            if (strtoupper($batch->Analysis[$a]->sample_type) == 'SAMPLE') {
                $HF_additions[] = $tmpa[$a]['mlHf'] = round($tmpa[$a]['tmpSampleWt']) * 5 + 5;
            } else {
                $tmpa[$a]['mlHf'] = 'BLANK';
            }

            $tmpa[$a]['inAlDb'] = true;
            if ($precheck) {
                $sampleWt = ($precheck['wt_bkr_sample'] - $precheck['wt_bkr_tare']);
                if ($sampleWt != 0) {
                    $temp_df = ($precheck['wt_bkr_soln'] - $precheck['wt_bkr_tare']) / $sampleWt;
                } else {
                    $temp_df = 0;
                }

                $temp_al = $precheck['icp_al'] * $temp_df * $tmpa[$a]['tmpSampleWt'] / 1000;
                $tmpa[$a]['tot_fe'] = sprintf('%.2f',
                    $precheck['icp_fe'] * $temp_df * $tmpa[$a]['tmpSampleWt'] / 1000);
                $tmpa[$a]['tot_ti'] = sprintf('%.2f',
                    $precheck['icp_ti'] * $temp_df * $tmpa[$a]['tmpSampleWt'] / 1000);
                $tmpa[$a]['tot_al'] = sprintf('%.2f', $temp_al
                    + ($batch->Analysis[$a]->wt_al_carrier * $batch->AlCarrier->al_conc) / 1000);
            } elseif ($batch->Analysis[$a]->sample_type === 'BLANK') {
                if ($batch->Analysis[$a]->wt_al_carrier > 0) {
                    $tmpa[$a]['tot_al'] = sprintf('%.2f',
                        ($batch->Analysis[$a]->wt_al_carrier * $batch->AlCarrier->al_conc) / 1000);
                } else {
                    $tmpa[$a]['tot_al'] = ' -- ';
                }
                $tmpa[$a]['tot_ti'] = ' -- ';
                $tmpa[$a]['tot_fe'] = ' -- ';
            } else {
                // sample isn't in the Alchecks database
                $tmpa[$a]['inAlDb'] = false;
            }
        }

        foreach ($tmpa as &$an_data) {
            if ($an_data['mlHf'] == 'BLANK') {
                if (!isset($blankHFVolume)) {
                    if (count($HF_additions) == 0) {
                        $blankHFVolume = 5; // mL
                    } else {
                        $blankHFVolume = roundDownToNearest(mean($HF_additions), 5);
                    }
                }
                $an_data['mlHf'] = $blankHFVolume;
            }
        } unset($an_data);

        $data = new stdClass();
        $data->batch = $batch;
        $data->tmpa = $tmpa;
        $data->user = $this->get_remote_user_html();
        $this->load->view('quartz_chem/print_tracking_sheet', $data);
    }

    private function get_remote_user_html()
    {
        if (isset($_SERVER['REMOTE_USER'])) {
            $user = htmlentities($_SERVER['REMOTE_USER']);
        } else {
            $user = '(None)';
        }
        return $user;
    }

    /**
     * Form to submit solution weights.
     */
    public function add_solution_weights()
    {
        $batch_id = (int)$this->input->post('batch_id');
        $refresh = (bool)$this->input->post('is_refresh');

        $batch = Doctrine_Query::create()
            ->from('Batch b')
            ->leftJoin('b.Analysis a')
            ->leftJoin('a.DissBottle db')
            ->where('b.id = ?', $batch_id)
            ->orderBy('a.id ASC')
            ->limit(1)
            ->fetchOne();
        $this->_dieIfQueryFailed($batch);

        $errors = false;
        if ($refresh) {
            $is_valid = $this->form_validation->run('add_solution_weights');

            $batch->merge($this->input->post('batch'));
            $weights = $this->input->post('wt_diss_bottle_total');
            $count = count($weights);
            for ($a = 0; $a < $count; $a++) {
                $batch->Analysis[$a]->wt_diss_bottle_total = $weights[$a];
            }

            if ($is_valid) {
                $batch->save();
            } else {
                $errors = true;
            }
        }

        $data = new stdClass();
        $data->errors = $errors;
        $data->numsamples = $batch->Analysis->count();
        $data->title = 'Add total solution weights';
        $data->main = 'quartz_chem/add_solution_weights';
        $data->batch = $batch;
        $this->load->view('template', $data);
    }

    public function add_split_weights()
    {
        $batch_id = (int)$this->input->post('batch_id');
        $refresh = (bool)$this->input->post('is_refresh');

        $query = Doctrine_Query::create()
            ->from('Batch b')
            ->leftJoin('b.Analysis a')
            ->leftJoin('a.DissBottle db')
            ->leftJoin('a.Split s')
            ->leftJoin('s.SplitBkr sb')
            ->where('b.id = ?', $batch_id)
            ->orderBy('a.id ASC')
            ->addOrderBy('s.split_num ASC')
            ->limit(1);
        $batch = $query->fetchOne();
        $this->_dieIfQueryFailed($batch);

        // insert splits and runs if they don't yet exist in the database
        if ($batch->initializeSplitsRuns()) {
            // there were changes, save it
            $batch->save();
        }

        $numsamples = $batch->Analysis->count();
        $errors = false;
        if ($refresh) {
            $is_valid = $this->form_validation->run('add_split_weights');

            $batch->notes = $this->input->post('notes');
            $bkr_ids = $this->input->post('split_bkr');
            $tares_wts = $this->input->post('bkr_tare');
            $split_wts = $this->input->post('bkr_split');

            $ind = 0; // index for post variable arrays
            for ($a = 0; $a < $numsamples; $a++) { // analysis loop
                $nsplits = $batch->Analysis[$a]->Split->count();
                for ($s = 0; $s < $nsplits; $s++, $ind++) { // split loop
                    $batch->Analysis[$a]->Split[$s]->split_bkr_id = $bkr_ids[$ind];
                    $batch->Analysis[$a]->Split[$s]->wt_split_bkr_tare = $tares_wts[$ind];
                    $batch->Analysis[$a]->Split[$s]->wt_split_bkr_split = $split_wts[$ind];
                }
            }

            if ($is_valid) {
                $batch->save();
            } else {
                $errors = true;
            }

            // add a new split if requested
            for ($i = 0; $i < $numsamples; $i++) {
                if ($this->input->post('a'.$i)) {
                    $tmp = new Split();
                    $tmp->split_num = $batch->Analysis[$i]->Split->count() + 1;
                    $batch->Analysis[$i]->Split[] = $tmp;
                    $batch->save();
                    break;
                }
            }

            $batch = $query->fetchOne();
        }

        $data = new stdClass;
        $data->errors = $errors;
        $data->numsamples = $numsamples;
        $data->batch = $batch;
        $data->bkr_list = Doctrine_Core::getTable('SplitBkr')->getList();

        $data->extraHeadContent = '<script type="text/javascript" src="js/setBeakerSeq.js" async></script>';
        $data->title = 'Add split weights';
        $data->main = 'quartz_chem/add_split_weights';
        $this->load->view('template', $data);
    }

    /**
     * Form for submitting ICP weighings.
     * @return void
     */
    public function add_icp_weights()
    {
        $batch_id = (int)$this->input->post('batch_id');
        $refresh = (bool)$this->input->post('is_refresh');

        $batch = Doctrine_Query::create()
            ->from('Batch b')
            ->leftJoin('b.Analysis a')
            ->leftJoin('a.DissBottle db')
            ->leftJoin('a.Sample sa')
            ->leftJoin('a.Split sp')
            ->leftJoin('sp.SplitBkr sb')
            ->where('b.id = ?', $batch_id)
            ->orderBy('a.id ASC')
            ->addOrderBy('sp.split_num')
            ->limit(1)
            ->fetchOne();
        $this->_dieIfQueryFailed($batch);

        if (isset($batch->Analysis)) {
            $numsamples = $batch->Analysis->count();
        } else {
            $numsamples = 0;
        }

        // For the case when it's a new icp run, change from the default to today's date.
        // It will be saved when valid data is submitted on the form.
        if ($batch->icp_date === '0000-00-00')  {
            $batch->icp_date = date('Y-m-d');
        }

        $errors = false;
        if ($refresh) {
            $is_valid = $this->form_validation->run('add_icp_weights');

            // change the object data
            $batch->merge($this->input->post('batch'));
            $tot_wts = $this->input->post('tot_wts');
            $ind = 0; // index for post variable arrays
            for ($a = 0; $a < $numsamples; $a++) { // analysis loop
                $nsplits = $batch->Analysis[$a]->Split->count();
                for ($s = 0; $s < $nsplits; $s++, $ind++) { // split loop
                    $batch->Analysis[$a]->Split[$s]->wt_split_bkr_icp = $tot_wts[$ind];
                }
            }

            // validate the form
            if ($is_valid) {
                $batch->save();
                $errors = false;
            } else {
                $errors = true;
            }
        }

        $data = new stdClass();
        $data->errors = $errors;
        $data->numsamples = $numsamples;
        $data->batch = $batch;
        $data->title = 'Add ICP solution weights';
        $data->main = 'quartz_chem/add_icp_weights';
        $this->load->view('template', $data);
    }

   /**
    * Form to input ICP results.
    *
    * @return void
    **/
    public function add_icp_results()
    {
        $submit = (bool)$this->input->post('submit');
        $batch_id = (int)$this->input->post('batch_id');

        $query = Doctrine_Query::create()
            ->from('Batch b')
            ->where('b.id = ?', $batch_id)
            ->leftJoin('b.Analysis a')
            ->leftJoin('a.Split s')
            ->leftJoin('s.SplitBkr sb')
            ->leftJoin('s.IcpRun r')
            ->orderBy('a.id ASC')
            ->addOrderBy('s.split_num')
            ->addOrderBy('r.run_num');

        $batch = $query->fetchOne();
        $this->_dieIfQueryFailed($batch);

        if ($submit == true) {
            $valid = $this->form_validation->run('add_icp_results');
            $batch->notes = $this->input->post('notes');
            $raw_al = $this->input->post('al_text');
            $raw_be = $this->input->post('be_text');
            $al_lines = split("[\n]", $raw_al);
            $be_lines = split("[\n]", $raw_be);

            // now validate our entries
            // this regexp to match a word followed by floating point numbers, just what we want
            $valid_regexp = '/[\w-]+\s+([-+]?[0-9]*\.?[0-9]+)+/i';
            $split_regexp = '/\s+/'; // we'll split on whitespace if it is valid
            $is_al = true;
            foreach (array($al_lines, $be_lines) as $lines) {
                if ($is_al) {
                    $element = 'aluminum';
                    $arr = 'al_arr';
                } else {
                    $element = 'beryllium';
                    $arr = 'be_arr';
                }
                foreach ($lines as $ln) {
                    if (preg_match($valid_regexp, $ln)) {
                        $tmp = preg_split($split_regexp, trim($ln));
                        $key = strval(array_shift($tmp));
                        // make the key of the final array the beaker number, one for be and one for al
                        ${$arr}[$key] = $tmp;
                    } elseif ($ln !== "") {
                        die("Error in $element input on line: $ln");
                    }
                }
                $is_al = false;
            }

            // check that number of splits is equal
            $len_be = count($be_arr);
            $len_al = count($al_arr);
            if ($len_be != $len_al) {
                die('There must be the same number of splits for aluminum and beryllium.');
            }

            // the number of runs submitted must be the same
            $nRunsAl = array();
            $nRunsBe = array();
            foreach ($be_arr as $split) {
                $nRunsBe[] = count($split);
            }
            foreach ($al_arr as $split) {
                $nRunsAl[] = count($split);
            }
            if ($nRunsAl !== $nRunsBe) {
                die('There must be an equal number of ICP runs between Aluminum and Beryllium.');
            }

            // make sure our number of splits matches the number created in last page
            $nSplits = 0;
            $nAnalyses = $batch->Analysis->count();
            for ($a = 0; $a < $nAnalyses; $a++) {
                $nSplits += $batch->Analysis[$a]->Split->count();
            }
            if ($len_be != $nSplits) {
                die("The number of splits in the database ($nSplits) does not "
                  . "match the number submitted ($len_be)");
            }

            // test that all submitted split beakers exist
            $bkrs = array_unique(array_keys(array_push($al_arr, $be_arr)));
            $missing = Doctrine_Core::getTable('SplitBkr')->findMissingBkrs($bkrs);
            if (count($missing) != 0) {
                echo "The beakers ";
                // comma separated list, true indicates we want an 'and' before last element
                echo comma_str($missing, true);
                die(' were not found in the database.<br>'
                  . 'Please ensure that you have spelled all '
                  . 'their names correctly or add them to the database.');
            }

            // data looks good, insert it into the database
            $batch->setIcpResults($al_arr, $be_arr)->save();
            $batch = $query->fetchOne();
        }

        // recreate input boxes from database
        $data = new stdClass();
        list($data->al_text, $data->be_text, $data->nrows) = $batch->generateIcpResultsText();

        // in case there were no splits or runs, give it a default number of rows
        if ($data->nrows == 0) {
            $data->nrows = 10;
        }

        $data->batch = $batch;
        $data->title = 'ICP Results Uploading';
        $data->main = 'quartz_chem/add_icp_results';
        $this->load->view('template', $data);
    }

   /**
    * Page to select whether to use an ICP result in calculations or not.
    * @return void
    */
    public function icp_quality_control()
    {
        // Retrieve intial postdata
        $batch_id = (int)$this->input->post('batch_id');
        $refresh = (bool)$this->input->post('refresh');
        $batch = Doctrine_Core::getTable('Batch')->findCompleteById($batch_id);
        $this->_dieIfQueryFailed($batch);

        // Do a database update if we validate properly
        $errors = false;
        if ($refresh) {
            $valid = $this->form_validation->run('icp_quality_control');
            $batch->notes = $this->input->post('notes');
            $use_be = $this->input->post('use_be');
            $use_al = $this->input->post('use_al');
            $batch->setIcpOKs($use_be, $use_al);

            if ($valid) {
                $batch->save();
            } else {
                $errors = true;
            }
        }

        $data = new stdClass();
        $data->batch = $batch->getReportArray(true);
        $data->errors = $errors;
        $data->title = 'ICP Quality Control';
        $data->main = 'quartz_chem/icp_quality_control';
        $this->load->view('template', $data);
    }

    // --------
    // REPORTS:
    // --------

    /**
     * Does calculations and prints an intermediate report on the samples.
     * @return void
     */
    public function intermediate_report()
    {
        $batch_id = (int)$this->uri->segment(3, $this->input->post('batch_id'));
        $data->batch = Doctrine_Core::getTable('Batch')
            ->getReportArray($batch_id);
        $this->_dieIfQueryFailed($data->batch);
        $data->title = 'Intermediate hard copy of weighings -- '
                     . 'Al - Be extraction from quartz';
        $data->todays_date = date('Y-m-d');
        $data->main = 'quartz_chem/intermediate_report';
        $this->load->view('quartz_chem/report_template', $data);
    }

    /**
     * Generates the final report for the batch.
     * @return void
     */
    public function final_report()
    {
        $batch_id = (int)$this->uri->segment(3, $this->input->post('batch_id'));

        // do all our calculations, pass true to do a complete report
        $data = new stdClass();
        $data->batch = Doctrine_Core::getTable('Batch')
            ->getReportArray($batch_id, true);
        $this->_dieIfQueryFailed($data->batch);

        $data->title = 'Final report -- Al - Be extraction from quartz';
        $data->todays_date = date('Y-m-d');
        $data->main = 'quartz_chem/final_report';
        $this->load->view('quartz_chem/report_template', $data);
    }

    private function _dieIfQueryFailed($obj, $msg=self::MSG_QUERY_FAILED)
    {
        if (!$obj) {
            die($msg);
        }
        return;
    }

    // ----------
    // CALLBACKS:
    // ----------

    /**
     * Returns true if $data is a valid date in YYYY-MM-DD format. Otherwise an
     * error message is set, and the function returns false.
     * @param string $date date in YYYY-MM-DD format
     * @access private
     */
    function _valid_date($date)
    {
        if ($this->form_validation->valid_date($date)) {
            return true;
        }

        $this->form_validation->set_message('_valid_date',
            'The %s field must be a valid date in the format YYYY-MM-DD.');
        return false;
    }

}
