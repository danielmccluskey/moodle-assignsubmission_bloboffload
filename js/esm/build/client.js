/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAmd} from "./amd";

const getAjax = async() => {
    const [ajax] = await requireAmd(["core/ajax"]);
    return ajax;
};

const getNotification = async() => {
    const [notification] = await requireAmd(["core/notification"]);
    return notification;
};

const call = async(methodname, args) => {
    const ajax = await getAjax();
    return ajax.call([{methodname, args}])[0];
};

const getUploadConfig = (assignId) =>
    call("assignsubmission_bloboffload_get_upload_config", {
        assignid: assignId,
    });

const getUploadTarget = (assignId, filename, filesize, mimetype) =>
    call("assignsubmission_bloboffload_get_upload_target", {
        assignid: assignId,
        filename,
        filesize,
        mimetype,
    });

const finalizeUpload = (assignId, uploadtoken, blobpath, filename, filesize, mimetype, etag) =>
    call("assignsubmission_bloboffload_finalize_upload", {
        assignid: assignId,
        uploadtoken,
        blobpath,
        filename,
        filesize,
        mimetype,
        etag,
        contenthash: "",
    });

const deleteUpload = (assignId, fileId) =>
    call("assignsubmission_bloboffload_delete_upload", {
        assignid: assignId,
        fileid: fileId,
    });

const notifyAlert = async(message) => {
    const notification = await getNotification();
    notification.alert("", message);
};

const notifyException = async(error) => {
    const notification = await getNotification();
    notification.exception(error);
};

const confirmAction = async(title, message, yesLabel, noLabel) => {
    const notification = await getNotification();

    return new Promise((resolve) => {
        notification.confirm(
            title,
            message,
            yesLabel,
            noLabel,
            () => resolve(true),
            () => resolve(false)
        );
    });
};

export {
    confirmAction,
    deleteUpload,
    finalizeUpload,
    getUploadConfig,
    getUploadTarget,
    notifyAlert,
    notifyException,
};
