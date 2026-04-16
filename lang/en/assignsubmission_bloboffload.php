<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for blob offload assignment submission plugin.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Blob offload';
$string['bloboffload'] = 'Blob offload';
$string['enabled'] = 'Enable blob offload submissions';
$string['maxfilessubmission'] = 'Maximum uploaded files';
$string['maximumsubmissionsize'] = 'Maximum submission size';
$string['acceptedfiletypes'] = 'Accepted file types';
$string['noconfiguration'] = 'Blob offload is not fully configured yet.';
$string['uploadfiles'] = 'Upload files';
$string['currentfiles'] = 'Uploaded files';
$string['loading'] = 'Loading uploader';
$string['nofiles'] = 'No uploaded files';
$string['countfiles'] = '{$a} file(s)';
$string['download'] = 'Download';
$string['view'] = 'View';
$string['delete'] = 'Delete';
$string['deleteconfirm'] = 'Are you sure you want to delete this file?';
$string['retry'] = 'Retry';
$string['uploadfailed'] = 'Upload failed';
$string['uploadinprogress'] = 'Uploading';
$string['uploadready'] = 'Ready';
$string['uploadnotallowed'] =
    'Uploads are not allowed for this assignment at the moment.';
$string['invalidpayload'] = 'Invalid blob offload submission payload.';
$string['invaliduploadtoken'] = 'Invalid upload token.';
$string['maxfilesreached'] = 'Maximum number of files reached for this assignment.';
$string['maxbytesexceeded'] = 'The file exceeds the allowed maximum upload size.';
$string['storageaccount'] = 'Azure storage account';
$string['storageaccount_desc'] = 'Storage account name used to issue SAS tokens.';
$string['accountkey'] = 'Azure storage account key';
$string['accountkey_desc'] = 'Shared key for the Azure storage account.';
$string['containername'] = 'Container name';
$string['containername_desc'] =
    'Azure Blob Storage container for assignment uploads.';
$string['endpointsuffix'] = 'Endpoint suffix';
$string['endpointsuffix_desc'] =
    'Azure storage endpoint suffix, for example core.windows.net.';
$string['uploadsasexpiry'] = 'Upload SAS lifetime';
$string['uploadsasexpiry_desc'] = 'How long upload SAS tokens stay valid.';
$string['readsasexpiry'] = 'Read SAS lifetime';
$string['readsasexpiry_desc'] = 'How long read SAS tokens stay valid.';
$string['defaultmaxbytes'] = 'Default maximum bytes';
$string['defaultmaxbytes_desc'] =
    'Default maximum upload size in bytes. Use 0 to inherit the course/site limit.';
$string['defaultmaxfiles'] = 'Default maximum files';
$string['defaultmaxfiles_desc'] = 'Default maximum number of files allowed.';
$string['privacy:metadata'] =
    'The blob offload submission plugin stores metadata about assignment '
    . 'uploads in Azure Blob Storage.';
$string['privacy:metadata:assignsubmission_bloboffload'] =
    'Summary data for a blob-backed submission.';
$string['privacy:metadata:assignsubmission_bloboffload_file'] =
    'Metadata for each blob-backed file.';
$string['privacy:metadata:assignsubmission_bloboffload_file:originalfilename'] =
    'The original filename supplied by the user.';
$string['privacy:metadata:assignsubmission_bloboffload_file:storedfilename'] =
    'The generated filename stored in Azure Blob Storage.';
$string['privacy:metadata:assignsubmission_bloboffload_file:blobpath'] =
    'The blob path inside the configured Azure container.';
$string['privacy:metadata:assignsubmission_bloboffload_file:bloburl'] =
    'The base blob URL.';
$string['privacy:metadata:assignsubmission_bloboffload_file:mimetype'] =
    'The MIME type reported for the uploaded file.';
$string['privacy:metadata:assignsubmission_bloboffload_file:filesize'] =
    'The uploaded file size in bytes.';
$string['privacy:metadata:assignsubmission_bloboffload_file:etag'] =
    'The Azure Blob ETag returned for the uploaded object.';
$string['privacy:metadata:assignsubmission_bloboffload_file:state'] =
    'The current lifecycle state for the file metadata.';
$string['privacy:metadata:assignsubmission_bloboffload_file:userid'] =
    'The user ID associated with the uploaded file.';
$string['privacy:metadata:assignsubmission_bloboffload_file:submissionid'] =
    'The assignment submission ID associated with the upload.';
$string['error:azureconfigmissing'] = 'Azure Blob Storage settings are incomplete.';
$string['error:invalidfilename'] = 'The file name is invalid.';
$string['error:fileoutsideprefix'] =
    'The uploaded blob path does not match the expected submission path.';
$string['error:filetypenotallowed'] =
    'That file type is not allowed for this assignment.';
$string['error:submissionnoteditable'] = 'This submission can no longer be edited.';
$string['error:filenotfound'] = 'The requested file metadata could not be found.';
$string['error:forbiddendownload'] = 'You are not allowed to access this file.';
$string['error:blobdeletefailed'] =
    'The file could not be deleted from Azure storage.';
$string['error:storageunavailable'] = 'Blob storage is unavailable.';
$string['error:invalidstate'] = 'The file is not in a valid state for this action.';
