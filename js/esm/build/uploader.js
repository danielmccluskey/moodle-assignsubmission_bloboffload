/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import React, {startTransition, useEffect, useRef, useState} from "react";

import {
    confirmAction,
    deleteUpload,
    finalizeUpload,
    getUploadConfig,
    getUploadTarget,
    notifyAlert,
    notifyException,
} from "./client";

const e = React.createElement;

const getErrorMessage = (error) => {
    if (error && typeof error === "object" && typeof error.message === "string") {
        return error.message;
    }

    return "";
};

const getErrorCode = (error) => {
    if (error && typeof error === "object" && typeof error.errorcode === "string") {
        return error.errorcode;
    }

    return "";
};

const isExpectedUserError = (error) => {
    const errorcode = getErrorCode(error);
    return [
        "maxfilesreached",
        "maxbytesexceeded",
        "error:filetypenotallowed",
        "error:submissionnoteditable",
        "error:blobdeletefailed",
        "error:filenotfound",
    ].includes(errorcode);
};

const uploadBlobWithProgress = (uploadUrl, localFile, onProgress) =>
    new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();

        request.open("PUT", uploadUrl);
        request.setRequestHeader("Content-Type", localFile.type || "application/octet-stream");
        request.setRequestHeader("x-ms-blob-type", "BlockBlob");
        request.setRequestHeader("x-ms-version", "2023-11-03");

        request.upload.addEventListener("progress", (event) => {
            if (!event.lengthComputable) {
                return;
            }

            onProgress({
                filename: localFile.name,
                loadedBytes: event.loaded,
                totalBytes: event.total,
                percent: Math.max(0, Math.min(100, Math.round(event.loaded / event.total * 100))),
            });
        });

        request.addEventListener("load", () => {
            if (request.status >= 200 && request.status < 300) {
                resolve(request.getResponseHeader("etag") || "");
                return;
            }

            reject(new Error(`Upload failed (${request.status})`));
        });

        request.addEventListener("error", () => {
            reject(new Error("Upload failed"));
        });

        request.addEventListener("abort", () => {
            reject(new Error("Upload cancelled"));
        });

        request.send(localFile);
    });

const Uploader = ({assignId, inputName, strings}) => {
    const containerRef = useRef(null);
    const filesRef = useRef([]);
    const [config, setConfig] = useState(null);
    const [files, setFiles] = useState([]);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(true);
    const [uploadProgress, setUploadProgress] = useState(null);

    useEffect(() => {
        let active = true;

        setLoading(true);
        setError("");

        void getUploadConfig(assignId)
            .then((result) => {
                if (!active) {
                    return;
                }

                setConfig(result);
                filesRef.current = result.files || [];
                startTransition(() => {
                    setFiles(result.files || []);
                });
                setLoading(false);
            })
            .catch((fetchError) => {
                if (!active) {
                    return;
                }

                setError(getErrorMessage(fetchError) || strings.uploadfailed);
                setLoading(false);
                void notifyException(fetchError);
            });

        return () => {
            active = false;
        };
    }, [assignId, strings.uploadfailed]);

    useEffect(() => {
        filesRef.current = files;
    }, [files]);

    useEffect(() => {
        const root = containerRef.current;
        if (!root) {
            return;
        }

        const form = root.closest("form");
        const selector = `[name="${inputName}"]`;
        const input = (form && form.querySelector(selector)) || document.querySelector(selector);

        if (input) {
            input.value = JSON.stringify({
                fileids: files.map((file) => file.id),
            });
        }
    }, [files, inputName]);

    let metaText = "";
    if (config) {
        const parts = [];
        if (config.acceptedtypeslabel) {
            parts.push(`${strings.acceptedtypes}: ${config.acceptedtypeslabel}`);
        }
        if (config.maxbyteslabel) {
            parts.push(`${strings.maxsize}: ${config.maxbyteslabel}`);
        }
        metaText = parts.join(" | ");
    }

    const reportError = async(uploadError) => {
        setError(getErrorMessage(uploadError) || strings.uploadfailed);
        setBusy(false);
        setUploadProgress(null);
        if (!isExpectedUserError(uploadError)) {
            await notifyException(uploadError);
        }
    };

    const uploadFile = async(localFile) => {
        if (!config) {
            return;
        }

        if (filesRef.current.length >= config.maxfiles) {
            await notifyAlert(strings.maxfilesreached);
            return;
        }

        if (config.maxbytes > 0 && localFile.size > config.maxbytes) {
            await notifyAlert(strings.maxbytesexceeded);
            return;
        }

        setError("");
        setBusy(true);
        setUploadProgress({
            filename: localFile.name,
            loadedBytes: 0,
            totalBytes: localFile.size,
            percent: 0,
        });

        try {
            const target = await getUploadTarget(
                assignId,
                localFile.name,
                localFile.size,
                localFile.type || "application/octet-stream"
            );

            const etag = await uploadBlobWithProgress(
                target.uploadurl,
                localFile,
                (progress) => {
                    setUploadProgress(progress);
                }
            );

            const uploadedFile = await finalizeUpload(
                assignId,
                target.uploadtoken,
                target.blobpath,
                localFile.name,
                localFile.size,
                localFile.type || "application/octet-stream",
                etag
            );

            setUploadProgress({
                filename: localFile.name,
                loadedBytes: localFile.size,
                totalBytes: localFile.size,
                percent: 100,
            });

            startTransition(() => {
                setFiles((currentFiles) => {
                    const existingIndex = currentFiles.findIndex((file) => file.id === uploadedFile.id);
                    const nextFiles = existingIndex === -1
                        ? [...currentFiles, uploadedFile]
                        : currentFiles.map((file) => (file.id === uploadedFile.id ? uploadedFile : file));
                    filesRef.current = nextFiles;
                    return nextFiles;
                });
            });
            setBusy(false);
            setUploadProgress(null);
        } catch (uploadError) {
            await reportError(uploadError);
        }
    };

    const handleSelection = async(event) => {
        const selectedFiles = Array.from(event.target.files || []);

        for (const localFile of selectedFiles) {
            await uploadFile(localFile);
        }

        event.target.value = "";
    };

    const handleDelete = async(fileId) => {
        const confirmed = await confirmAction(
            strings.delete,
            strings.deleteconfirm,
            strings.delete,
            strings.cancel
        );
        if (!confirmed) {
            return;
        }

        setError("");
        setBusy(true);

        try {
            await deleteUpload(assignId, fileId);
            startTransition(() => {
                setFiles((currentFiles) => {
                    const nextFiles = currentFiles.filter((file) => file.id !== fileId);
                    filesRef.current = nextFiles;
                    return nextFiles;
                });
            });
            setBusy(false);
        } catch (deleteError) {
            await reportError(deleteError);
        }
    };

    const fileItems = files.map((file) =>
        e(
            "li",
            {
                className: "assignsubmission-bloboffload__file",
                key: file.id,
            },
            e(
                "div",
                {className: "assignsubmission-bloboffload__filebody"},
                e("div", {className: "assignsubmission-bloboffload__filename"}, file.filename),
                e(
                    "div",
                    {className: "assignsubmission-bloboffload__filemeta"},
                    `${file.filesize}${file.mimetype ? ` | ${file.mimetype}` : ""}`
                )
            ),
            e(
                "div",
                {className: "assignsubmission-bloboffload__actions"},
                e(
                    "a",
                    {
                        href: file.downloadurl,
                        className: "btn btn-outline-secondary btn-sm",
                    },
                    strings.download
                ),
                e(
                    "button",
                    {
                        type: "button",
                        className: "btn btn-outline-danger btn-sm",
                        disabled: busy,
                        onClick: () => {
                            void handleDelete(file.id);
                        },
                    },
                    strings.delete
                )
            )
        )
    );

    return e(
        "div",
        {ref: containerRef},
        e(
            "div",
            {className: "assignsubmission-bloboffload__panel"},
            e(
                "div",
                {className: "assignsubmission-bloboffload__eyebrow"},
                strings.uploadfiles
            ),
            e(
                "label",
                {className: "assignsubmission-bloboffload__label"},
                e("span", {className: "assignsubmission-bloboffload__prompt"}, strings.uploadfiles),
                e("input", {
                    type: "file",
                    className: "assignsubmission-bloboffload__input form-control",
                    multiple: true,
                    accept: (config && config.acceptattr) || "",
                    disabled: busy || loading,
                    onChange: (event) => {
                        void handleSelection(event);
                    },
                })
            ),
            metaText ? e("div", {className: "assignsubmission-bloboffload__meta"}, metaText) : null
        ),
        e(
            "div",
            {className: "assignsubmission-bloboffload__messages"},
            error ? e("div", {className: "alert alert-danger mb-3"}, error) : null,
            !error && (busy || loading)
                ? e("div", {className: "alert alert-info mb-3"}, busy ? strings.uploading : strings.loading)
                : null
        ),
        uploadProgress ? e(
            "div",
            {
                className: "assignsubmission-bloboffload__progresscard",
                "aria-live": "polite",
            },
            e(
                "div",
                {className: "assignsubmission-bloboffload__progresshead"},
                e("div", {className: "assignsubmission-bloboffload__progressname"}, uploadProgress.filename),
                e("div", {className: "assignsubmission-bloboffload__progresspercent"}, `${uploadProgress.percent}%`)
            ),
            e(
                "div",
                {
                    className: "assignsubmission-bloboffload__progressbar",
                    role: "progressbar",
                    "aria-valuemin": 0,
                    "aria-valuemax": 100,
                    "aria-valuenow": uploadProgress.percent,
                    "aria-label": `${strings.uploading}: ${uploadProgress.filename}`,
                },
                e("span", {
                    className: "assignsubmission-bloboffload__progressvalue",
                    style: {width: `${uploadProgress.percent}%`},
                })
            ),
            e(
                "div",
                {className: "assignsubmission-bloboffload__meta"},
                `${uploadProgress.percent}% uploaded`
            )
        ) : null,
        e(
            "div",
            {className: "assignsubmission-bloboffload__section"},
            e(
                "div",
                {className: "assignsubmission-bloboffload__sectionhead"},
                e("div", {className: "assignsubmission-bloboffload__heading"}, strings.currentfiles),
                files.length ? e("span", {className: "assignsubmission-bloboffload__countbadge"}, files.length) : null
            ),
            !files.length && !loading
                ? e("div", {className: "assignsubmission-bloboffload__empty"}, strings.nofiles)
                : null,
            files.length
                ? e("ul", {className: "assignsubmission-bloboffload__files"}, fileItems)
                : null
        )
    );
};

export default Uploader;
