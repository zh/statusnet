<?php

class YammerAuthVerifyForm extends Form
{
    private $runner;

    function __construct($out, YammerRunner $runner)
    {
        parent::__construct($out);
        $this->runner = $runner;
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
        $this->out->elementStart('p');
        $this->out->text(_m('Follow this link to confirm authorization at Yammer; you will be prompted to log in if necessary:'));
        $this->out->elementEnd('p');

        $this->out->elementStart('blockquote');
        $this->out->element('a',
            array('href' => $this->runner->getAuthUrl(),
                  'target' => '_blank'),
            _m('Open Yammer authentication window'));
        $this->out->elementEnd('blockquote');
        
        $this->out->element('p', array(), _m('Copy the verification code you are given into the form below:'));

        $this->out->input('verify_token', _m('Verification code:'));
        
        // iframe would be nice to avoid leaving -- since they don't seem to have callback url O_O
        /*
        $this->out->element('iframe', array('id' => 'yammer-oauth',
                                            'src' => $this->runner->getAuthUrl()));
        */
        // yeah, it ignores the callback_url
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _m('Verify code'), 'submit', null, _m('Verification code'));
    }
}
