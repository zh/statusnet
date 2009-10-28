<?php
/**
 * StatusNet, the distributed open-source mMediaFileicroblogging tool
 *
 * Abstraction for a media files in general
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Media
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

class MediaFile
{

    var $filename      = null;
    var $fileRecord    = null;
    var $user          = null;
    var $fileurl       = null;
    var $short_fileurl = null;
    var $mimetype      = null;

    function __construct($user = null, $filename = null, $mimetype = null)
    {
        if ($user == null) {
            $this->user = common_current_user();
        }

        common_debug('in MediaFile constructor');

        $this->filename = $filename;
        $this->mimetype = $mimetype;

        common_debug('storing file');
        $this->fileRecord = $this->storeFile();
        common_debug('finished storing file');

        $this->fileurl = common_local_url('attachment',
                                    array('attachment' => $this->fileRecord->id));

        common_debug('$this->fileurl() = ' . $this->fileurl);

        // not sure this is necessary -- Zach
        $this->maybeAddRedir($this->fileRecord->id, $this->fileurl);

        common_debug('shortening file url');
        $this->short_fileurl = common_shorten_url($this->fileurl);
        common_debug('shortened file url =  ' . $short_fileurl);

        // Also, not sure this is necessary -- Zach
        $this->maybeAddRedir($this->fileRecord->id, $this->short_fileurl);

        common_debug("MediaFile: end of constructor");
    }

    function attachToNotice($notice)
    {
        common_debug('MediaFile::attachToNotice() -- doing File_to_post');
        File_to_post::processNew($this->fileRecord->id, $notice->id);
        common_debug('MediaFile done doing File_to_post');

        $this->maybeAddRedir($this->fileRecord->id,
                             common_local_url('file', array('notice' => $notice->id)));
    }

    function shortUrl()
    {
        return $this->short_fileurl;
    }

    function delete()
    {
        $filepath = File::path($this->filename);
        @unlink($filepath);
    }

    function storeFile() {

        $file = new File;
        $file->filename = $this->filename;

        common_debug('storing ' . $this->filename);

        $file->url = File::url($this->filename);
        common_debug('file->url = ' . $file->url);

        $filepath = File::path($this->filename);
        common_debug('filepath = ' . $filepath);

        $file->size = filesize($filepath);
        $file->date = time();
        $file->mimetype = $this->mimetype;

        $file_id = $file->insert();

        if (!$file_id) {

            common_debug("storeFile: problem inserting new file");
            common_log_db_error($file, "INSERT", __FILE__);
            throw new ClientException(_('There was a database error while saving your file. Please try again.'));
        }

        common_debug('finished storing file');

        return $file;
    }

    function rememberFile($file, $short)
    {
        $this->maybeAddRedir($file->id, $short);
    }

    function maybeAddRedir($file_id, $url)
    {

        common_debug("maybeAddRedir: looking up url: $url for file id $file_id");

        $file_redir = File_redirection::staticGet('url', $url);

        if (empty($file_redir)) {

            common_debug("maybeAddRedir: $url is not in the db");

            $file_redir = new File_redirection;
            $file_redir->url = $url;
            $file_redir->file_id = $file_id;

            $result = $file_redir->insert();

            if (!$result) {
                common_log_db_error($file_redir, "INSERT", __FILE__);
                throw new ClientException(_('There was a database error while saving your file. Please try again.'));
            }
        } else {

            common_debug("maybeAddRedir: no need to add $url, it's already in the db");
        }
    }

    static function fromUpload($param = 'media', $user = null)
    {
        common_debug("fromUpload: param = $param");

        if (empty($user)) {
            $user = common_current_user();
        }

        if (!isset($_FILES[$param]['error'])){
            common_debug('no file found');
            return;
        }

        switch ($_FILES[$param]['error']) {
        case UPLOAD_ERR_OK: // success, jump out
            break;
        case UPLOAD_ERR_INI_SIZE:
            throw new ClientException(_('The uploaded file exceeds the ' .
                'upload_max_filesize directive in php.ini.'));
            return;
        case UPLOAD_ERR_FORM_SIZE:
            throw new ClientException(
                _('The uploaded file exceeds the MAX_FILE_SIZE directive' .
                ' that was specified in the HTML form.'));
            return;
        case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES[$param]['tmp_name']);
            throw new ClientException(_('The uploaded file was only' .
                ' partially uploaded.'));
            return;
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new ClientException(_('Missing a temporary folder.'));
            return;
        case UPLOAD_ERR_CANT_WRITE:
            throw new ClientException(_('Failed to write file to disk.'));
            return;
        case UPLOAD_ERR_EXTENSION:
            throw new ClientException(_('File upload stopped by extension.'));
            return;
        default:
            throw new ClientException(_('System error uploading file.'));
            return;
        }

        if (!MediaFile::respectsQuota($user, $_FILES['attach']['size'])) {

            // Should never actually get here

            @unlink($_FILES[$param]['tmp_name']);
            throw new ClientException(_('File exceeds user\'s quota!'));
            return;
        }

        $mimetype = MediaFile::getUploadedFileType($_FILES[$param]['tmp_name']);

        $filename = null;

        if (isset($mimetype)) {

            $basename = basename($_FILES[$param]['name']);
            $filename = File::filename($user->getProfile(), $basename, $mimetype);
            $filepath = File::path($filename);

            common_debug("filepath = " . $filepath);

            $result = move_uploaded_file($_FILES[$param]['tmp_name'], $filepath);

            if (!$result) {
                throw new ClientException(_('File could not be moved to destination directory.'));
                return;
            }

        } else {
            throw new ClientException(_('Could not determine file\'s mime-type!'));
            return;
        }

        return new MediaFile($user, $filename, $mimetype);
    }

    static function fromFilehandle($fh, $user) {

        $stream = stream_get_meta_data($fh);

        if (MediaFile::respectsQuota($user, filesize($stream['uri']))) {

            // Should never actually get here

            throw new ClientException(_('File exceeds user\'s quota!'));
            return;
        }

        $mimetype = MediaFile::getUploadedFileType($fh);

        $filename = null;

        if (isset($mimetype)) {

            $filename = File::filename($user->getProfile(), "email", $mimetype);

            $filepath = File::path($filename);

            $result = copy($stream['uri'], $filepath) && chmod($filepath, 0664);

            if (!$result) {
                throw new ClientException(_('File could not be moved to destination directory.' .
                    $stream['uri'] . ' ' . $filepath));
            }
        } else {
            throw new ClientException(_('Could not determine file\'s mime-type!'));
            return;
        }

        return new MediaFile($user, $filename, $mimetype);
    }

    static function getUploadedFileType($f) {
        require_once 'MIME/Type.php';

        common_debug("in getUploadedFileType");

        $cmd = &PEAR::getStaticProperty('MIME_Type', 'fileCmd');
        $cmd = common_config('attachments', 'filecommand');

        $filetype = null;

        if (is_string($f)) {

            // assuming a filename

            $filetype = MIME_Type::autoDetect($f);
        } else {

            // assuming a filehandle

            $stream  = stream_get_meta_data($f);
            $filetype = MIME_Type::autoDetect($stream['uri']);
        }

        if (in_array($filetype, common_config('attachments', 'supported'))) {
            return $filetype;
        }
        $media = MIME_Type::getMedia($filetype);
        if ('application' !== $media) {
            $hint = sprintf(_(' Try using another %s format.'), $media);
        } else {
            $hint = '';
        }
        throw new ClientException(sprintf(
            _('%s is not a supported filetype on this server.'), $filetype) . $hint);
    }

    static function respectsQuota($user, $filesize)
    {
        $file = new File;
        $result = $file->isRespectsQuota($user, $filesize);
        if ($result === true) {
            return true;
        } else {
            throw new ClientException($result);
        }
    }

}