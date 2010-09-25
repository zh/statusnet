<?php

class YammerAuthVerifyForm extends Form
{
    private $verify_url;

    function __construct($out, $auth_url)
    {
        parent::__construct($out);
        $this->verify_url = $auth_url;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'yammer-auth-verify-form';
    }


    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_yammer_auth_verify';
    }


    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('yammeradminpanel');
    }


    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _m('Connect to Yammer'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->input('verify-code', _m('Verification code:'), '', _m("Click through and paste the code it gives you below..."));
        $this->out->submit('submit', _m('Verify code'), 'submit', null, _m('Verification code'));
        $this->element('iframe', array('id' => 'yammer-oauth',
                                       'src' => $this->auth_url));
    }
}
