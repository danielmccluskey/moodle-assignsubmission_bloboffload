/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {requireAmd} from "./amd";
import type {BlobOffloadFile, UploaderConfig} from "./types";

type AjaxModule = {
    call: (requests: Array<{methodname: string; args: Record<string, unknown>}>) => Promise<unknown>[];
};

type NotificationModule = {
    alert: (title: string, message: string) => void;
    confirm: (
        title: string,
        message: string,
        yesLabel: string,
        noLabel: string,
        yesCallback: () => void,
        noCallback?: () => void
    ) => void;
    exception: (error: unknown) => void;
};

const getAjax = async(): Promise<AjaxModule> => {
    const [ajax] = await requireAmd<[AjaxModule]>(["core/ajax"]);
    return ajax;
};

const getNotification = async(): Promise<NotificationModule> => {
    const [notification] = await requireAmd<[NotificationModule]>(["core/notification"]);
    return notification;
};

const call = async<T>(methodname: string, args: Record<string, unknown>): Promise<T> => {
    const ajax = await getAjax();
    return ajax.call([{methodname, args}])[0] as Promise<T>;
};

export const getUploadConfig = (assignId: number): Promise<UploaderConfig> =>
    call<UploaderConfig>("assignsubmission_bloboffload_get_upload_config", {
        assignid: assignId,
    });

export const getUploadTarget = (
    assignId: number,
    filename: string,
    filesize: number,
    mimetype: string
): Promise<{
    uploadtoken: string;
    blobpath: string;
    uploadurl: string;
}> =>
    call("assignsubmission_bloboffload_get_upload_target", {
        assignid: assignId,
        filename,
        filesize,
        mimetype,
    });

export const finalizeUpload = (
    assignId: number,
    uploadtoken: string,
    blobpath: string,
    filename: string,
    filesize: number,
    mimetype: string,
    etag: string
): Promise<BlobOffloadFile> =>
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

export const deleteUpload = (assignId: number, fileId: number): Promise<{status: boolean}> =>
    call("assignsubmission_bloboffload_delete_upload", {
        assignid: assignId,
        fileid: fileId,
    });

export const notifyAlert = async(message: string): Promise<void> => {
    const notification = await getNotification();
    notification.alert("", message);
};

export const notifyException = async(error: unknown): Promise<void> => {
    const notification = await getNotification();
    notification.exception(error);
};

export const confirmAction = async(
    title: string,
    message: string,
    yesLabel: string,
    noLabel: string
): Promise<boolean> => {
    const notification = await getNotification();

    return new Promise(resolve => {
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
