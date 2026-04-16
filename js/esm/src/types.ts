/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type BlobOffloadFile = {
    id: number;
    filename: string;
    filesize: string;
    mimetype: string;
    state: string;
    downloadurl: string;
    viewurl: string;
};

export type UploaderConfig = {
    submissionid: number;
    maxfiles: number;
    maxbytes: number;
    maxbyteslabel: string;
    filetypeslist: string;
    acceptedtypeslabel: string;
    acceptattr: string;
    files: BlobOffloadFile[];
};

export type UploaderStrings = {
    acceptedtypes: string;
    cancel: string;
    currentfiles: string;
    delete: string;
    deleteconfirm: string;
    download: string;
    loading: string;
    maxbytesexceeded: string;
    maxsize: string;
    maxfilesreached: string;
    nofiles: string;
    uploadfailed: string;
    uploadfiles: string;
    uploading: string;
    view: string;
};
