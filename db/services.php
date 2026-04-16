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
 * External services definitions for blob offload submission plugin.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'assignsubmission_bloboffload_get_upload_config' => [
        'classname' => 'assignsubmission_bloboffload\external\get_upload_config',
        'methodname' => 'execute',
        'description' => 'Get uploader configuration and current files for a blob-offloaded assignment.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit',
    ],
    'assignsubmission_bloboffload_get_upload_target' => [
        'classname' => 'assignsubmission_bloboffload\external\get_upload_target',
        'methodname' => 'execute',
        'description' => 'Get a short-lived upload target for Azure Blob Storage.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit',
    ],
    'assignsubmission_bloboffload_finalize_upload' => [
        'classname' => 'assignsubmission_bloboffload\external\finalize_upload',
        'methodname' => 'execute',
        'description' => 'Finalize an uploaded blob and persist metadata for the submission draft.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit',
    ],
    'assignsubmission_bloboffload_delete_upload' => [
        'classname' => 'assignsubmission_bloboffload\external\delete_upload',
        'methodname' => 'execute',
        'description' => 'Delete or mark deleted a blob-backed uploaded file.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'mod/assign:submit',
    ],
    'assignsubmission_bloboffload_get_download_url' => [
        'classname' => 'assignsubmission_bloboffload\external\get_download_url',
        'methodname' => 'execute',
        'description' => 'Get a short-lived read URL for a blob-backed file.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'mod/assign:view',
    ],
];
