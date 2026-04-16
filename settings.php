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
 * Site settings for blob offload assignment submission plugin.
 *
 * @package    assignsubmission_bloboffload
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'assignsubmission_bloboffload/storageaccount',
        get_string('storageaccount', 'assignsubmission_bloboffload'),
        get_string('storageaccount_desc', 'assignsubmission_bloboffload'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'assignsubmission_bloboffload/accountkey',
        get_string('accountkey', 'assignsubmission_bloboffload'),
        get_string('accountkey_desc', 'assignsubmission_bloboffload'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_bloboffload/containername',
        get_string('containername', 'assignsubmission_bloboffload'),
        get_string('containername_desc', 'assignsubmission_bloboffload'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_bloboffload/endpointsuffix',
        get_string('endpointsuffix', 'assignsubmission_bloboffload'),
        get_string('endpointsuffix_desc', 'assignsubmission_bloboffload'),
        'core.windows.net',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configduration(
        'assignsubmission_bloboffload/uploadsasexpiry',
        get_string('uploadsasexpiry', 'assignsubmission_bloboffload'),
        get_string('uploadsasexpiry_desc', 'assignsubmission_bloboffload'),
        900
    ));

    $settings->add(new admin_setting_configduration(
        'assignsubmission_bloboffload/readsasexpiry',
        get_string('readsasexpiry', 'assignsubmission_bloboffload'),
        get_string('readsasexpiry_desc', 'assignsubmission_bloboffload'),
        600
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_bloboffload/maxbytes',
        get_string('defaultmaxbytes', 'assignsubmission_bloboffload'),
        get_string('defaultmaxbytes_desc', 'assignsubmission_bloboffload'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'assignsubmission_bloboffload/maxfiles',
        get_string('defaultmaxfiles', 'assignsubmission_bloboffload'),
        get_string('defaultmaxfiles_desc', 'assignsubmission_bloboffload'),
        20,
        PARAM_INT
    ));
}
