/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";
import type {ChangeEvent} from "react";

import {
    confirmAction,
    deleteUpload,
    finalizeUpload,
    getUploadConfig,
    getUploadTarget,
    notifyAlert,
    notifyException,
    refreshUploadTarget,
} from "./client";
import type {BlobOffloadFile, UploaderConfig, UploaderStrings} from "./types";

type Props = {
    assignId: number;
    inputName: string;
    strings: UploaderStrings;
};

type BatchProgressState = {
    filename: string;
    completedFiles: number;
    totalFiles: number;
    completedBytes: number;
    currentBytes: number;
    totalBytes: number;
    finishing: boolean;
};

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const units = ["KB", "MB", "GB", "TB"];
    const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length);
    const amount = bytes / 1024 ** unit;
    return `${new Intl.NumberFormat(undefined, {maximumFractionDigits: amount < 10 ? 1 : 0}).format(amount)} ${units[unit - 1]}`;
};

const getErrorMessage = (error: unknown): string => {
    if (error && typeof error === "object" && "message" in error && typeof error.message === "string") {
        return error.message;
    }

    return "";
};

const getErrorCode = (error: unknown): string => {
    if (error && typeof error === "object" && "errorcode" in error && typeof error.errorcode === "string") {
        return error.errorcode;
    }

    return "";
};

const isExpectedUserError = (error: unknown): boolean => {
    const errorcode = getErrorCode(error);
    return [
        "maxfilesreached",
        "maxbytesexceeded",
        "error:filetypenotallowed",
        "error:submissionnoteditable",
        "error:blobdeletefailed",
        "error:blobverificationfailed",
        "error:filenotfound",
    ].includes(errorcode);
};

const BLOCK_BYTES = 32 * 1024 * 1024;
const PARALLEL_BLOCKS = 3;
const MAX_ATTEMPTS = 4;

type UploadTarget = {
    uploadtoken: string;
    blobpath: string;
    uploadurl: string;
    expiresat: number;
};

type UploadError = Error & {status?: number};

const put = (
    url: string,
    body: Blob | string,
    headers: Record<string, string>,
    onProgress: (loadedBytes: number) => void
): Promise<string> => new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open("PUT", url);
        request.timeout = 15 * 60 * 1000;
        request.setRequestHeader("x-ms-version", "2023-11-03");
        for (const [name, value] of Object.entries(headers)) {
            request.setRequestHeader(name, value);
        }

        request.upload.addEventListener("progress", event => {
            if (event.lengthComputable) {
                onProgress(event.loaded);
            }
        });

        request.addEventListener("load", () => {
            if (request.status >= 200 && request.status < 300) {
                resolve(request.getResponseHeader("etag") || "");
                return;
            }
            const error = new Error(`Upload failed (HTTP ${request.status})`) as UploadError;
            error.status = request.status;
            reject(error);
        });

        request.addEventListener("error", () => reject(new Error("Upload failed (network error)")));
        request.addEventListener("timeout", () => reject(new Error("Upload timed out")));
        request.send(body);
    });

const uploadBlob = async(
    assignId: number,
    target: UploadTarget,
    localFile: File,
    onProgress: (loadedBytes: number) => void,
    onFinishing: () => void
): Promise<string> => {
    let currentTarget = target;
    let refreshPromise: Promise<void> | null = null;
    const getUrl = async(force = false): Promise<string> => {
        if (force || Date.now() / 1000 > currentTarget.expiresat - 90) {
            if (!refreshPromise) {
                refreshPromise = refreshUploadTarget(assignId, target.uploadtoken)
                    .then(refreshed => {
                        currentTarget = {...currentTarget, ...refreshed};
                    })
                    .finally(() => {
                        refreshPromise = null;
                    });
            }
            await refreshPromise;
        }
        return currentTarget.uploadurl;
    };

    const putWithRetry = async(
        suffix: string,
        body: Blob | string,
        headers: Record<string, string>,
        progress: (loadedBytes: number) => void
    ): Promise<string> => {
        for (let attempt = 0; attempt < MAX_ATTEMPTS; attempt++) {
            const url = (await getUrl()) + suffix;
            try {
                return await put(url, body, headers, progress);
            } catch (error) {
                progress(0);
                const status = (error as UploadError).status || 0;
                if (status === 403) {
                    await getUrl(true);
                } else if (status !== 0 && status !== 408 && status !== 429 && status < 500) {
                    throw error;
                }
                if (attempt === MAX_ATTEMPTS - 1) {
                    throw error;
                }
                await new Promise(resolve => setTimeout(resolve, 1000 * 2 ** attempt));
            }
        }
        throw new Error("Upload failed");
    };

    const mimetype = localFile.type || "application/octet-stream";
    if (localFile.size <= BLOCK_BYTES) {
        const etag = await putWithRetry("", localFile, {
            "Content-Type": mimetype,
            "x-ms-blob-content-type": mimetype,
            "x-ms-blob-type": "BlockBlob",
        }, loaded => onProgress(Math.min(localFile.size, loaded)));
        onProgress(localFile.size);
        onFinishing();
        await getUrl(); // Extend the pending record if the upload used most of its SAS lifetime.
        return etag;
    }

    const blockCount = Math.ceil(localFile.size / BLOCK_BYTES);
    const blockIds = Array.from({length: blockCount}, (_, index) => btoa(String(index).padStart(6, "0")));
    const loaded = new Array<number>(blockCount).fill(0);
    const reportProgress = () => onProgress(loaded.reduce((sum, bytes) => sum + bytes, 0));
    let nextBlock = 0;
    let failed = false;
    const worker = async() => {
        while (!failed && nextBlock < blockCount) {
            const index = nextBlock++;
            const start = index * BLOCK_BYTES;
            const end = Math.min(start + BLOCK_BYTES, localFile.size);
            const suffix = `&comp=block&blockid=${encodeURIComponent(blockIds[index])}`;
            try {
                await putWithRetry(suffix, localFile.slice(start, end), {
                    "Content-Type": "application/octet-stream",
                }, bytes => {
                    loaded[index] = Math.min(end - start, bytes);
                    reportProgress();
                });
            } catch (error) {
                failed = true;
                throw error;
            }
            loaded[index] = end - start;
            reportProgress();
        }
    };
    const results = await Promise.allSettled(
        Array.from({length: Math.min(PARALLEL_BLOCKS, blockCount)}, () => worker())
    );
    const failure = results.find(result => result.status === "rejected");
    if (failure && failure.status === "rejected") {
        throw failure.reason;
    }

    onFinishing();
    const blockList = `<?xml version="1.0" encoding="utf-8"?><BlockList>${blockIds.map(
        id => `<Latest>${id}</Latest>`
    ).join("")}</BlockList>`;
    const etag = await putWithRetry("&comp=blocklist", blockList, {
        "Content-Type": "application/xml",
        "x-ms-blob-content-type": mimetype,
    }, () => {});
    await getUrl(); // Keep the pending token valid until Moodle verifies the committed blob.
    return etag;
};

const Uploader = ({assignId, inputName, strings}: Props) => {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const filesRef = useRef<BlobOffloadFile[]>([]);
    const uploadingRef = useRef(false);
    const [config, setConfig] = useState<UploaderConfig | null>(null);
    const [files, setFiles] = useState<BlobOffloadFile[]>([]);
    const [busy, setBusy] = useState<"upload" | "delete" | null>(null);
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(true);
    const [uploadProgress, setUploadProgress] = useState<BatchProgressState | null>(null);

    useEffect(() => {
        let active = true;

        setLoading(true);
        setError("");

        void getUploadConfig(assignId)
            .then(result => {
                if (!active) {
                    return;
                }

                setConfig(result);
                filesRef.current = result.files || [];
                syncInput(filesRef.current);
                setFiles(result.files || []);
                setLoading(false);
            })
            .catch(fetchError => {
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

    const syncInput = (currentFiles: BlobOffloadFile[]) => {
        const root = containerRef.current;
        if (!root) {
            return;
        }

        const form = root.closest("form");
        const selector = `[name="${inputName}"]`;
        const input = form?.querySelector<HTMLInputElement>(selector) ?? document.querySelector<HTMLInputElement>(selector);

        if (input) {
            input.value = JSON.stringify({
                fileids: currentFiles.map(file => file.id),
            });
        }
    };

    useEffect(() => {
        if (config) {
            syncInput(files);
        }
    }, [files, inputName, config]);

    useEffect(() => {
        const form = containerRef.current?.closest("form");
        if (!form) {
            return;
        }
        const preventEarlySubmit = (event: Event) => {
            if (uploadingRef.current) {
                event.preventDefault();
                event.stopImmediatePropagation();
                void notifyAlert(strings.waitforfiles);
            }
        };
        form.addEventListener("submit", preventEarlySubmit, true);
        return () => form.removeEventListener("submit", preventEarlySubmit, true);
    }, [strings.waitforfiles]);

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

    const reportError = async(uploadError: unknown) => {
        setError(getErrorMessage(uploadError) || strings.uploadfailed);
        if (!isExpectedUserError(uploadError)) {
            await notifyException(uploadError);
        }
    };

    const uploadFile = async(localFile: File, completedFiles: number, completedBytes: number, totalFiles: number,
        totalBytes: number): Promise<boolean> => {
        if (!config) {
            return false;
        }

        if (filesRef.current.length >= config.maxfiles) {
            setError(strings.maxfilesreached);
            return false;
        }

        if (config.maxbytes > 0 && localFile.size > config.maxbytes) {
            setError(strings.maxbytesexceeded);
            return false;
        }

        setUploadProgress({
            filename: localFile.name,
            completedFiles,
            totalFiles,
            completedBytes,
            currentBytes: 0,
            totalBytes,
            finishing: false,
        });

        try {
            const target = await getUploadTarget(
                assignId,
                localFile.name,
                localFile.size,
                localFile.type || "application/octet-stream"
            );

            const etag = await uploadBlob(
                assignId,
                target,
                localFile,
                loadedBytes => {
                    setUploadProgress(progress => progress ? {...progress, currentBytes: loadedBytes} : null);
                },
                () => setUploadProgress(progress => progress ? {
                    ...progress,
                    currentBytes: localFile.size,
                    finishing: true,
                } : null)
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

            const nextFiles = [...filesRef.current, uploadedFile];
            filesRef.current = nextFiles;
            syncInput(nextFiles);
            setFiles(nextFiles);
            setUploadProgress(progress => progress ? {
                ...progress,
                completedFiles: completedFiles + 1,
                completedBytes: completedBytes + localFile.size,
                currentBytes: 0,
                finishing: false,
            } : null);
            return true;
        } catch (uploadError) {
            await reportError(uploadError);
            return false;
        }
    };

    const handleSelection = async(event: ChangeEvent<HTMLInputElement>) => {
        const input = event.target;
        const selectedFiles = Array.from(input.files || []);
        if (!selectedFiles.length || busy) {
            return;
        }

        const totalBytes = selectedFiles.reduce((sum, file) => sum + file.size, 0);
        let completedBytes = 0;
        let completedFiles = 0;
        setError("");
        setBusy("upload");
        uploadingRef.current = true;
        try {
            for (const localFile of selectedFiles) {
                const uploaded = await uploadFile(localFile, completedFiles, completedBytes,
                    selectedFiles.length, totalBytes);
                if (!uploaded) {
                    break;
                }
                completedFiles++;
                completedBytes += localFile.size;
            }
        } finally {
            uploadingRef.current = false;
            setBusy(null);
            setUploadProgress(null);
            input.value = "";
        }
    };

    const handleDelete = async(fileId: number) => {
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
        setBusy("delete");
        uploadingRef.current = true;

        try {
            await deleteUpload(assignId, fileId);
            const nextFiles = filesRef.current.filter(file => file.id !== fileId);
            filesRef.current = nextFiles;
            syncInput(nextFiles);
            setFiles(nextFiles);
        } catch (deleteError) {
            await reportError(deleteError);
        } finally {
            uploadingRef.current = false;
            setBusy(null);
        }
    };

    const remainingBytes = uploadProgress
        ? Math.max(0, uploadProgress.totalBytes - uploadProgress.completedBytes - uploadProgress.currentBytes)
        : 0;
    const progressPercent = uploadProgress
        ? Math.floor(uploadProgress.totalBytes > 0
            ? ((uploadProgress.completedBytes + uploadProgress.currentBytes) / uploadProgress.totalBytes) * 100
            : (uploadProgress.completedFiles / uploadProgress.totalFiles) * 100)
        : 0;

    return (
        <div ref={containerRef}>
            <div className="assignsubmission-bloboffload__panel">
                <label className="assignsubmission-bloboffload__label">
                    <span className="assignsubmission-bloboffload__prompt">{strings.uploadfiles}</span>
                    <input
                        type="file"
                        className="assignsubmission-bloboffload__input form-control"
                        multiple
                        accept={config?.acceptattr || ""}
                        disabled={!!busy || loading || !config}
                        onChange={event => void handleSelection(event)}
                    />
                </label>
                {metaText && <div className="assignsubmission-bloboffload__meta">{metaText}</div>}
            </div>

            <div className="assignsubmission-bloboffload__messages">
                {error && <div className="alert alert-danger mb-3">{error}</div>}
                {!error && (loading || busy === "delete") && (
                    <div className="alert alert-info mb-3">{loading ? strings.loading : strings.deleting}</div>
                )}
            </div>

            {uploadProgress && (
                <div className="assignsubmission-bloboffload__progresscard">
                    <div className="assignsubmission-bloboffload__progresshead">
                        <div className="assignsubmission-bloboffload__progressname">
                            {uploadProgress.finishing ? strings.finishing : strings.uploading}: {uploadProgress.filename}
                        </div>
                        <div className="assignsubmission-bloboffload__progresspercent">{progressPercent}%</div>
                    </div>
                    <div
                        className="assignsubmission-bloboffload__progressbar"
                        role="progressbar"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={progressPercent}
                        aria-label={strings.uploading}
                    >
                        <span
                            className="assignsubmission-bloboffload__progressvalue"
                            style={{width: `${progressPercent}%`}}
                        />
                    </div>
                    <div className="assignsubmission-bloboffload__meta">
                        <span aria-live="polite">
                            {uploadProgress.completedFiles}/{uploadProgress.totalFiles} {strings.filesuploaded}
                        </span>
                        {" · "}{formatBytes(remainingBytes)} {strings.remaining}
                    </div>
                </div>
            )}

            <div className="assignsubmission-bloboffload__section">
                <div className="assignsubmission-bloboffload__sectionhead">
                    <div className="assignsubmission-bloboffload__heading">{strings.currentfiles}</div>
                    {!!files.length && (
                        <span className="assignsubmission-bloboffload__countbadge">{files.length}</span>
                    )}
                </div>
                {!files.length && !!config && (
                    <div className="assignsubmission-bloboffload__empty">{strings.nofiles}</div>
                )}

                {!!files.length && (
                    <ul className="assignsubmission-bloboffload__files">
                        {files.map(file => (
                            <li className="assignsubmission-bloboffload__file" key={file.id}>
                                <div className="assignsubmission-bloboffload__filebody">
                                    <div className="assignsubmission-bloboffload__filename">{file.filename}</div>
                                    <div className="assignsubmission-bloboffload__filemeta">
                                        {file.filesize}
                                    </div>
                                </div>
                                <div className="assignsubmission-bloboffload__actions">
                                    <a
                                        href={file.downloadurl}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="btn btn-outline-secondary btn-sm"
                                    >
                                        {strings.download}
                                    </a>
                                    <button
                                        type="button"
                                        className="btn btn-outline-danger btn-sm"
                                        disabled={!!busy}
                                        onClick={() => {
                                            void handleDelete(file.id);
                                        }}
                                    >
                                        {strings.delete}
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
};

export default Uploader;
