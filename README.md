# moodle-assignsubmission_bloboffload

Assignment submission subplugin for Moodle that stores uploaded files in
Azure Blob Storage while keeping submission metadata in Moodle.

## Requirements

- Moodle 5.2 and PHP 8.3 or later.
- An Azure Blob Storage account, a private container, and its shared account key.

Install this directory as `mod/assign/submission/bloboffload`, then complete Moodle's plugin upgrade. Configure the storage account, key, and container under **Site administration → Plugins → Assignment submission → Blob offload** and enable the submission type in an assignment.

Set **Submission type name** on the same site settings page to change the label shown in assignment submission settings. Leave it empty to use **Blob offload**.

## Course access

In the same site settings, enter the IDs of courses allowed to use blob offload, separated by commas or new lines. Enter `*` alone to allow all courses; leave the setting empty to allow none. The ID appears in each course URL as `course/view.php?id=123`. Outside the list, blob offload is absent from assignment submission settings and cannot accept new uploads, even if an assignment had it enabled before. Existing blob files remain downloadable to authorised users. Check the assignment settings in one listed course and one unlisted course after changing the list.

The uploader mounts through Moodle 5.2's Mustache `react` helper and ESM import map. Deploy `js/esm/build/uploader.js` with the PHP files and purge Moodle caches after an upgrade.

## Azure CORS

Set a CORS rule on the **Blob** service for the Moodle site's exact origin. Allow `PUT` and `OPTIONS` with request headers `content-type`, `x-ms-blob-content-type`, `x-ms-blob-type`, and `x-ms-version`. Expose `ETag` if available. The browser sends file bytes straight to Azure; Moodle checks the committed blob and records its metadata. Files above 32 MiB use 32 MiB blocks with up to three uploads at once, then a block-list commit. Failed blocks are retried. The uploader renews its blob-specific SAS as needed during long transfers; pending uploads may be renewed for up to 24 hours. The plugin limit is 20 GiB per file. Select a larger **Maximum submission size** in the assignment settings to allow files above the previous 5,000 MiB limit.

## Live smoke test

As a student, open an assignment with blob offload enabled, select three files together, and check that the uploader shows the completed file count and bytes remaining. Test a file above 32 MiB and, if available, one above 5 GiB. Save the submission, reload the page, and download a file to check its size and contents. Then delete it and confirm it disappears. Also try a disallowed file type and an oversized file. For a team submission, check that another team member can see and download the file. The Moodle server must be able to send `HEAD` requests to the Azure Blob endpoint to finalise uploads.
