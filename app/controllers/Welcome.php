<?php

class Welcome extends MY_Controller {

    function index()
    {
        $data = array(
            'main' => 'welcome',
            'title' => 'CNL Database'       
        );

        $this->load->view('template', $data);
    }

}
