<?php

/**
 * AlAms
 *
 */
class AlAms extends BaseAlAms
{
    public function getConcAl26()
    {
        $an = $this->Analysis;
        $alc = $an->Batch->BeCarrier;

        // Do calculations on the blank first
        $blank = $an->Batch->getBlank('Al');
        $BlkAms = $blank->AlAms[0];
        $R_26to27_b = $BlkAms->r_to_rstd * $BlkAms->AlAmsStd->r26to27;
        list($M_Al_b, $M_Al_b_err) = $blank->getMassIcp('Al');
        // Note that $M_Al_b_err (error for mass of Al in blank) is a standard deviation.

        // Error propagation for the blank:
        $blank_err = $BlkAms->AlAmsStd->r26to27 * max($BlkAms->exterror, $BlkAms->interror);
        $n26_b_err_terms = array(
            // error from blank AMS measurement
            $blank_err * $M_Al_b * AVOGADRO / MM_AL,
            // error from ICP measurement
            $M_Al_b_err * $R_26to27_b * AVOGADRO / MM_AL,
        );
        $n26_b_err = sqrt(sum_of_squares($n26_b_err_terms));

        // Be26/Be27 ratio of the sample
        $R_26to27 = $this->r_to_rstd * $this->AlAmsStd->r26to27;
        // mass of the quartz in the sample
        $M_qtz = $an->getSampleWt();
        list($M_Al, $M_Al_err) = $an->getMassIcp('Al');
        // Estimate of Be10 concentration in sample
        $al26_conc = ($R_26to27 * $M_Al - $R_26to27_b * $M_Al_b) * AVOGADRO / ($M_qtz * MM_AL);
        // Calculate the error in Al26 concentration:
        // First, define the differentials for each error source. Each is
        // equivalent to del(Number of Be10 atoms)/del(source variable)
        // multiplied by the error in the source variable.
        $err = max($this->exterror, $this->interror);
        $err_terms = array(
            $err * $M_Al * AVOGADRO / ($M_qtz * MM_AL), // from ams error
            -$n26_b_err / $M_qtz, // error from the atoms of Al26 in blank
            $M_Al_err * $R_26to27 * AVOGADRO / ($M_qtz * MM_AL), // from carrier error
        );
        $al26_err = sqrt(sum_of_squares($err_terms));
        return array($al26_conc, $al26_err);
    }
}