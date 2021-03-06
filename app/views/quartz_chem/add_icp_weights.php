<?php echo form_open(site_url('quartz_chem/add_icp_weights'), '',
    array( // hidden POST variables
        'batch_id' => $batch->id,
        'is_refresh' => 'true'
    )); ?>

<table width="800" class="arial10">
    <tr>
        <td>
        <h3>Batch information:</p></h3>
        Batch ID: <?php echo $batch->id; ?><br>
        Batch start date: <?php echo $batch->start_date; ?><br>
        Batch owner: <?php echo $batch->owner; ?><br>
        Batch description: <?php echo $batch->description; ?><p/>
        ICP date: <?php echo form_input('batch[icp_date]', $batch->icp_date); ?> (Format = YYYY-MM-DD)
        </td>
    </tr>
    <tr><td colspan="4">
        Batch notes:<br>
        <center>
        <textarea name="batch[notes]" rows=5 cols=100><?php echo $batch->notes; ?></textarea>
        </center>
    </td></tr>
    <tr><td><hr></td></tr>
</table>

<?php
if ($errors) {
    echo validation_errors() . '<hr>';
}
?>

<table width="800" class="arial8">

    <tr>
        <td colspan="8" class="arial12">Sample information:</p></td>
    </tr>
    <tr>
        <td>Analysis ID</td>
        <td>Sample name</td>
        <td>Dissolution bottle ID</td>
        <td>Split number</td>
        <td>Split beaker ID</td>
        <td>Beaker tare wt.</td>
        <td>Beaker + ICP solution wt.</td>
        <td>ICP solution wt.</td>
    </tr>

<?php for ($i = 0; $i < $numsamples; $i++): ?>

    <tr><td colspan="8"><hr></td></tr>
    <?php for ($s = 0, $nsplits = $batch->Analysis[$i]->Split->count(); $s < $nsplits; $s++): ?>
        <tr>
            <?php if ($s == 0): ?>
                <td><?php echo $batch->Analysis[$i]->id; ?></td>
                <td>
                <?php
                if (isset($batch->Analysis[$i]->Sample->name)) {
                    echo $batch->Analysis[$i]->Sample->name;
                } else {
                    echo $batch->Analysis[$i]->sample_name;
                }
                ?>
                </td>
                <td><?php echo $batch->Analysis[$i]->DissBottle->bottle_number; ?></td>
            <?php else: ?>
                <td colspan="3"></td>
            <?php endif; ?>

            <td>Split <?php echo $s+1; ?>:</td>
            <td><?php echo $batch->Analysis[$i]->Split[$s]->SplitBkr->bkr_number; ?></td>
            <td><?php echo (float)$batch->Analysis[$i]->Split[$s]->wt_split_bkr_tare; ?></td>
            <td><?php echo form_input('tot_wts[]', (float)$batch->Analysis[$i]->Split[$s]->wt_split_bkr_icp); ?></td>
            <td>
                <?php
                $icp_wt = $batch->Analysis[$i]->Split[$s]->wt_split_bkr_icp
                    - $batch->Analysis[$i]->Split[$s]->wt_split_bkr_tare;
                printf('%.4f', $icp_wt);
                ?>
            </td>
        </tr>
    <?php endfor; ?>

<?php endfor; ?>

    <tr><td colspan="8"><hr></td></tr>
</table>

<table width="800">
    <tr>
    <td align="center">
    <input type=submit value="Save and refresh">
    </td>
    </tr>
    <tr><td><hr></td></tr>
    <?php echo form_close(); ?>
</table>

<?php $this->load->view('quartz_chem/bottom_links'); ?>
