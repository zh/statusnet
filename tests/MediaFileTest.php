<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';

class MediaFileTest extends PHPUnit_Framework_TestCase
{

    public function setup()
    {
        $this->old_attachments_supported = common_config('attachments', 'supported');
        $GLOBALS['config']['attachments']['supported'] = true;
    }

    public function tearDown()
    {
        $GLOBALS['config']['attachments']['supported'] = $this->old_attachments_supported;
    }

    /**
     * @dataProvider fileTypeCases
     *
     */
    public function testFileType($filename, $expectedType)
    {
        if (!file_exists($filename)) {
            throw new Exception("WTF? $filename test file missing");
        }
        $this->assertEquals($expectedType, MediaFile::getUploadedFileType($filename));
    }

    static public function fileTypeCases()
    {
        $base = dirname(__FILE__);
        $dir = "$base/sample-uploads";
        return array(
            array("$dir/office.pdf", "application/pdf"),
            
            array("$dir/wordproc.odt", "application/vnd.oasis.opendocument.text"),
            array("$dir/wordproc.ott", "application/vnd.oasis.opendocument.text-template"),
            array("$dir/wordproc.doc", "application/msword"),
            array("$dir/wordproc.docx",
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document"),
            array("$dir/wordproc.rtf", "text/rtf"),
            
            array("$dir/spreadsheet.ods",
                "application/vnd.oasis.opendocument.spreadsheet"),
            array("$dir/spreadsheet.ots",
                "application/vnd.oasis.opendocument.spreadsheet-template"),
            array("$dir/spreadsheet.xls", "application/vnd.ms-excel"),
            array("$dir/spreadsheet.xlt", "application/vnd.ms-excel"),
            array("$dir/spreadsheet.xlsx",
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"),
            
            array("$dir/presentation.odp",
                "application/vnd.oasis-opendocument.presentation"),
            array("$dir/presentation.otp",
                "application/vnd.oasis-opendocument.presentation-template"),
            array("$dir/presentation.ppt",
                "application/vnd.ms-powerpoint"),
            array("$dir/presentation.pot",
                "application/vnd.ms-powerpoint"),
            array("$dir/presentation.pptx",
                "application/vnd.openxmlformats-officedocument.presentationml.presentation"),
        );
    }

}

