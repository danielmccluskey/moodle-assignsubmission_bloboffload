var a=o=>new Promise((n,i)=>{if(typeof window.require!="function"){i(new Error("Moodle AMD loader is unavailable."));return}window.require(o,(...t)=>n(t),i)});var c=async()=>{let[o]=await a(["core/ajax"]);return o},l=async()=>{let[o]=await a(["core/notification"]);return o},r=async(o,n)=>(await c()).call([{methodname:o,args:n}])[0],f=o=>r("assignsubmission_bloboffload_get_upload_config",{assignid:o}),m=(o,n,i,t)=>r("assignsubmission_bloboffload_get_upload_target",{assignid:o,filename:n,filesize:i,mimetype:t}),p=(o,n,i,t,s,e,d)=>r("assignsubmission_bloboffload_finalize_upload",{assignid:o,uploadtoken:n,blobpath:i,filename:t,filesize:s,mimetype:e,etag:d,contenthash:""}),b=(o,n)=>r("assignsubmission_bloboffload_delete_upload",{assignid:o,fileid:n}),w=async o=>{(await l()).alert("",o)},x=async o=>{(await l()).exception(o)},y=async(o,n,i,t)=>{let s=await l();return new Promise(e=>{s.confirm(o,n,i,t,()=>e(!0),()=>e(!1))})};export{y as confirmAction,b as deleteUpload,p as finalizeUpload,f as getUploadConfig,m as getUploadTarget,w as notifyAlert,x as notifyException};
/*
 * This file is part of Moodle - http://moodle.org/
 *
 * @copyright  2026 Daniel McCluskey
 * @author     Daniel McCluskey
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
