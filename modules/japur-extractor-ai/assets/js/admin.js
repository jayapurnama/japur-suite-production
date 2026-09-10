jQuery(function($){
 function esc(t){return $('<div>').text(t||'').html();}
 var JAF_MASTER=JAF.master_settings||{};
 function masterEnabled(key,def){ return Object.prototype.hasOwnProperty.call(JAF_MASTER,key) ? !!JAF_MASTER[key] : !!def; }
 function masterDiagnostic(key,def){ return masterEnabled('diagnostic_enabled',true) && masterEnabled(key,def); }
 function diagnosticLevel(){ var v=String(JAF_MASTER.diagnostic_level||'standard').toLowerCase(); return (v==='basic'||v==='detailed')?v:'standard'; }
 function multiFailurePolicy(){ var v=String(JAF_MASTER.multi_failure_policy||'continue').toLowerCase(); return v==='stop'?'stop':'continue'; }
 function continueOrStopMulti(items,index,counters){
   if(multiFailurePolicy()!=='stop') { runMultiDestination(items,index+1,counters); return; }
   counters.stopped=true;
   for(var j=index+1;j<items.length;j++){
     if(items[j] && (!items[j].status || items[j].status==='pending')){ items[j].status='skipped'; items[j].stage='SKIPPED'; items[j].message='Batch dihentikan karena tujuan sebelumnya gagal.'; updateMultiProgress(items[j],'error','Dilewati','Batch dihentikan oleh Kebijakan saat Multi gagal.'); }
   }
   finishMultiBatch(items,counters.ok,counters.failed,counters.results||[]);
 }

 function msg(t,ok){$('#jaf-status').text(t).css('color',ok?'#08752d':'#a40000');}
 var JAF_PREFLIGHT_OK=false;
 var JAF_PROCESS_LOCK=false;
 function safetyEnabled(key,def){ return masterEnabled('safety_guard_enabled',true) && masterEnabled(key,def); }
 function safetyGuardBeforeStart(target){
   if(!masterEnabled('safety_guard_enabled',true)) return true;
   if(safetyEnabled('safety_prevent_duplicate_click',true) && JAF_PROCESS_LOCK){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Proses sudah berjalan.</strong><br>Jangan jalankan workflow yang sama dua kali.</div>'); return false; }
   if(safetyEnabled('safety_block_active_background',true)){ var active=bgLoad(); if(active && !$('#jaf-workflow .jaf-workflow-card').hasClass('is-complete')){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Background Job masih aktif.</strong><br>Selesaikan atau hentikan proses Background yang sedang berjalan sebelum memulai workflow baru.</div>'); return false; } }
   if(safetyEnabled('safety_validate_before_start',true) && !validateProcessFields(target)) return false;
   if(safetyEnabled('safety_require_preflight',true) && !JAF_PREFLIGHT_OK){ runPreflight(false); return false; }
   JAF_PROCESS_LOCK=true; return true;
 }
 function preflightTargets(){
   var target=String($('#jaf-publish-target').val()||''), selected=[];
   if(target==='multi') selected=$('.jaf-multi-target-check:checked').map(function(){return String($(this).val()||'');}).get();
   return {target:target,type:String($('#jaf-type').val()||''),wordpress_profile:String($('#jaf-wp-profile').val()||''),blogger_profile:String($('#jaf-blogger-profile').val()||''),selected_targets:selected};
 }
 function validateProcessFields(target){
   target=target||String($('#jaf-publish-target').val()||'');
   var type=String($('#jaf-type').val()||'').trim(), mat=String($('#jaf-material').val()||'').trim();
   if(!type){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Jenis Artikel belum dipilih.</strong><br>Pilih Jenis Artikel terlebih dahulu sebelum membuat artikel.</div>'); $('#jaf-type').trigger('focus'); return false; }
   if(mat.length<30){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Materi belum siap.</strong><br>Materi minimal 30 karakter. '+(sourceMode==='url'?'Klik Ekstrak Materi terlebih dahulu.':'')+'</div>'); $('#jaf-material').trigger('focus'); return false; }
   if(target!=='wordpress' && target!=='blogger' && target!=='multi'){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Tujuan Publish belum dipilih.</strong><br>Pilih WordPress, Blogger, atau Wordpress + Blogger.</div>'); $('#jaf-publish-target').trigger('focus'); return false; }
   if(target==='multi'){
     var checked=$('.jaf-multi-target-check:checked').map(function(){return String($(this).val()||'');}).get();
     if(!checked.length){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Website tujuan belum dipilih.</strong><br>Pilih minimal satu website atau blog pada Multi Website.</div>'); return false; }
     var invalid='';
     $.each(checked,function(i,key){ var item=multiTargetByKey(key); if(!item)return; if(String(item.target||'')==='blogger' && !String(item.blogger_profile||'')){ invalid=item.name+' belum memiliki Profil Blogger.'; return false; } if(String(item.target||'')==='wordpress' && !multiCategoriesFor(item).length){ invalid=item.name+' belum memiliki Kategori WordPress.'; return false; } if(String(item.target||'')==='blogger' && !multiLabelsFor(item).length){ invalid=item.name+' belum memiliki Label Blogger.'; return false; } });
     if(invalid){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Data tujuan belum lengkap.</strong><br>'+esc(invalid)+'</div>'); return false; }
   } else if(target==='wordpress'){
     if(!String($('#jaf-category input:checked').map(function(){return $(this).val();}).get().join('')).trim()){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Kategori WordPress belum dipilih.</strong><br>Pilih minimal satu kategori sebelum membuat artikel.</div>'); return false; }
   } else if(target==='blogger'){
     if(!String($('#jaf-blogger-profile').val()||'')){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Profil Blogger belum dipilih.</strong><br>Pilih Profil Blogger terlebih dahulu.</div>'); return false; }
     if(!$('#jaf-label input:checked').length){ ensureWorkflow().html('<div class="jaf-inline-alert is-error"><strong>Label Blogger belum dipilih.</strong><br>Pilih minimal satu Label Blogger sebelum membuat artikel.</div>'); return false; }
   }
   return true;
 }

 function renderPreflight(data){
   var checks=Array.isArray(data&&data.checks)?data.checks:[], html='';
   $.each(checks,function(i,c){
     var ok=!!c.ok, cls=ok?'is-ok':'is-error', icon=ok?'✓':'!';
     var http=Number(c.http||0); html+='<div class="jaf-preflight-item '+cls+'"><span class="jaf-preflight-icon">'+icon+'</span><div class="jaf-preflight-copy"><strong>'+esc(c.label||'Pemeriksaan')+'</strong><span>'+esc(c.detail||'')+(http?' · HTTP '+http:'')+'</span></div></div>';
   });
   $('#jaf-preflight-checks').html(html);
   var ready=!!(data&&data.ready), msg=(data&&data.message)||'';
   $('#jaf-preflight-status').removeClass('is-idle is-running is-ok is-error').addClass(ready?'is-ok':'is-error').html('<strong>'+(ready?'Sistem siap':'Pemeriksaan belum lolos')+'</strong><span>'+esc(msg)+'</span>');
   JAF_PREFLIGHT_OK=ready;
   $('#jaf-preflight-run').prop('disabled',false).text('Periksa Lagi');
   syncGenerateButtonState();
 }
 function runPreflight(auto){
   var mat=String($('#jaf-material').val()||'').trim(), payload=preflightTargets();
   if(mat.length<30){JAF_PREFLIGHT_OK=false;return;}
   $('#jaf-preflight-card').show(); $('#jaf-preflight-run').prop('disabled',true).text('Memeriksa...');
   $('#jaf-preflight-status').removeClass('is-idle is-ok is-error').addClass('is-running').html('<strong>Memeriksa sistem...</strong><span>Menguji konfigurasi AI dan akses website tujuan sebelum proses dimulai.</span>');
   $('#jaf-preflight-checks').empty(); JAF_PREFLIGHT_OK=false; syncGenerateButtonState();
   $.post(JAF.ajax,$.extend({action:'jaf_preflight_check',nonce:JAF.nonce},payload),function(r){
     if(r&&r.success){renderPreflight(r.data||{});if(auto&&r.data&&r.data.ready){window.setTimeout(function(){autoScrollTo('#jaf-generate');},180);}}
     else {var m=r&&r.data&&r.data.message?r.data.message:'Pemeriksaan sistem gagal.';renderPreflight({ready:false,message:m,checks:[{label:'System Check',ok:false,detail:m}]});}
   }).fail(function(xhr){var m=xhr&&xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Server tidak merespons saat System Check.';renderPreflight({ready:false,message:m,checks:[{label:'System Check',ok:false,detail:m}]});});
 }
 function syncGenerateButtonState(){
   var b=$('#jaf-generate'); if(!b.length)return;
   var target=String($('#jaf-publish-target').val()||''), mat=String($('#jaf-material').val()||'').trim(), type=String($('#jaf-type').val()||'').trim();
   var validTarget=target==='wordpress'||target==='blogger'||target==='multi';
   b.prop('disabled',!(validTarget && type!=='' && mat.length>=30));
 }
 var JAF_DEVELOPER_RUN_ID='';
 var JAF_DEVELOPER_LOG=[];
 var JAF_DEVELOPER_LAST_SIG='';
 function developerEnabled(){ return masterEnabled('developer_mode_enabled',false); }
 function developerSetting(key,def){ return masterEnabled(key,def); }
 function developerBeginRun(){
   if(!developerEnabled())return;
   JAF_DEVELOPER_RUN_ID='JAF-'+Date.now().toString(36).toUpperCase()+'-'+Math.random().toString(36).slice(2,8).toUpperCase();
   JAF_DEVELOPER_LOG=[];JAF_DEVELOPER_LAST_SIG='';
   try{sessionStorage.setItem('jaf_developer_run_id',JAF_DEVELOPER_RUN_ID);sessionStorage.removeItem('jaf_developer_log');}catch(e){}
 }
 function developerRunId(){
   if(!developerEnabled()) return '';
   if(JAF_DEVELOPER_RUN_ID)return JAF_DEVELOPER_RUN_ID;
   try{JAF_DEVELOPER_RUN_ID=sessionStorage.getItem('jaf_developer_run_id')||'';}catch(e){}
   if(!JAF_DEVELOPER_RUN_ID){JAF_DEVELOPER_RUN_ID='JAF-'+Date.now().toString(36).toUpperCase()+'-'+Math.random().toString(36).slice(2,8).toUpperCase();try{sessionStorage.setItem('jaf_developer_run_id',JAF_DEVELOPER_RUN_ID);}catch(e){}}
   return JAF_DEVELOPER_RUN_ID;
 }
 function developerLoadLog(){
   if(!developerEnabled())return [];
   try{var raw=sessionStorage.getItem('jaf_developer_log');var a=raw?JSON.parse(raw):[];if($.isArray(a))JAF_DEVELOPER_LOG=a;}catch(e){JAF_DEVELOPER_LOG=[];}
   return JAF_DEVELOPER_LOG;
 }
 function developerSaveLog(){
   if(!developerEnabled())return;
   var max=Math.max(10,Math.min(100,Number(JAF_MASTER.developer_max_log_entries||50)));if(JAF_DEVELOPER_LOG.length>max)JAF_DEVELOPER_LOG=JAF_DEVELOPER_LOG.slice(-max);
   try{sessionStorage.setItem('jaf_developer_log',JSON.stringify(JAF_DEVELOPER_LOG));}catch(e){}
 }
 function developerLog(entry){
   if(!developerEnabled())return;
   entry=entry||{};var e=$.extend({timestamp:new Date().toISOString(),run_id:developerRunId()},entry);
   var sig=[e.source,e.job_id,e.target,e.stage,e.state,e.retry_count,e.http,e.message].join('|');
   if(sig===JAF_DEVELOPER_LAST_SIG)return;
   JAF_DEVELOPER_LAST_SIG=sig;developerLoadLog();JAF_DEVELOPER_LOG.push(e);developerSaveLog();renderDeveloperPanel(e);
 }
 function developerClearLog(){
   JAF_DEVELOPER_LOG=[];JAF_DEVELOPER_LAST_SIG='';try{sessionStorage.removeItem('jaf_developer_log');}catch(e){}renderDeveloperPanel({});
 }
 function developerCopy(){
   if(!developerEnabled())return;
   developerLoadLog();var text=JSON.stringify({run_id:developerRunId(),log:JAF_DEVELOPER_LOG},null,2);
   var ok=function(){var b=$('.jaf-developer-copy');b.text('✓ Tersalin');setTimeout(function(){b.text('Copy Diagnostic');},1200);};
   if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(text).then(ok,function(){developerCopyFallback(text,ok);});else developerCopyFallback(text,ok);
 }
 function developerCopyFallback(text,ok){try{var ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();ok();}catch(e){alert('Diagnostic tidak dapat disalin pada browser ini.');}}
 function renderDeveloperPanel(latest){
   if(!developerEnabled()){$('.jaf-developer-panel').remove();return;}
   latest=latest||{};developerLoadLog();var fields=[];
   function add(key,label,val){if(developerSetting(key,true)&&String(val||'')!=='')fields.push('<div><strong>'+esc(label)+'</strong><span>'+esc(String(val))+'</span></div>');}
   add('developer_show_run_id','Workflow Run ID',developerRunId());add('developer_show_job_id','Job ID',latest.job_id||'—');add('developer_show_target','Target',latest.target||'—');add('developer_show_stage','Current Stage',latest.stage||'—');add('developer_show_retry_count','Retry Count',latest.retry_count==null?'0':latest.retry_count);add('developer_show_http','HTTP Status',latest.http||'—');add('developer_show_timestamp','Timestamp',latest.timestamp||new Date().toISOString());add('developer_show_job_state','Job State',latest.state||'—');
   var diag=latest.message||latest.diagnostic||'';
   if(developerSetting('developer_show_diagnostic',true)&&diag)fields.push('<div class="jaf-developer-wide"><strong>Diagnostic Detail</strong><pre>'+esc(typeof diag==='string'?diag:JSON.stringify(diag,null,2))+'</pre></div>');
   var html='<div class="jaf-developer-panel"><div class="jaf-developer-head"><div><span class="jaf-result-kicker">DEVELOPER MODE</span><h3>Advanced Diagnostics</h3><p>Read-only technical state. Tidak mengubah workflow atau data server.</p></div><span class="jaf-diagnostic-badge">'+JAF_DEVELOPER_LOG.length+' log</span></div><div class="jaf-developer-grid">'+fields.join('')+'</div>';
   if(JAF_DEVELOPER_LOG.length){html+='<details class="jaf-developer-log"><summary>Developer Log</summary><pre>'+esc(JSON.stringify(JAF_DEVELOPER_LOG,null,2))+'</pre></details>';}
   html+='<div class="jaf-developer-actions">'+(developerSetting('developer_copy_button',true)?'<button type="button" class="button jaf-developer-copy">Copy Diagnostic</button>':'')+'<button type="button" class="button jaf-developer-clear">Clear/Reset Temporary Diagnostics</button></div></div>';
   if(!$('.jaf-developer-panel').length)$('#jaf-result').append(html);else $('.jaf-developer-panel').replaceWith(html);
 }
 $(document).on('click','.jaf-developer-copy',developerCopy);
 $(document).on('click','.jaf-developer-clear',function(){if(window.confirm('Hapus Developer Log sementara pada browser ini?'))developerClearLog();});
 function developerWorkflow(stage,target,extra){extra=extra||{};developerLog($.extend({source:'workflow',stage:String(stage||'').toUpperCase(),target:target||'',state:extra.state||'running',retry_count:extra.retry_count||0,http:extra.http||0,message:extra.message||''},extra));}
 function publicationDiagnosticCard(data){
   if(!masterEnabled('diagnostic_enabled',true)) return '';
   data=data||{}; var stage=data.stage||'PUBLISH', http=Number(data.http||0), target=data.target||'', message=data.message||'Publikasi gagal.', level=diagnosticLevel();
   var showStage=masterDiagnostic('diagnostic_show_stage',true), showHttp=masterDiagnostic('diagnostic_show_http',true), showTarget=masterDiagnostic('diagnostic_show_target',true), showMessage=masterDiagnostic('diagnostic_show_message',true);
   var body='';
   if(showTarget&&target) body+='<div><strong>Website</strong><span>'+esc(target)+'</span></div>';
   if(showHttp&&http&&level!=='basic') body+='<div><strong>HTTP</strong><span>'+esc(String(http))+'</span></div>';
   if(showStage) body+='<div><strong>Tahap</strong><span>'+esc(stage)+'</span></div>';
   if(showMessage) body+='<div class="jaf-diagnostic-message"><strong>Pesan</strong><span>'+esc(message)+'</span></div>';
   return '<div class="jaf-publication-diagnostics is-error"><div class="jaf-diagnostic-head"><div><span class="jaf-result-kicker">PUBLICATION DIAGNOSTICS</span><h3>Publikasi gagal</h3></div>'+(showStage?'<span class="jaf-diagnostic-badge">'+esc(stage)+'</span>':'')+'</div><div class="jaf-diagnostic-grid">'+body+'</div></div>';
 }

 function resetButtons(){ JAF_PROCESS_LOCK=false; $('#jaf-generate').prop('disabled',false); $('#jaf-material,#jaf-url,#jaf-extract-url,#jaf-extract-material,#jaf-wp-profile').prop('disabled',false); $('#jaf-category input,#jaf-tags input,#jaf-label input,#jaf-type,#jaf-location,#jaf-language,#jaf-publish-target').prop('disabled',false); $('#jaf-multi-targets input').prop('disabled',false); }
 function syncLanguagePicker(){ var val=$('#jaf-language').val()||'id'; $('#jaf-language-picker .jaf-language-option').removeClass('active').filter('[data-language="'+val+'"]').addClass('active'); }
 $(document).on('click','.jaf-language-option',function(){ var val=$(this).data('language')||'id'; $('#jaf-language').val(val).trigger('change'); syncLanguagePicker(); });
 $(document).on('change','#jaf-language',syncLanguagePicker);
 function syncGenerateButtonLabel(){
   var target=$('#jaf-publish-target').val()||'', $b=$('#jaf-generate'); if(!$b.length)return;
   if(target==='multi'){ $b.html('<span class="dashicons dashicons-upload" aria-hidden="true"></span> Buat &amp; Publish ke Wordpress + Blogger'); return; }
   if($('#jaf-background-mode').prop('checked') && masterEnabled('background_button_label',true)){ $b.html('<span class="dashicons dashicons-upload" aria-hidden="true"></span> Buat &amp; Publikasikan'); }
   else { $b.html('<span class="dashicons dashicons-edit" aria-hidden="true"></span> Buat Artikel dan Gambar'); }
 }
 $(document).on('change','#jaf-background-mode',function(){ var bg=$(this).prop('checked'); $('#jaf-wake-lock').prop('disabled',bg); if(bg) releaseWakeLock(); syncGenerateButtonLabel(); });
 $(document).on('change','#jaf-wake-lock',function(){ if($(this).prop('checked')&&!$('#jaf-background-mode').prop('checked')&&progressTimer) acquireWakeLock(); else if(!$(this).prop('checked')) releaseWakeLock(); });
 window.setTimeout(resumeBackgroundJob,350);
 let sourceMode='manual';
 // Metode Input: tombol hanya mengganti panel sumber, tidak menjalankan submit/form.
 $(document).on('click','.jwp-source-tab',function(e){
   e.preventDefault();
   var mode=String($(this).data('source')||'manual');
   if(mode!=='manual' && mode!=='url') mode='manual';
   sourceMode=mode;
   $('.jwp-source-tab').removeClass('active button-primary');
   $(this).addClass('active button-primary');
   $('#jaf-source-manual').toggle(mode==='manual');
   $('#jaf-source-url').toggle(mode==='url');
   if(mode==='url') $('#jaf-url').trigger('focus');
   else $('#jaf-material').trigger('focus');
 });
 let progressTimer=null;
 let progressValue=0;
 let progressTarget=0;
 let workflowRunKey='';
 let jafWakeLock=null;
 let jafBgPollTimer=null;
 function wakeLockEnabled(){ return $('#jaf-wake-lock').length ? $('#jaf-wake-lock').prop('checked') : false; }
 async function acquireWakeLock(){ if($('#jaf-background-mode').prop('checked') || !wakeLockEnabled() || !('wakeLock' in navigator)) return; try{ if(jafWakeLock && !jafWakeLock.released)return; jafWakeLock=await navigator.wakeLock.request('screen'); jafWakeLock.addEventListener('release',function(){jafWakeLock=null;}); }catch(e){} }
 async function releaseWakeLock(){ try{ if(jafWakeLock) await jafWakeLock.release(); }catch(e){} jafWakeLock=null; }
 document.addEventListener('visibilitychange',function(){ if(document.visibilityState==='visible' && progressTimer && !$('#jaf-background-mode').prop('checked')) acquireWakeLock(); });

 function ensureWorkflow(){
   return $('#jaf-workflow');
 }
 function setWorkflowSticky(enabled){
   var $w=ensureWorkflow();
   if(!$w.length)return;
   $w.toggleClass('jaf-process-sticky',!!enabled);
 }
 function autoScrollTo(target,extraOffset){
   var $target=$(target).first();
   if(!$target.length)return;
   var topBar=0, $wpbar=$('#wpadminbar');
   if($wpbar.length) topBar=Math.max(0,$wpbar.outerHeight()||0);
   var $workflow=ensureWorkflow();
   var workflowOffset=($workflow.hasClass('jaf-process-sticky') ? ($workflow.outerHeight()||0)+14 : 18);
   var offset=topBar+workflowOffset+(parseInt(extraOffset,10)||0);
   var top=Math.max(0,Math.round($target.offset().top-offset));
   window.requestAnimationFrame(function(){
     window.scrollTo({top:top,behavior:'smooth'});
   });
 }
 function clearProgressTimer(){ if(progressTimer){clearInterval(progressTimer);progressTimer=null;} }
 function normalizeWorkflowStage(active){
   active=String(active||'').toLowerCase();
   var map={extract:'extract',article:'article',thumb:'thumb',thumbnail:'thumb',publish:'publish',ready_publish:'publish',done:'publish',failed:'publish'};
   return map[active]||'article';
 }
 function updateWorkflowProgressDom(title,detail){
   var $w=ensureWorkflow(); if(!$w.length)return;
   var pct=Math.round(progressValue);
   $w.find('.jaf-workflow-head h3').text(title||'');
   $w.find('.jaf-workflow-head p').text(detail||'');
   $w.find('.jaf-progress-ring').css('--jaf-progress',pct+'%').find('span').text(pct+'%');
   $w.find('.jaf-progress-track span').css('width',pct+'%');
 }
 function workflowMarkup(active,percent,title,detail){
   active=normalizeWorkflowStage(active);
   var allStages=[['extract','Ekstrak','stage_show_extract'],['article','Artikel','stage_show_article'],['thumb','Thumbnail','stage_show_thumbnail'],['publish','Terbitkan','stage_show_publish']];
   var useStageController=masterEnabled('stage_controller_enabled',true);
   var stages=$.grep(allStages,function(st){return !useStageController||masterEnabled(st[2],true);});
   if(!stages.length) stages=[['publish','Terbitkan','stage_show_publish']];
   var activeIndex=-1;
   $.each(stages,function(i,st){if(st[0]===active)activeIndex=i;});
   if(activeIndex<0){
     var fallbackLabel=active==='extract'?'Ekstrak':(active==='article'?'Artikel':(active==='thumb'?'Thumbnail':'Terbitkan'));
     stages.push([active,fallbackLabel,'']);
     activeIndex=stages.length-1;
   }
   var html='<div class="jaf-workflow-card is-running">'+
     '<div class="jaf-workflow-head"><div><div class="jaf-eyebrow">PROSES WORKFLOW</div><h3>'+esc(title)+'</h3><p>'+esc(detail)+'</p></div><div class="jaf-progress-ring" style="--jaf-progress:'+percent+'%"><span>'+percent+'%</span></div></div>'+
     '<div class="jaf-progress-track"><span style="width:'+percent+'%"></span></div>'+
     '<div class="jaf-steps">';
   $.each(stages,function(i,st){
     var cls=i<activeIndex?'is-done':(i===activeIndex?'is-active':'');
     html+='<div class="jaf-step '+cls+'"><span class="jaf-step-dot">'+(i<activeIndex?'✓':(i===activeIndex?'':'') )+'</span><span>'+st[1]+'</span></div>';
   });
   html+='</div><div class="jaf-workflow-controls"><button type="button" class="button jaf-stop-background" style="display:none">⏹ Hentikan Proses</button></div></div>';
   return html;
 }
 function startWorkflow(active,title,detail,start,max){
   active=normalizeWorkflowStage(active);
   var startValue=Number(start||8), maxValue=Number(max||88);
   var runKey=active+'|'+String(title||'');
   var $w=ensureWorkflow();
   // Background polling may report the same stage repeatedly. Never rebuild the card or reset its animation.
   if(workflowRunKey===runKey && $w.find('.jaf-workflow-card.is-running').length){
     progressValue=Math.max(progressValue,startValue);
     progressTarget=Math.max(progressTarget,maxValue,startValue);
     updateWorkflowProgressDom(title,detail);
     syncBackgroundStopButton();
     if(!progressTimer){
       progressTimer=setInterval(function(){
         if(progressValue<progressTarget){
           var step=progressValue<35?3:(progressValue<65?2:1);
           progressValue=Math.min(progressTarget,progressValue+step);
           updateWorkflowProgressDom(title,detail);
         }
       },520);
     }
     return;
   }
   clearProgressTimer();
   acquireWakeLock();
   var developerIsNewRun=!workflowRunKey;
   workflowRunKey=runKey;
   if(developerIsNewRun)developerBeginRun();
   developerWorkflow(active,'', {state:'running',message:detail||title});
   progressValue=startValue;
   progressTarget=Math.max(maxValue,startValue);
   $w.show();
   setWorkflowSticky(true);
   function render(){ $w.html(workflowMarkup(active,Math.round(progressValue),title,detail)); syncBackgroundStopButton(); }
   render();
   progressTimer=setInterval(function(){
     if(progressValue<progressTarget){
       var step=progressValue<35?3:(progressValue<65?2:1);
       progressValue=Math.min(progressTarget,progressValue+step);
       render();
     }
   },520);
 }
 function completeWorkflow(active,title,detail){
   developerWorkflow(active,'',{state:'done',message:detail||title});
   clearProgressTimer(); progressValue=100; progressTarget=100; workflowRunKey='';
   var $w=ensureWorkflow();
   $w.html(workflowMarkup(active,100,title,detail).replace('is-running','is-complete'));
   if(active==='publish'){
     setWorkflowSticky(false);
     releaseWakeLock();
     $w.stop(true,true).fadeOut(160);
   }
 }
 function failWorkflow(active,title,detail,errorText){
   developerWorkflow(active,'',{state:'failed',message:errorText||detail||'Proses gagal.'});
   clearProgressTimer();
   workflowRunKey=''; progressTarget=progressValue;
   releaseWakeLock();
   var $w=ensureWorkflow();
   var base=workflowMarkup(active,Math.min(progressValue,99),title,detail).replace('is-running','is-error');
   base=base.replace('</div></div>','<div class="jaf-workflow-error">'+esc(errorText||'Proses gagal. Silakan periksa pengaturan dan coba lagi.')+'</div></div></div>');
   $w.html(base);
 }
 function resultShell(){
   if(!$('#jaf-result').length)return;
   $('#jaf-result').html('<div class="jaf-result-workspace"><div class="jaf-result-grid"></div><div class="jaf-publish-card" id="jaf-publish-card" style="display:none"></div></div>');
 }
 function articleCard(f,payload){
   return '<section class="jaf-result-card jaf-article-card"><div class="jaf-result-card-head"><div><span class="jaf-result-kicker">HASIL ARTIKEL</span><h3>'+esc(f.title||'Artikel selesai')+'</h3></div><span class="jaf-result-badge">SIAP</span></div><div class="jaf-result-meta"><div><strong>Focus Keyphrase</strong><span>'+esc(f.focus_keyword||'-')+'</span></div><div><strong>Deskripsi</strong><span>'+esc(f.description||'-')+'</span></div></div><div class="jaf-content-frame"><div class="jaf-content-label">Isi Artikel</div><div class="jaf-content-copy">Artikel berhasil dibuat dan siap diproses ke tahap thumbnail.</div><details><summary>Lihat format field Javanese</summary><textarea class="widefat" rows="14" readonly>'+esc(payload||'')+'</textarea></details></div></section>';
 }
 function thumbCard(img,target){
   var action=target==='blogger'?'Upload ke Blogger':'Terapkan & Publish';
   return '<section class="jaf-result-card jaf-thumb-card"><div class="jaf-result-card-head"><div><span class="jaf-result-kicker">HASIL THUMBNAIL</span><h3>Gambar siap digunakan</h3></div><span class="jaf-result-badge">16:9</span></div><div class="jaf-image-frame">'+(img?'<img src="'+esc(img)+'" alt="">':'<div class="jaf-image-empty">Thumbnail selesai diproses.</div>')+'</div><div class="jaf-result-foot"><span>Thumbnail berhasil dibuat.</span><button type="button" id="jaf-apply" class="button button-primary jaf-publish-action"><span class="dashicons dashicons-upload" aria-hidden="true"></span> '+action+'</button></div></section>';
 }
 function publishCard(target,label,detail){
   return '<div class="jaf-publish-inner"><div><span class="jaf-result-kicker">PUBLIKASI</span><h3>'+esc(label)+'</h3><p>'+esc(detail)+'</p></div><span class="jaf-publish-target">'+(target==='blogger'?'Blogger':'WordPress')+'</span></div>';
 }
 function publicationSuccessCard(target,url){
   var open=url ? '<a class="button button-primary jaf-open-published" target="_blank" rel="noopener noreferrer" href="'+esc(url)+'">Lihat Artikel</a>' : '';
   return '<div class="jaf-publish-success"><div class="jaf-success-mark">✓</div><div class="jaf-publish-success-copy"><span class="jaf-result-kicker">PUBLIKASI BERHASIL</span><h3>Artikel berhasil diterbitkan</h3><p>Artikel dan thumbnail sudah tersedia di '+(target==='blogger'?'Blogger':'WordPress')+'.</p><div class="jaf-publish-actions">'+open+'<button type="button" class="button button-primary jaf-rebuild">Buat Ulang</button><button type="button" class="button jaf-close-success">Tutup</button></div></div></div>';
 }
 function hideWorkflowAfterSinglePublish(target,background){
   target=String(target||'wordpress').toLowerCase();
   if(target==='wp') target='wordpress';
   if(target!=='wordpress' && target!=='blogger') return;
   var mode=background?'background':'foreground';
   var key='single_'+(target==='blogger'?'blogger':'wp')+'_'+mode+'_hide_workflow_after_success';
   var enabled=masterEnabled(key,false);
   var $card=$('#jaf-next-action-card');
   if(!$card.length) return;
   // Server-rendered attributes are the authoritative fallback for all 4 combinations.
   if(String($card.attr('data-'+key)||'')==='1') enabled=true;
   if(!enabled) return;
   // “Alur kerja” is the footer card, not the progress card #jaf-workflow.
   $card.stop(true,true).hide().attr('aria-hidden','true').addClass('jaf-hidden-after-publish');
 }
 function bgItemsPayload(){
   var items=[], list=Array.isArray(window.JAF_MULTI_TARGETS)?window.JAF_MULTI_TARGETS:[];
   var checked=$('#jaf-multi-targets input.jaf-multi-target-check:checked').map(function(){return String($(this).val()||'');}).get();
   $.each(checked,function(i,key){ var item=multiTargetByKey(key); if(!item)return; items.push({
     key:item.key,name:item.name,platform:item.platform,target:item.target,wordpress_profile:item.wordpress_profile||'',blogger_profile:item.blogger_profile||'',language:item.language||'id',
     categories:multiCategoriesFor(item),tags:multiTagsFor(item),labels:multiLabelsFor(item)
   }); });
   return items;
 }
 function bgStore(id){ try{ if(id)localStorage.setItem('jaf_active_background_job',id); else localStorage.removeItem('jaf_active_background_job'); }catch(e){} }
 function bgLoad(){ try{return localStorage.getItem('jaf_active_background_job')||'';}catch(e){return '';} }
 function syncBackgroundStopButton(){
   var id=bgLoad(); var show=!!id && !$('#jaf-workflow .jaf-workflow-card').hasClass('is-complete');
   $('#jaf-workflow .jaf-stop-background').toggle(show);
 }
 function stopBackground(id){
   id=id||bgLoad(); if(!id)return;
   if(!window.confirm('Hentikan proses background ini? Proses yang belum selesai tidak akan dilanjutkan.'))return;
   $('.jaf-stop-background').prop('disabled',true).text('Menghentikan...');
   $.post(JAF.ajax,{action:'jaf_cancel_background',nonce:JAF.nonce,job_id:id},function(r){
     if(r&&r.success){ clearInterval(jafBgPollTimer); jafBgPollTimer=null; bgStore(''); releaseWakeLock(); clearProgressTimer(); var job=r.data||{}; var $w=ensureWorkflow(); $w.show().removeClass('is-sticky'); $w.html('<div class="jaf-workflow-card is-error"><div class="jaf-workflow-head"><div><div class="jaf-eyebrow">PROSES DIHENTIKAN</div><h3>Proses dihentikan</h3><p>'+esc(job.message||'Proses dihentikan oleh pengguna.')+'</p></div></div></div>'); setWorkflowSticky(false); resetButtons();
     } else { $('.jaf-stop-background').prop('disabled',false).text('⏹ Hentikan Proses'); alert((r&&r.data&&r.data.message)||'Proses tidak dapat dihentikan.'); }
   }).fail(function(xhr){ $('.jaf-stop-background').prop('disabled',false).text('⏹ Hentikan Proses'); var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; alert(d.message||'Server tidak merespons saat menghentikan proses.'); });
 }
 $(document).on('click','.jaf-stop-background',function(){ stopBackground(bgLoad()); });
 function backgroundStart(target){
   var payload={action:'jaf_start_background',nonce:JAF.nonce,target:target,material:$('#jaf-material').val().trim(),type:$('#jaf-type').val(),location:$('#jaf-location').val(),language:$('#jaf-language').val()||'id',blogger_profile:$('#jaf-blogger-profile').val()||'',wordpress_profile:$('#jaf-wp-profile').val()||'',categories:$('#jaf-category input:checked').map(function(){return $(this).val();}).get(),tags:$('#jaf-tags input:checked').map(function(){return $(this).val();}).get(),labels:$('#jaf-label input:checked').map(function(){return $(this).val();}).get()};
   if(target==='multi'){ var items=bgItemsPayload(); if(!items.length){ensureWorkflow().html('<div class="jaf-inline-alert is-error">Pilih minimal satu website atau blog tujuan.</div>');return;} payload.items_json=JSON.stringify(items); }
   $('#jaf-generate').prop('disabled',true); $('#jaf-material,#jaf-url,#jaf-category input,#jaf-tags input,#jaf-label input,#jaf-type,#jaf-location,#jaf-language,#jaf-publish-target,#jaf-extract-url,#jaf-extract-material,#jaf-wp-profile,#jaf-blogger-profile,#jaf-multi-targets input').prop('disabled',true);
   setWorkflowSticky(true); startWorkflow('article','Proses background dimulai','Server akan melanjutkan proses meskipun halaman ditinggalkan.',3,target==='multi'?96:52);
   $.post(JAF.ajax,payload,function(r){
     if(!r.success){ failWorkflow('article','Background gagal dimulai','Job tidak berhasil dibuat.',r.data&&r.data.message?r.data.message:'Gagal membuat background job.'); resetButtons(); return; }
     var id=r.data.job_id; bgStore(id); if(target==='multi'){ window.JAF_LAST_MULTI_JOB_ID=id; try{localStorage.setItem('jaf_last_multi_job',id);}catch(e){} } pollBackground(id);
   }).fail(function(xhr){var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; failWorkflow('article','Background gagal dimulai','Server tidak merespons.',d.message||'Gagal membuat background job.'); resetButtons();});
 }
 function backgroundPublish(id,b){
   if(!id)return;
   b.prop('disabled',true); startWorkflow('publish','Mempublikasikan di background','Server akan menerbitkan artikel tanpa menahan halaman.',88,96);
   $.post(JAF.ajax,{action:'jaf_start_background_publish',nonce:JAF.nonce,job_id:id},function(r){
     if(!r.success){ failWorkflow('publish','Publikasi background gagal','Job belum dijalankan.',r.data&&r.data.message?r.data.message:'Gagal memulai publikasi background.'); b.prop('disabled',false); return; }
     pollBackground(id);
   }).fail(function(xhr){var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; failWorkflow('publish','Publikasi background gagal','Server tidak merespons.',d.message||'Gagal memulai publikasi background.');b.prop('disabled',false);});
 }
 function renderBackgroundSingle(job){
   if(job.target && $('#jaf-publish-target').val()!==job.target){ $('#jaf-publish-target').val(job.target); syncTargetUI(); }
   var stage=String(job.stage||'ARTICLE').toUpperCase(), pct=Number(job.percent||0);
   var active=normalizeWorkflowStage(stage);
   if(job.status==='running'||job.status==='queued'||job.status==='ready_publish'){
     var title=stage==='READY_PUBLISH'?'Artikel siap dipublikasikan':'Background: '+(stage==='THUMBNAIL'?'Membuat thumbnail':(stage==='PUBLISH'?'Mempublikasikan':'Memproses artikel'));
     var cap=stage==='ARTICLE'?50:(stage==='THUMBNAIL'?80:(stage==='PUBLISH'?96:86));
     startWorkflow(active,title,job.message||'Proses berjalan di server.',Math.max(3,pct),Math.max(cap,pct));
   }
   var res=job.result||{};
   if(stage==='READY_PUBLISH' && res.fields){
     $('#jaf-result').html('<div class="jaf-result-workspace"><div class="jaf-result-grid"></div><div class="jaf-publish-card" id="jaf-publish-card"></div></div>');
     $('.jaf-result-grid').append(articleCard(res.fields,res.payload||''));
     $('.jaf-result-grid').append(thumbCard(res.image_url||'','wordpress'));
     $('#jaf-apply').data('job',job.article_job_token||'').attr('data-background-job',job.id);
     $('#jaf-publish-card').show().html(publishCard(job.target,job.target==='blogger'?'Siap dikirim ke Blogger':'Siap diterapkan ke WordPress','Artikel dan thumbnail selesai. Publikasi dapat dilanjutkan di background.'));
     resetButtons(); $('#jaf-generate').prop('disabled',true); setWorkflowSticky(true); return;
   }
   if(stage==='DONE'){
     completeWorkflow('publish','Proses selesai',job.message||'Artikel berhasil dipublikasikan.');
     var target=job.target==='blogger'?'Blogger':'WordPress', url=res.url||'';
     $('#jaf-result').html('<div class="jaf-result-workspace"><div class="jaf-result-grid"></div></div>');
     if(res.fields) $('.jaf-result-grid').append(articleCard(res.fields,res.payload||''));
     if(res.image_url && !masterEnabled('single_hide_thumbnail_after_success',true)) $('.jaf-result-grid').append(thumbCard(res.image_url,target));
     $('.jaf-result-grid').append(publicationSuccessCard(job.target,url));
     if(masterEnabled('single_hide_publish_after_success',true)) $('#jaf-apply').remove();
     hideWorkflowAfterSinglePublish(job.target,true);
     bgStore(''); resetButtons(); return;
   }
   if(job.status==='failed'){
     var d=(job.diagnostics&&job.diagnostics.length)?job.diagnostics[job.diagnostics.length-1]:{}; failWorkflow((job.stage||'publish').toLowerCase(),'Proses background gagal',job.message||'Proses gagal.',d.message||job.message||'Proses gagal.');
     $('#jaf-publish-card').show().html(publicationDiagnosticCard({stage:d.stage||job.stage,http:d.http||0,target:d.target||'',message:d.message||job.message||'Proses gagal.'})); resetButtons(); bgStore(''); return;
   }
 }
 function renderBackgroundMulti(job){
   var items=Array.isArray(job.items)?job.items:[]; renderMultiProgress(items); $.each(items,function(i,item){var st=item.status==='success'?'success':(item.status==='failed'?'error':(i===Number(job.index||0)?'running':''));var label=item.status==='success'?'Sukses':(item.status==='failed'?'Gagal':(i===Number(job.index||0)?'Berjalan':'Menunggu'));updateMultiProgress(item,st,label,item.message||'');});
   var done=0,failed=0;$.each(items,function(i,it){if(it.status==='success')done++;if(it.status==='failed')failed++;});
   $('#jaf-multi-summary').text(job.status==='done'?('Selesai: '+done+' berhasil'+(failed?' • '+failed+' gagal.':' • semua berhasil.')):('Background: '+done+' berhasil'+(failed?' • '+failed+' gagal':'')+' • '+items.length+' tujuan.'));
   if(job.status==='done'){
     completeWorkflow('publish','Batch selesai',job.message||'Batch background selesai.');
     var results=[];$.each(items,function(i,it){if(it.status==='success')results.push({name:it.name,url:it.url||''});});
     $('#jaf-result').append(multiBatchSuccessCard(results,done,failed));
     if(failed&&job.diagnostics&&job.diagnostics.length&&masterEnabled('diagnostic_enabled',true)){var seenDiag={};var dh='<div class="jaf-publication-diagnostics jaf-multi-diagnostics"><div class="jaf-diagnostic-head"><div><span class="jaf-result-kicker">PUBLICATION DIAGNOSTICS</span><h3>Detail kegagalan batch</h3></div>'+(masterDiagnostic('diagnostic_show_stage',true)?'<span class="jaf-diagnostic-badge">'+failed+' gagal</span>':'')+'</div><div class="jaf-diagnostic-list">';$.each(job.diagnostics,function(i,d){var diagKey=String(d.target||'')+'|'+String(d.stage||'')+'|'+String(d.message||'');if(seenDiag[diagKey])return;seenDiag[diagKey]=true;var retryKey='';$.each(items,function(k,it){if(String(it.name||'')===String(d.target||''))retryKey=String(it.key||'');});var meta='';if(masterDiagnostic('diagnostic_show_stage',true))meta+=esc(d.stage||'PUBLISH');if(masterDiagnostic('diagnostic_show_http',true)&&d.http&&diagnosticLevel()!=='basic')meta+=(meta?' · ':'')+'HTTP '+esc(String(d.http));dh+='<div class="jaf-diagnostic-row">'+(masterDiagnostic('diagnostic_show_target',true)?'<strong>'+esc(d.target||'Website')+'</strong>':'')+(meta?'<span>'+meta+'</span>':'')+(masterDiagnostic('diagnostic_show_message',true)?'<em>'+esc(d.message||'Proses gagal.')+'</em>':'')+((masterDiagnostic('diagnostic_show_retry',true)&&retryKey&&masterEnabled('retry_enabled',true))?'<button type="button" class="button jaf-multi-retry" data-retry-key="'+esc(retryKey)+'" data-retry-job="'+esc(job.id||window.JAF_LAST_MULTI_JOB_ID||'')+'">↻ Retry</button>':'')+'</div>';});dh+='</div></div>';$('#jaf-result').append(dh);} resetButtons();if(failed){window.JAF_LAST_MULTI_JOB_ID=job.id||window.JAF_LAST_MULTI_JOB_ID;} bgStore('');return;
   }
   setWorkflowSticky(true);
   // Pertahankan model progress Multi lama. Hanya bedakan run key per website/tahap
   // agar polling pada website berikutnya tidak mewarisi animasi website sebelumnya.
   var activeIndex=Math.max(0,Math.min(items.length-1,Number(job.index||0)));
   var activeStage=normalizeWorkflowStage(job.stage||'ARTICLE');
   var devItem=items[activeIndex]||{}; developerLog({source:'background',job_id:job.id||'',target:devItem.name||job.target||'',stage:activeStage,state:job.status||'running',retry_count:devItem.retry_count||0,http:job.http||0,message:job.message||''});
   var activeName=(items[activeIndex]&&items[activeIndex].name)?items[activeIndex].name:'Website '+(activeIndex+1);
   var bgTitle='Background Multi Website '+(activeIndex+1)+'/'+Math.max(1,items.length);
   var bgDetail='Memproses '+activeName+' — '+String(job.stage||'ARTICLE').toUpperCase()+'.';
   startWorkflow(activeStage,bgTitle,bgDetail,Number(job.percent||3),activeStage==='publish'?96:(activeStage==='thumb'?82:52));
 }
 $(document).on('click','.jaf-multi-retry',function(){
   var b=$(this), id=String(b.attr('data-retry-job')||window.JAF_LAST_MULTI_JOB_ID||''); var key=String(b.attr('data-retry-key')||''); if(!id||!key)return;
   b.prop('disabled',true).text('Menyiapkan...');
   developerLog({source:'background-retry',job_id:id,target:key,stage:'RETRY',state:'starting',retry_count:1,message:'Retry dimulai.'});
   $.post(JAF.ajax,{action:'jaf_retry_multi_item',nonce:JAF.nonce,job_id:id,key:key,type:$('#jaf-type').val()||'',location:$('#jaf-location').val()||'',language:$('#jaf-language').val()||'id'},function(r){
     if(r&&r.success){ developerLog({source:'background-retry',job_id:id,target:key,stage:'RETRY',state:'running',retry_count:1,message:'Retry dijalankan.'}); try{localStorage.setItem('jaf_active_background_job',id);}catch(e){} b.text('Retry dijalankan'); pollBackground(id); }
     else { developerLog({source:'background-retry',job_id:id,target:key,stage:'RETRY',state:'failed',retry_count:1,http:0,message:(r&&r.data&&r.data.message)||'Retry gagal dijalankan.'}); b.prop('disabled',false).text('↻ Retry'); alert((r&&r.data&&r.data.message)||'Retry gagal dijalankan.'); }
   }).fail(function(xhr){b.prop('disabled',false).text('↻ Retry');var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{};alert(d.message||'Server tidak merespons saat retry.');});
 });
 function pollBackground(id){
   if(!id)return; if(jafBgPollTimer)clearInterval(jafBgPollTimer);
   function tick(){ $.post(JAF.ajax,{action:'jaf_background_status',nonce:JAF.nonce,job_id:id},function(r){if(!r.success)return;var job=r.data||{};if(job.mode==='multi')renderBackgroundMulti(job);else renderBackgroundSingle(job);syncBackgroundStopButton();if(['done','failed','cancelled'].indexOf(job.status)!==-1){clearInterval(jafBgPollTimer);jafBgPollTimer=null;}}); }
   tick(); jafBgPollTimer=setInterval(tick,1800);
 }
 function resumeBackgroundJob(){ if(!masterEnabled('background_resume_after_reload',true)) return; var id=bgLoad(); if(id)pollBackground(id); }
 function resetResultForNewRun(){
  if(developerEnabled()){developerBeginRun();renderDeveloperPanel({state:'reset',message:'Workflow baru disiapkan.'});}
  // Reset hanya state artikel/hasil. Konfigurasi global dan koneksi tetap.
  $('#jaf-next-action-card').stop(true,true).show();
  workflowRunKey=''; progressTarget=0;
  $('#jaf-extract-preview').remove();
  $('#jaf-publish-target').val('');
  $('#jaf-wp-profile').val('');
  $('#jaf-blogger-profile').prop('selectedIndex',0);
  $('#jaf-type').val('');
  $('#jaf-multi-targets input').prop('checked',false);
  $('#jaf-category input,#jaf-tags input,#jaf-label input').prop('checked',false);
  $('#jaf-material,#jaf-url').val('');
  $('#jaf-extract-status').empty();
  $('#jaf-location').val('Jakarta');
  $('#jaf-language').val('id').trigger('change');
  sourceMode='manual';
  $('.jwp-source-tab').removeClass('active button-primary');
  $('.jwp-source-tab[data-source="manual"]').addClass('active button-primary');
  $('#jaf-source-manual').show();
  $('#jaf-source-url').hide();
  resetButtons();
  $('#jaf-blogger-profile').prop('disabled',false);
  syncLanguagePicker();
  syncTargetUI();
  syncTargetContext();
  var $defaultCategory=$('#jaf-category input').filter(function(){return String($(this).val()||'').trim().toLowerCase()==='berita';}).first();
  if($defaultCategory.length)$defaultCategory.prop('checked',true); else $('#jaf-category input').first().prop('checked',true);

  if(masterEnabled('reset_preflight_on_rebuild',true)){ JAF_PREFLIGHT_OK=false; $('#jaf-preflight-card').hide(); $('#jaf-preflight-checks').empty(); $('#jaf-preflight-status').removeClass('is-running is-ok is-error').addClass('is-idle').html('<strong>Belum diperiksa.</strong><span>Setelah materi siap, jalankan pemeriksaan sistem.</span>'); }

  // Hapus SEMUA sisa visual sesi sebelumnya, termasuk workflow selesai.
  $('.jaf-publish-success,.jaf-article-card,.jaf-thumb-card').remove();
  $('#jaf-result').empty();
  var $workflow=ensureWorkflow();
  if($workflow.length){
    setWorkflowSticky(false);
    $workflow.stop(true,true).hide().empty();
  }

  // Sesi baru dimulai dari atas dan fokus ke tujuan publish.
  window.setTimeout(function(){
    window.scrollTo({top:0,left:0,behavior:'smooth'});
    window.setTimeout(function(){ $('#jaf-publish-target').trigger('focus'); },350);
  },30);
}
 function syncPublishButton(target){
   var $b=$('#jaf-apply'); if(!$b.length)return;
   $b.html('<span class="dashicons dashicons-upload" aria-hidden="true"></span> '+(target==='blogger'?'Upload ke Blogger':'Terapkan & Publish'));
 }
 $(document).on('submit','.jaf-delete-wp-profile-form',function(e){
   if(window.JapurSuiteConfirm){ e.preventDefault(); var form=this; window.JapurSuiteConfirm('Hapus profil WordPress ini? Data koneksi profil akan dihapus dari Japur Suite.',function(){form.submit();},{title:'Hapus Profil WordPress',confirmText:'Hapus Profil'}); }
 });
 $(document).on('click','.jaf-test-wp-profile',function(){
   var b=$(this), pid=b.data('profile'); if(!pid)return;
   var payload={action:'jaf_test_wp_profile',nonce:JAF.nonce,profile:pid,site_url:$('#jaf-wp-site-url').val()||'',username:$('#jaf-wp-username').val()||'',app_password:$('#jaf-wp-app-password').val()||''};
   b.prop('disabled',true).text('Menguji...');
   $.post(JAF.ajax,payload,function(r){ var m=r.success?(r.data.message||'Koneksi WordPress berhasil.'):(r.data&&r.data.message?r.data.message:'Koneksi gagal.'); if(window.JapurSuiteToast)window.JapurSuiteToast.show(m,r.success?'success':'error'); else $('#jaf-status').text(m); }).fail(function(){ var m='Server tidak merespons saat menguji koneksi WordPress.'; if(window.JapurSuiteToast)window.JapurSuiteToast.show(m,'error'); else $('#jaf-status').text(m); }).always(function(){ b.prop('disabled',false).text('Test Koneksi'); });
 });
 function parseManualTextarea($field){
   var raw=String($field.val()||'');
   return raw.split(/[\n,]+/).map(function(v){return $.trim(v);}).filter(function(v){return v!=='';});
 }
 function mergeManualTerms($field,items){
   var existing=parseManualTextarea($field), seen={}, merged=[];
   $.each(existing,function(i,name){var k=name.toLocaleLowerCase();if(!seen[k]){seen[k]=1;merged.push(name);}});
   $.each(items||[],function(i,it){var name=$.trim(String(it&&it.name||''));if(!name)return;var k=name.toLocaleLowerCase();if(!seen[k]){seen[k]=1;merged.push(name);}});
   $field.val(merged.join(', ')).trigger('change');
   return merged;
 }
 $(document).on('input change','.jaf-manual-taxonomy',function(){
  var $editor=$(this).closest('#jaf-profile-editor');
  $editor.find('input[name="taxonomy_manual_dirty"]').val('1');
});

$(document).on('click','.jaf-fetch-taxonomies',function(){
   var b=$(this),$manager=b.closest('.jaf-term-manager'),pid=String($manager.data('profile')||''),$status=$manager.find('.jaf-term-manager-status'),$editor=b.closest('#jaf-profile-editor'),$cat=$editor.find('.jaf-manual-taxonomy[data-taxonomy="categories"]'),$tag=$editor.find('.jaf-manual-taxonomy[data-taxonomy="tags"]');
   if(!pid){$status.text('Website Ini memakai taxonomy live dan tidak menggunakan daftar profil.');return;}
   if(!$cat.length||!$tag.length){$status.text('Kolom Kategori atau Tag tidak ditemukan.');return;}
   b.prop('disabled',true).addClass('updating-message'); $status.text('Mengambil Kategori dan Tag dari website tujuan...');
   $.post(JAF.ajax,{action:'jaf_load_wp_taxonomies',nonce:JAF.nonce,profile:pid},function(r){
     if(!r.success){$status.text(r.data&&r.data.message?r.data.message:'Gagal mengambil Kategori dan Tag.');return;}
     var cats=Array.isArray(r.data&&r.data.manual_categories)?r.data.manual_categories:[],tags=Array.isArray(r.data&&r.data.manual_tags)?r.data.manual_tags:[];
     $cat.val(cats.join(', ')).trigger('input').trigger('change');
     $tag.val(tags.join(', ')).trigger('input').trigger('change');
     var nc=Array.isArray(r.data&&r.data.categories)?r.data.categories.length:0,nt=Array.isArray(r.data&&r.data.tags)?r.data.tags.length:0;
     $status.text(nc+' kategori dan '+nt+' tag berhasil dimasukkan ke daftar Kategori dan Tag. Silakan edit bila perlu, lalu klik Simpan Profil.');
   }).fail(function(xhr){var m=xhr&&xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Server tidak merespons saat mengambil Kategori dan Tag.';$status.text(m);}).always(function(){b.prop('disabled',false).removeClass('updating-message');});
 });
 function renderLocalLiveTerms(taxonomy,items){
   var $box=$('.jaf-local-tax-box[data-taxonomy="'+taxonomy+'"]'); if(!$box.length)return;
   var $list=$box.find('.jaf-local-term-list'), $count=$box.find('.jaf-local-tax-count'), $visible=$box.find('.jaf-local-visible-count');
   items=Array.isArray(items)?items:[];
   $count.text(items.length+' item');
   $list.empty();
   if(!items.length){ $list.html('<div class="jaf-local-term-empty">Belum ada '+(taxonomy==='categories'?'kategori':'tag')+'.</div>'); $visible.text('0 ditampilkan'); return; }
   $.each(items,function(i,it){ $list.append('<span class="jaf-local-term-chip" data-name="'+esc(it.name||'')+'">'+esc(it.name||'')+'</span>'); });
   filterLocalLiveTerms($box);
 }
 function filterLocalLiveTerms($box){
   var q=String($box.find('.jaf-local-term-search').val()||'').toLowerCase().trim(), shown=0;
   $box.find('.jaf-local-term-chip').each(function(){ var ok=!q||String($(this).text()).toLowerCase().indexOf(q)>=0; $(this).toggle(ok); if(ok)shown++; });
   var total=$box.find('.jaf-local-term-chip').length;
   $box.find('.jaf-local-visible-count').text((q?shown+' dari '+total:total)+' ditampilkan');
 }
 $(document).on('input','.jaf-local-term-search',function(){ filterLocalLiveTerms($(this).closest('.jaf-local-tax-box')); });
 $(document).on('click','.jaf-refresh-local-taxonomy',function(e){
   e.preventDefault();
   var b=$(this), $card=b.closest('.jaf-local-taxonomy-card'), $status=$card.find('.jaf-local-tax-status');
   if(!b.length)return;
   b.addClass('is-loading').prop('disabled',true).find('.dashicons').addClass('jaf-spin');
   $status.removeClass('is-error is-ok').text('Mengambil ulang Kategori dan Tag dari WordPress...');
   $.post(JAF.ajax,{action:'jaf_refresh_local_taxonomy',nonce:JAF.nonce},function(r){
     if(!r.success){$status.addClass('is-error').text(r.data&&r.data.message?r.data.message:'Gagal memperbarui daftar.');return;}
     renderLocalLiveTerms('categories',r.data.categories||[]); renderLocalLiveTerms('tags',r.data.tags||[]);
     window.JAF_WP_TAXONOMY_CACHE=window.JAF_WP_TAXONOMY_CACHE||{}; window.JAF_WP_TAXONOMY_CACHE.local={categories:r.data.categories||[],tags:r.data.tags||[]};
     if($('#jaf-wp-profile').length && !$('#jaf-wp-profile').val()){ renderWorkflowWpCache(); }
     $status.addClass('is-ok').text(r.data.message||'Daftar berhasil diperbarui.');
   }).fail(function(xhr){var m=xhr&&xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Server tidak merespons saat memperbarui daftar.';$status.addClass('is-error').text(m);}).always(function(){
     b.removeClass('is-loading').prop('disabled',false).find('.dashicons').removeClass('jaf-spin');
   });
 });

 function effectivePublishTarget(){
   var target=$('#jaf-publish-target').val()||'';
   return target;
 }
 function multiTaxonomyMarkup(item,panelId){
   var key=String(item.key||''), platform=String(item.target||'');
   var panelAttrs=panelId?' id="'+esc(panelId)+'" hidden':'';
   var html='<div class="jaf-multi-taxonomy"'+panelAttrs+'>';
   if(platform==='wordpress'){
     var cats=Array.isArray(item.categories)?item.categories:[], tags=Array.isArray(item.tags)?item.tags:[];
     html+='<div class="jaf-multi-tax-title">Kategori <span>Pilih satu atau beberapa</span></div><div class="jaf-multi-tax-checks">';
     if(cats.length){ $.each(cats,function(i,name){ html+='<label><input type="checkbox" class="jaf-multi-category" data-multi-key="'+esc(key)+'" value="'+esc(name)+'"'+(String(name||'').trim().toLowerCase()==='berita'?' checked="checked"':'')+'> '+esc(name)+'</label>'; }); } else html+='<em>Belum ada kategori tersimpan.</em>';
     html+='</div><div class="jaf-multi-tax-title">Tag <span>opsional</span></div><div class="jaf-multi-tax-checks">';
     if(tags.length){ $.each(tags,function(i,name){ html+='<label><input type="checkbox" class="jaf-multi-tag" data-multi-key="'+esc(key)+'" value="'+esc(name)+'"> '+esc(name)+'</label>'; }); } else html+='<em>Belum ada tag tersimpan.</em>';
     html+='</div>';
   } else {
     var labels=Array.isArray(item.labels)?item.labels:[];
     html+='<div class="jaf-multi-tax-title">Label <span>Pilih satu atau beberapa</span></div><div class="jaf-multi-tax-checks">';
     if(labels.length){ $.each(labels,function(i,name){ html+='<label><input type="checkbox" class="jaf-multi-label" data-multi-key="'+esc(key)+'" value="'+esc(name)+'"'+(String(name||'').trim().toLowerCase()==='berita'?' checked="checked"':'')+'> '+esc(name)+'</label>'; }); } else html+='<em>Belum ada Label tersimpan.</em>';
     html+='</div>';
   }
   return html+'</div>';
 }
 function currentMultiFilter(){ return String($('#jaf-multi-filter .jaf-multi-filter-btn.is-active').data('filter')||'all'); }
 function applyMultiFilter(){
   var filter=currentMultiFilter(), visible=0,total=$('#jaf-multi-targets .jaf-multi-target-item').length;
   $('#jaf-multi-targets .jaf-multi-target-item').each(function(){
     var key=String($(this).attr('data-multi-target')||''), item=multiTargetByKey(key), isAdsense=!!(item&&Number(item.adsense||0)===1);
     var show=filter==='all' || (filter==='adsense' ? isAdsense : !isAdsense);
     $(this).toggle(show); if(show) visible++;
   });
   $('#jaf-multi-filter .jaf-multi-filter-btn').removeClass('button-primary is-active');
   $('#jaf-multi-filter .jaf-multi-filter-btn[data-filter="'+filter+'"]').addClass('button-primary is-active');
   var label=filter==='adsense'?'Web Adsense':(filter==='non-adsense'?'Web Bukan Adsense':'Semua Website');
   $('#jaf-multi-filter-status').text('Filter: '+label+' • '+visible+' dari '+total+' tujuan ditampilkan.');
 }
 function renderMultiTargets(){
   var $box=$('#jaf-multi-targets'); if(!$box.length)return;
   var list=Array.isArray(window.JAF_MULTI_TARGETS)?window.JAF_MULTI_TARGETS:[];
   $box.empty();
   if(!list.length){ $box.html('<em>Belum ada website atau blog yang dapat dipilih.</em>'); applyMultiFilter(); return; }
   $.each(list,function(i,item){
     var key=String(item.key||''); if(!key)return;
     var label=esc(item.name||'Website');
     var platform=esc(item.platform||'');
     var language=item.language==='en'?'🇬🇧 English':'🇮🇩 Indonesia';
     var adsense=item.adsense?' <span class="jaf-multi-target-adsense">Adsense</span>':' <span class="jaf-multi-target-adsense is-muted">Non Adsense</span>';
     var panelId='jaf-multi-tax-'+i;
     $box.append('<div class="jaf-multi-target-item" data-multi-target="'+esc(key)+'">'+
       '<div class="jaf-multi-target-head">'+
         '<label class="jaf-multi-target-main"><input type="checkbox" class="jaf-multi-target-check" value="'+esc(key)+'"><span class="jaf-multi-target-copy"><span class="jaf-multi-target-name">'+label+'</span><span class="jaf-multi-target-platform">'+platform+' · '+language+'</span>'+adsense+'</span></label>'+
         '<button type="button" class="jaf-multi-target-toggle" aria-expanded="false" aria-controls="'+panelId+'"><span class="jaf-multi-target-toggle-label">Atur</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>'+
       '</div>'+multiTaxonomyMarkup(item,panelId)+'</div>');
   });
   updateMultiSelectionStatus();
   syncMultiPrimaryTaxonomy();
   syncMultiPrimaryTags();
   applyMultiFilter();
 }
 function toggleMultiTaxonomy($item,open){
   if(!$item || !$item.length)return;
   var $panel=$item.find('.jaf-multi-taxonomy').first(), $btn=$item.find('.jaf-multi-target-toggle').first();
   if(!$panel.length||!$btn.length)return;
   if(typeof open==='undefined') open=$panel.prop('hidden');
   $panel.prop('hidden',!open);
   $btn.attr('aria-expanded',open?'true':'false').toggleClass('is-open',!!open);
   $btn.find('.jaf-multi-target-toggle-label').text(open?'Tutup':'Atur');
 }
 $(document).on('click','.jaf-multi-target-toggle',function(e){
   e.preventDefault();
   e.stopPropagation();
   var $item=$(this).closest('.jaf-multi-target-item');
   toggleMultiTaxonomy($item);
 });
 function updateMultiSelectionStatus(){
   var total=$('#jaf-multi-targets > .jaf-multi-target-item .jaf-multi-target-check:checked').length, all=$('#jaf-multi-targets > .jaf-multi-target-item .jaf-multi-target-check').length;
   $('#jaf-multi-selection-status').text(total ? total+' tujuan dipilih dari '+all+'.' : 'Belum ada tujuan dipilih.');
 }
 $(document).on('click','.jaf-multi-filter-btn',function(e){ e.preventDefault(); $('#jaf-multi-filter .jaf-multi-filter-btn').removeClass('button-primary is-active'); $(this).addClass('button-primary is-active'); applyMultiFilter(); });
 $(document).on('change','.jaf-multi-target-check',syncMultiTargetSelection);
 function multiTargetByKey(key){
   var list=Array.isArray(window.JAF_MULTI_TARGETS)?window.JAF_MULTI_TARGETS:[];
   for(var i=0;i<list.length;i++) if(String(list[i].key||'')===String(key||'')) return list[i];
   return null;
 }
 function multiSelectedValues(key,selector){ var out=[]; $('#jaf-multi-targets '+selector+'[data-multi-key="'+String(key).replace(/"/g,'&quot;')+'"]:checked').each(function(){ out.push($(this).val()); }); return out; }
 function multiEnsureDefaultTerm(values,selector,key){
   var out=Array.isArray(values)?values.slice():[];
   var hasDefault=false;
   $.each(out,function(i,name){ if(String(name||'').trim().toLowerCase()==='berita') hasDefault=true; });
   if(!hasDefault){
     var $default=$('#jaf-multi-targets '+selector+'[data-multi-key="'+String(key).replace(/"/g,'&quot;')+'"]').filter(function(){return String($(this).val()||'').trim().toLowerCase()==='berita';}).first();
     if($default.length) out.unshift($default.val());
   }
   return out;
 }
 function multiCategoriesFor(item){ return multiEnsureDefaultTerm(multiSelectedValues(item&&item.key,'.jaf-multi-category'),'.jaf-multi-category',item&&item.key); }
 function multiPrimaryTags(){ return $('#jaf-multi-primary-tags .jaf-multi-primary-tag:checked').map(function(){return String($(this).val()||'').trim();}).get(); }
 function multiTagsFor(item){
   var local=multiSelectedValues(item&&item.key,'.jaf-multi-tag'), global=multiPrimaryTags(), out=[];
   $.each(local.concat(global),function(i,name){ name=String(name||'').trim(); if(name && $.inArray(name,out)===-1) out.push(name); });
   return out;
 }
 function syncMultiPrimaryTags(){
   var selected=multiPrimaryTags(), set={}; $.each(selected,function(i,name){set[String(name).toLowerCase()]=true;});
   $('#jaf-multi-targets .jaf-multi-target-item').each(function(){
     var $item=$(this), checked=$item.find('.jaf-multi-target-check').prop('checked'), key=String($item.attr('data-multi-target')||'');
     if(key.indexOf('wordpress:')!==0) return;
     $item.find('.jaf-multi-tag').each(function(){
       var name=String($(this).val()||'').trim().toLowerCase();
       if(name==='discover' || name==='trending') $(this).prop('checked',checked && !!set[name]);
     });
   });
   $('#jaf-multi-primary-tags-status').text(selected.length ? selected.join(', ')+' akan diterapkan ke semua tujuan WordPress yang dicentang.' : 'Tidak ada tag global. Tag khusus per-website tetap dapat dipilih melalui Atur.');
 }
 function multiLabelsFor(item){ return multiEnsureDefaultTerm(multiSelectedValues(item&&item.key,'.jaf-multi-label'),'.jaf-multi-label',item&&item.key); }
 function multiPrimaryCategories(){ return $('#jaf-multi-primary-taxonomy .jaf-multi-primary-category:checked').map(function(){return $(this).val();}).get(); }
 function syncMultiPrimaryTaxonomy(){
   var selected=multiPrimaryCategories(), selectedSet={}; $.each(selected,function(i,name){selectedSet[String(name).toLowerCase()]=true;});
   if(!selected.length){
     $('#jaf-multi-primary-taxonomy-status').text('Belum ada kategori utama dipilih. Setiap tujuan tetap memakai default Berita bila tersedia.');
     return;
   }
   $('#jaf-multi-targets .jaf-multi-target-item').each(function(){
     var $item=$(this), isTarget=$item.find('.jaf-multi-target-check').prop('checked');
     $item.find('.jaf-multi-category,.jaf-multi-label').each(function(){
       var name=String($(this).val()||''), lower=name.toLowerCase();
       var isDefault=lower==='berita';
       var should=isTarget && (isDefault || !!selectedSet[lower]);
       $(this).prop('checked',should);
     });
   });
   var count=selected.length;
   $('#jaf-multi-primary-taxonomy-status').text(count ? count+' kategori utama dipilih dan akan diterapkan ke semua tujuan yang dicentang.' : 'Belum ada kategori utama dipilih. Setiap tujuan tetap memakai default Berita bila tersedia.');
 }
 $(document).on('change','.jaf-multi-primary-category',syncMultiPrimaryTaxonomy);
 $(document).on('change','.jaf-multi-primary-tag',syncMultiPrimaryTags);
 function syncMultiTargetSelection(){
   $('#jaf-multi-targets .jaf-multi-target-item').each(function(){ var checked=$(this).find('.jaf-multi-target-check').prop('checked'); $(this).toggleClass('is-selected',!!checked); });
   updateMultiSelectionStatus();
   syncMultiPrimaryTaxonomy();
   syncMultiPrimaryTags();
 }
 function multiResultItem(item,state,status,detail){
   var cls=status==='success'?'is-success':(status==='error'?'is-error':(status==='running'?'is-running':''));
   return '<div class="jaf-multi-progress-item '+cls+'" data-multi-key="'+esc(item.key||'')+'"><span class="jaf-multi-progress-dot"></span><div class="jaf-multi-progress-copy"><strong>'+esc(item.name||'Website')+' · '+esc(state||'Menunggu')+'</strong><span>'+esc(detail||'')+'</span></div></div>';
 }
 function renderMultiProgress(items){
   var html='<div class="jaf-multi-progress" id="jaf-multi-progress">';
   $.each(items,function(i,item){ html+=multiResultItem(item,'Menunggu','', ''); });
   html+='</div><div id="jaf-multi-summary" class="jwp-status" aria-live="polite"></div>';
   $('#jaf-result').html(html);
 }
 function updateMultiProgress(item,status,state,detail){
   var $row=$('#jaf-multi-progress [data-multi-key="'+String(item.key||'').replace(/\"/g,'&quot;')+'"]');
   if(!$row.length)return;
   $row.removeClass('is-running is-success is-error');
   if(status==='running')$row.addClass('is-running');
   if(status==='success')$row.addClass('is-success');
   if(status==='error')$row.addClass('is-error');
   $row.find('strong').text((item.name||'Website')+' · '+(state||'Menunggu'));
   $row.find('span.jaf-multi-progress-copy > span').text(detail||'');
 }
 function multiBatchSuccessCard(results,ok,failed){
   var links='';
   $.each(results||[],function(i,item){
     if(!item || !item.url)return;
     links+='<a class="button jaf-multi-open-published" target="_blank" rel="noopener noreferrer" href="'+esc(item.url)+'"><span class="dashicons dashicons-external" aria-hidden="true"></span> Lihat '+esc(item.name||'Artikel')+'</a>';
   });
   var detail=ok+' website berhasil dipublikasikan'+(failed?' • '+failed+' gagal.':' ke semua website tujuan.');
   return '<div class="jaf-publish-success jaf-multi-publish-success"><div class="jaf-success-mark">✓</div><div class="jaf-publish-success-copy"><span class="jaf-result-kicker">PUBLIKASI BATCH BERHASIL</span><h3>Artikel berhasil diterbitkan</h3><p>'+esc(detail)+'</p>'+(links?'<div class="jaf-multi-published-links">'+links+'</div>':'')+'<div class="jaf-publish-actions jaf-multi-final-actions"><button type="button" class="button button-primary jaf-rebuild">Buat Ulang</button><button type="button" class="button jaf-close-success">Tutup</button></div></div></div>';
 }
 function foregroundMultiDiagnostic(item,stage,data,fallback,http){
   data=data||{};
   var msg=String(data.message||fallback||'Proses gagal.');
   var code=Number(data.http||http||0);
   return {target:String(item&&item.name||'Website'),stage:String(stage||'PUBLISH').toUpperCase(),http:code,message:msg};
 }
 function setForegroundMultiFailure(item,stage,data,fallback,http){
   var diag=foregroundMultiDiagnostic(item,stage,data,fallback,http);
   item.status='failed';
   item.stage='FAILED';
   item.failed_stage=diag.stage;
   item.message=diag.message;
   item.diagnostic=diag;
   developerLog({source:'foreground-multi',job_id:'',target:diag.target,stage:diag.stage,state:'failed',retry_count:item.retry_count||0,http:diag.http||0,message:diag.message});
   if(diag.stage==='ARTICLE'){
     item.article_job_token='';
     item.result={};
     item.thumbnail_image_url='';
   }
   return diag;
 }
 function foregroundMultiDiagnostics(items){
   var out=[];
   $.each(items||[],function(i,item){
     if(item && item.status==='failed' && item.diagnostic) out.push(item.diagnostic);
   });
   return out;
 }
 function renderForegroundMultiDiagnostics(){
   var items=window.JAF_LAST_MULTI_ITEMS||[], diagnostics=foregroundMultiDiagnostics(items);
   $('.jaf-multi-diagnostics').remove();
   if(!diagnostics.length || !masterEnabled('diagnostic_enabled',true))return;
   var failed=diagnostics.length, seen={};
   var dh='<div class="jaf-publication-diagnostics jaf-multi-diagnostics"><div class="jaf-diagnostic-head"><div><span class="jaf-result-kicker">PUBLICATION DIAGNOSTICS</span><h3>Detail kegagalan batch</h3></div>'+(masterDiagnostic('diagnostic_show_stage',true)?'<span class="jaf-diagnostic-badge">'+failed+' gagal</span>':'')+'</div><div class="jaf-diagnostic-list">';
   $.each(diagnostics,function(i,d){
     var diagKey=String(d.target||'')+'|'+String(d.stage||'')+'|'+String(d.message||'');
     if(seen[diagKey])return; seen[diagKey]=true;
     var retryKey='';
     $.each(items,function(k,it){if(it && it.status==='failed' && String(it.name||'')===String(d.target||''))retryKey=String(it.key||'');});
     var meta='';
     if(masterDiagnostic('diagnostic_show_stage',true)) meta+=esc(d.stage||'PUBLISH');
     if(masterDiagnostic('diagnostic_show_http',true)&&d.http&&diagnosticLevel()!=='basic') meta+=(meta?' · ':'')+'HTTP '+esc(String(d.http));
     dh+='<div class="jaf-diagnostic-row">'+(masterDiagnostic('diagnostic_show_target',true)?'<strong>'+esc(d.target||'Website')+'</strong>':'')+(meta?'<span>'+meta+'</span>':'')+(masterDiagnostic('diagnostic_show_message',true)?'<em>'+esc(d.message||'Proses gagal.')+'</em>':'')+((masterDiagnostic('diagnostic_show_retry',true)&&retryKey&&masterEnabled('retry_enabled',true))?'<button type="button" class="button jaf-multi-foreground-retry" data-retry-key="'+esc(retryKey)+'">↻ Coba Lagi</button>':'')+'</div>';
   });
   dh+='</div></div>'; $('#jaf-result').append(dh);
 }

 function finishMultiBatch(items,ok,failed,results){
   window.JAF_LAST_MULTI_DIAGNOSTICS=foregroundMultiDiagnostics(items);
   completeWorkflow('publish','Batch selesai',''+ok+' website berhasil dipublikasikan'+(failed?' • '+failed+' gagal.':' .'));
   $('#jaf-multi-summary').text((items.some(function(it){return it.status==='skipped';})?'Batch dihentikan: ':'Selesai: ')+ok+' berhasil'+(failed?' • '+failed+' gagal.':' • semua berhasil.')+(items.some(function(it){return it.status==='skipped';})?' • tujuan berikutnya dilewati.':''));
   if(ok>0) $('#jaf-result').append(multiBatchSuccessCard(results||[],ok,failed));
   renderForegroundMultiDiagnostics();
   resetButtons();
   window.setTimeout(function(){ if(ok>0) autoScrollTo('.jaf-multi-publish-success',0); },260);
 }
 function runMultiDestination(items,index,counters){
   if(index>=items.length){ window.JAF_MULTI_DIAGNOSTICS_CURRENT=counters.diagnostics||[]; finishMultiBatch(items,counters.ok,counters.failed,counters.results||[]); return; }
   var item=items[index];
   item.status='running'; item.stage='ARTICLE'; item.failed_stage=''; item.message=''; item.diagnostic=null;
   updateMultiProgress(item,'running','Membuat artikel',(index+1)+' dari '+items.length+' — '+item.platform);
   startWorkflow('article','Multi Website '+(index+1)+'/'+items.length,'Membuat artikel untuk '+item.name+'.',8,52);
   var payload={action:'jaf_generate_article',nonce:JAF.nonce,material:$('#jaf-material').val().trim(),type:$('#jaf-type').val(),location:$('#jaf-location').val(),language:(item.language==='en'?'en':'id'),target:item.target,blogger_profile:item.blogger_profile||'',wordpress_profile:item.wordpress_profile||'',categories:multiCategoriesFor(item),tags:multiTagsFor(item),labels:multiLabelsFor(item),used_titles:counters.usedTitles||[]};
   $.post(JAF.ajax,payload,function(r){
     if(!r.success){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var d=setForegroundMultiFailure(item,'ARTICLE',r.data||{},'Pembuatan artikel gagal.'); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Artikel: '+d.message+(d.http?' · HTTP '+d.http:'')); continueOrStopMulti(items,index,counters); return; }
     var job=r.data.job; item.article_job_token=job||''; item.result={fields:r.data.fields||{},payload:r.data.payload||''}; item.stage='THUMBNAIL'; item.status='running';
     updateMultiProgress(item,'running','Membuat thumbnail','Artikel selesai, menyiapkan thumbnail.');
     startWorkflow('thumb','Multi Website '+(index+1)+'/'+items.length,'Membuat thumbnail untuk '+item.name+'.',52,82);
     $.post(JAF.ajax,{action:'jaf_generate_thumbnail',nonce:JAF.nonce,job:job},function(t){
       if(!t.success){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var d=setForegroundMultiFailure(item,'THUMBNAIL',t.data||{},'Pembuatan thumbnail gagal.'); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Thumbnail: '+d.message+(d.http?' · HTTP '+d.http:'')); continueOrStopMulti(items,index,counters); return; }
       item.stage='PUBLISH'; item.status='running'; item.thumbnail_image_url=t.data&&t.data.image_url?t.data.image_url:'';
       updateMultiProgress(item,'running','Mempublikasikan','Artikel dan thumbnail siap, mengirim ke '+item.name+'.');
       startWorkflow('publish','Multi Website '+(index+1)+'/'+items.length,'Mempublikasikan ke '+item.name+'.',82,96);
       var action=item.target==='blogger'?'jaf_blogger_publish':'jaf_apply_publish';
       var pub={action:action,nonce:JAF.nonce,job:job};
       if(action==='jaf_blogger_publish') pub.labels=multiLabelsFor(item); else { pub.categories=multiCategoriesFor(item); pub.tags=multiTagsFor(item); }
       $.post(JAF.ajax,pub,function(pr){
         if(!pr.success){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var d=setForegroundMultiFailure(item,'PUBLISH',pr.data||{},'Publikasi gagal.'); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Publish: '+d.message+(d.http?' · HTTP '+d.http:'')); continueOrStopMulti(items,index,counters); return; }
         counters.ok++; counters.usedTitles=counters.usedTitles||[]; counters.results=counters.results||[];
         counters.results.push({name:item.name||'Website',platform:item.platform||'',url:(pr.data&&pr.data.url)?pr.data.url:''});
         if(r && r.data && r.data.fields && r.data.fields.title) counters.usedTitles.push(r.data.fields.title);
         item.status='success'; item.stage='DONE'; item.failed_stage=''; item.diagnostic=null; item.publish_url=(pr.data&&pr.data.url)?pr.data.url:'';
         updateMultiProgress(item,'success','Sukses','Artikel berhasil dibuat, thumbnail selesai, dan dipublikasikan.');
         runMultiDestination(items,index+1,counters);
       }).fail(function(xhr){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var data=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; var d=setForegroundMultiFailure(item,'PUBLISH',data,'Server error saat publish.',xhr&&xhr.status?xhr.status:0); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Publish: '+d.message+(d.http?' · HTTP '+d.http:'')); runMultiDestination(items,index+1,counters); });
     }).fail(function(xhr){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var data=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; var d=setForegroundMultiFailure(item,'THUMBNAIL',data,'Server error saat thumbnail.',xhr&&xhr.status?xhr.status:0); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Thumbnail: '+d.message+(d.http?' · HTTP '+d.http:'')); runMultiDestination(items,index+1,counters); });
   }).fail(function(xhr){ counters.failed++; counters.diagnostics=counters.diagnostics||[]; var data=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; var d=setForegroundMultiFailure(item,'ARTICLE',data,'Server error saat membuat artikel.',xhr&&xhr.status?xhr.status:0); counters.diagnostics.push(d); updateMultiProgress(item,'error','Gagal','Artikel: '+d.message+(d.http?' · HTTP '+d.http:'')); runMultiDestination(items,index+1,counters); });
 }
 $(document).on('click','.jaf-multi-foreground-retry',function(){
   developerLog({source:'foreground-retry',target:$(this).attr('data-retry-key')||'',stage:'RETRY',state:'starting',retry_count:1,message:'Retry foreground dimulai.'});
   var $btn=$(this), key=String($btn.attr('data-retry-key')||''), item=null;
   $.each(window.JAF_LAST_MULTI_ITEMS||[],function(i,it){if(String(it.key||'')===key)item=it;});
   if(!item)return;
   var stage=String(item.failed_stage||'ARTICLE').toUpperCase();
   $btn.prop('disabled',true).text('Mencoba...');
   function retryFail(message,data,http){
     var d=setForegroundMultiFailure(item,stage,data||{message:message},message,http);
     window.JAF_LAST_MULTI_DIAGNOSTICS=foregroundMultiDiagnostics(window.JAF_LAST_MULTI_ITEMS||[]);
     $btn.prop('disabled',false).text('↻ Coba Lagi');
     updateMultiProgress(item,'error','Gagal',stage+': '+d.message+(d.http?' · HTTP '+d.http:''));
     renderForegroundMultiDiagnostics();
   }
   function retryPublish(){
     if(!item.article_job_token){retryFail('Data artikel sebelumnya tidak tersedia. Jalankan ulang dari tahap Artikel.');return;}
     var action=item.target==='blogger'?'jaf_blogger_publish':'jaf_apply_publish';
     var pub={action:action,nonce:JAF.nonce,job:item.article_job_token};
     if(action==='jaf_blogger_publish')pub.labels=multiLabelsFor(item);else{pub.categories=multiCategoriesFor(item);pub.tags=multiTagsFor(item);}
     item.status='running'; item.stage='PUBLISH';
     startWorkflow('publish','Retry '+item.name,'Mempublikasikan ulang ke '+item.name+'.',82,96);
     $.post(JAF.ajax,pub,function(pr){
       if(!pr.success){retryFail((pr.data&&pr.data.message)||'Publikasi ulang gagal.',pr.data||{});return;}
       item.status='success';item.stage='DONE';item.failed_stage='';item.diagnostic=null;item.publish_url=(pr.data&&pr.data.url)?pr.data.url:'';
       updateMultiProgress(item,'success','Sukses','Publikasi ulang berhasil.');
       var ok=0,failed=0,results=[];$.each(window.JAF_LAST_MULTI_ITEMS||[],function(i,it){if(it.status==='success'){ok++;results.push({name:it.name,url:it.publish_url||''});}else if(it.status==='failed')failed++;});
       window.JAF_LAST_MULTI_DIAGNOSTICS=foregroundMultiDiagnostics(window.JAF_LAST_MULTI_ITEMS||[]);
       $('#jaf-multi-summary').text('Selesai: '+ok+' berhasil'+(failed?' • '+failed+' gagal.':' • semua berhasil.'));
       $btn.text('✓ Berhasil diperbaiki').prop('disabled',true);
       renderForegroundMultiDiagnostics();
       completeWorkflow('publish','Retry berhasil',item.name+' berhasil dipublikasikan ulang.');
     }).fail(function(xhr){var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{};retryFail(d.message||'Server tidak merespons saat publish ulang.',d,xhr&&xhr.status?xhr.status:0);});
   }
   function retryThumbnail(){
     if(!item.article_job_token){retryFail('Data artikel sebelumnya tidak tersedia. Jalankan ulang dari tahap Artikel.');return;}
     item.status='running'; item.stage='THUMBNAIL';
     startWorkflow('thumb','Retry '+item.name,'Membuat ulang thumbnail untuk '+item.name+'.',52,82);
     $.post(JAF.ajax,{action:'jaf_generate_thumbnail',nonce:JAF.nonce,job:item.article_job_token},function(t){
       if(!t.success){retryFail((t.data&&t.data.message)||'Pembuatan thumbnail ulang gagal.',t.data||{});return;}
       item.stage='PUBLISH'; item.status='running'; item.failed_stage=''; item.thumbnail_image_url=t.data&&t.data.image_url?t.data.image_url:''; retryPublish();
     }).fail(function(xhr){var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{};retryFail(d.message||'Server tidak merespons saat thumbnail ulang.',d,xhr&&xhr.status?xhr.status:0);});
   }
   if(stage==='ARTICLE'){
     if(!$('#jaf-type').val()){retryFail('Pilih jenis Artikel terlebih dahulu sebelum Retry Artikel.');return;}
     var payload={action:'jaf_generate_article',nonce:JAF.nonce,material:$('#jaf-material').val().trim(),type:$('#jaf-type').val(),location:$('#jaf-location').val(),language:(item.language==='en'?'en':'id'),target:item.target,blogger_profile:item.blogger_profile||'',wordpress_profile:item.wordpress_profile||'',categories:multiCategoriesFor(item),tags:multiTagsFor(item),labels:multiLabelsFor(item),used_titles:[]};
     item.status='running'; item.stage='ARTICLE';
     startWorkflow('article','Retry '+item.name,'Membuat ulang artikel untuk '+item.name+'.',8,52);
     $.post(JAF.ajax,payload,function(r){
       if(!r.success){retryFail((r.data&&r.data.message)||'Pembuatan artikel ulang gagal.',r.data||{});return;}
       item.article_job_token=r.data.job||'';item.result={fields:r.data.fields||{},payload:r.data.payload||''};item.failed_stage='';retryThumbnail();
     }).fail(function(xhr){var d=xhr&&xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{};retryFail(d.message||'Server tidak merespons saat artikel ulang.',d,xhr&&xhr.status?xhr.status:0);});
   }else if(stage==='THUMBNAIL'){retryThumbnail();}else{retryPublish();}
 });
 function startMultiWebsite(){
   var mat=$('#jaf-material').val().trim(), checked=$('#jaf-multi-targets input:checked').map(function(){return $(this).val();}).get();
   if(!$('#jaf-type').val()){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Pilih jenis Artikel terlebih dahulu.</div>'); return; }
   if(mat.length<30){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Materi terlalu pendek. '+(sourceMode==='url'?'Klik Ekstrak Materi terlebih dahulu.':'')+'</div>'); return; }
   if(!checked.length){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Pilih minimal satu website atau blog tujuan.</div>'); return; }
   var items=[]; $.each(checked,function(i,key){var item=multiTargetByKey(key);if(item)items.push(item);});
   if(!items.length){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Tujuan Multi Website tidak ditemukan. Muat ulang halaman lalu coba lagi.</div>'); return; }
   $('#jaf-generate').prop('disabled',true); $('#jaf-material,#jaf-url,#jaf-type,#jaf-location,#jaf-language,#jaf-publish-target,#jaf-extract-url,#jaf-extract-material,#jaf-multi-targets input').prop('disabled',true);
   renderMultiProgress(items);
   window.JAF_LAST_MULTI_DIAGNOSTICS=[]; window.JAF_LAST_MULTI_ITEMS=items.slice(); runMultiDestination(items,0,{ok:0,failed:0,usedTitles:[],results:[],diagnostics:[]});
 }
 function syncTargetUI(){
   var target=$('#jaf-publish-target').val()||'', multi=target==='multi', hasTarget=(target==='wordpress'||target==='blogger'||target==='multi');
   $('#jaf-multi-target-card').toggle(multi);
   $('#jaf-language-setting-row').toggle(!multi);
   $('#jaf-wp-profile-wrap').toggle(hasTarget && target==='wordpress' && !multi);
   $('#jaf-blogger-profile-wrap').toggle(hasTarget && target==='blogger' && !multi);
   $('#jaf-wp-taxonomy,#jaf-wp-tags').toggle(hasTarget && target==='wordpress' && !multi);
   $('#jaf-blogger-labels').toggle(hasTarget && target==='blogger' && !multi);
   $('#jaf-wp-options-card').toggle(!multi && hasTarget);
   $('#jaf-taxonomy-title').text(target==='blogger'?'Label Blogger':'Kategori & Tag');
   $('#jaf-taxonomy-desc').text(target==='blogger'?'Atur label artikel Blogger. Label mengikuti profil Blogger yang dipilih.':'Atur klasifikasi artikel WordPress. Bagian ini otomatis menyesuaikan tujuan.');
   $('#jaf-taxonomy-badge').text(target==='blogger'?'Blogger':'WordPress');
   if(target==='blogger' && !multi) loadBloggerProfile();
   if(target==='wordpress' && !multi) renderWorkflowWpCache();
   if(!hasTarget){ $('#jaf-wp-profile-wrap,#jaf-blogger-profile-wrap,#jaf-multi-target-card').hide(); $('#jaf-wp-options-card').hide(); }
   if(multi){ $('#jaf-wp-profile-wrap,#jaf-blogger-profile-wrap,#jaf-wp-options-card').hide(); renderMultiTargets(); }
   syncGenerateButtonLabel();
 }
 function clearTaxonomySelections(){
   $('#jaf-category input,#jaf-tags input,#jaf-label input').prop('checked',false);
 }
 function syncTargetContext(){
   var target=$('#jaf-publish-target').val()||'', effective=target;
   // Satu sumber konteks untuk seluruh field setelah Tujuan Publish dipilih.
   // Pilihan taxonomy/label lama tidak boleh terbawa ke website/blog yang berbeda.
   clearTaxonomySelections();
   if(effective==='wordpress'){
     $('#jaf-blogger-profile').val('');
     renderWorkflowWpCache();
   } else if(effective==='blogger'){
     $('#jaf-wp-profile').val('');
     loadBloggerProfile();
   }
 }
 $(document).on('change','.jaf-multi-target-check',syncMultiTargetSelection);
 $(document).on('click','#jaf-multi-select-all',function(){ $('#jaf-multi-targets .jaf-multi-target-item:visible .jaf-multi-target-check').prop('checked',true); syncMultiTargetSelection(); });
 $(document).on('click','#jaf-multi-clear-all',function(){ $('#jaf-multi-targets .jaf-multi-target-item:visible .jaf-multi-target-check').prop('checked',false); syncMultiTargetSelection(); });
 $(document).on('change','#jaf-publish-target',function(){ JAF_PREFLIGHT_OK=false; syncTargetUI(); syncTargetContext(); $('#jaf-preflight-card').hide(); syncGenerateButtonState(); });
 $(document).on('change','#jaf-type',function(){ JAF_PREFLIGHT_OK=false; $('#jaf-preflight-card').hide(); syncGenerateButtonState(); });
 $(document).on('change','#jaf-publish-target',syncGenerateButtonLabel);
 $(document).on('change','#jaf-wp-profile,#jaf-blogger-profile,.jaf-multi-target-check',function(){ JAF_PREFLIGHT_OK=false; $('#jaf-preflight-card').hide(); syncGenerateButtonState(); });
 $(document).on('click','#jaf-preflight-run',function(){ runPreflight(false); });
 $(document).on('input change','#jaf-material',function(){ JAF_PREFLIGHT_OK=false; $('#jaf-preflight-card').hide(); syncGenerateButtonState(); });

 $(document).on('change','#jaf-wp-profile',function(){
   clearTaxonomySelections();
   renderWorkflowWpCache();
 });
 $(document).on('change','#jaf-blogger-profile',function(){
   $('#jaf-category input,#jaf-tags input').prop('checked',false);
   loadBloggerProfile();
 });
 syncTargetUI();
 syncGenerateButtonState();
 syncGenerateButtonLabel();
 function renderWpTerms(taxonomy,items){
   var $box=taxonomy==='categories'?$('#jaf-category'):$('#jaf-tags');
   if(!$box.length)return;
   $box.empty();
   if(!items||!items.length){ $box.html('<em>Belum ada '+(taxonomy==='categories'?'kategori':'tag')+' pada website tujuan.</em>'); return; }
   $.each(items,function(i,item){
     var isDefault=taxonomy==='categories' && String(item.name||'').trim().toLowerCase()==='berita';
     $box.append('<label><input type="checkbox" name="jaf_'+(taxonomy==='categories'?'category':'tag')+'[]" value="'+esc(item.name)+'"'+(isDefault?' checked="checked"':'')+'> '+esc(item.name)+'</label>');
   });
 }
 var localWorkflowTaxonomyLoading=false;
 function loadLocalWorkflowTaxonomy(){
   if(localWorkflowTaxonomyLoading || !$('#jaf-wp-profile').length || $('#jaf-wp-profile').val()!=='') return;
   localWorkflowTaxonomyLoading=true;
   $.post(JAF.ajax,{action:'jaf_refresh_local_taxonomy',nonce:JAF.nonce},function(r){
     if(!r || !r.success) return;
     var cats=Array.isArray(r.data&&r.data.categories)?r.data.categories:[], tags=Array.isArray(r.data&&r.data.tags)?r.data.tags:[];
     window.JAF_WP_TAXONOMY_CACHE=window.JAF_WP_TAXONOMY_CACHE||{};
     window.JAF_WP_TAXONOMY_CACHE.local={categories:cats,tags:tags,source:'local-live'};
     if($('#jaf-wp-profile').val()===''){
       renderWpTerms('categories',cats);
       renderWpTerms('tags',tags);
       $('#jaf-category-desc').text(cats.length?'Kategori live dari Website ini (WordPress).':'Belum ada kategori di Website ini.');
       $('#jaf-tag-desc').text(tags.length?'Tag live dari Website ini (WordPress).':'Belum ada tag di Website ini.');
     }
   }).always(function(){ localWorkflowTaxonomyLoading=false; });
 }
 function renderWorkflowWpCache(){
   var pid=$('#jaf-wp-profile').val()||'', cacheKey=pid||'local', cached=(window.JAF_WP_TAXONOMY_CACHE||{})[cacheKey]||{};
   var cats=Array.isArray(cached.categories)?cached.categories:[], tags=Array.isArray(cached.tags)?cached.tags:[];
   renderWpTerms('categories',cats); renderWpTerms('tags',tags);
   var isLocalLive=(!pid || cached.source==='local-live');
   $('#jaf-category-desc').text(cats.length?(isLocalLive?'Kategori live dari Website ini (WordPress).':'Kategori dari website tujuan (cache Japur Suite).'):(isLocalLive?'Memuat kategori live dari Website ini...':'Belum ada kategori tersimpan untuk domain ini.'));
   $('#jaf-tag-desc').text(tags.length?(isLocalLive?'Tag live dari Website ini (WordPress).':'Tag dari website tujuan (cache Japur Suite).'):(isLocalLive?'Memuat tag live dari Website ini...':'Belum ada tag tersimpan untuk domain ini.'));
   if(!pid && (!cats.length || !tags.length)) loadLocalWorkflowTaxonomy();
 }
 function ensureBloggerProfileOptions(){
   var $select=$('#jaf-blogger-profile');
   if(!$select.length)return '';
   var profiles=window.JAF_BLOGGER_PROFILES||{}, keys=Object.keys(profiles);
   if(!keys.length)return $select.val()||'';
   var current=$select.val()||'';
   var needsHydrate=$select.find('option').length!==keys.length;
   if(!needsHydrate){
     $.each(keys,function(i,key){
       var $opt=$select.find('option').eq(i);
       if(!$opt.length || $opt.val()!==key || $.trim($opt.text())===''){ needsHydrate=true; return false; }
     });
   }
   if(needsHydrate){
     $select.empty();
     $.each(keys,function(i,key){
       var item=profiles[key]||{}, name=item.name||'Profil Blogger';
       $select.append($('<option>',{value:key,text:name}));
     });
   }
   if(current && $select.find('option[value="'+esc(current).replace(/"/g,'&quot;')+'"]').length){
     $select.val(current);
   } else {
     $select.prop('selectedIndex',0);
   }
   return $select.val()||'';
 }
 function loadBloggerProfile(){
   var $box=$('#jaf-label');
   var pid=ensureBloggerProfileOptions();
   if(!pid || !$box.length) return;
   var profiles=window.JAF_BLOGGER_PROFILES||{}, labels=(profiles[pid]&&profiles[pid].labels)||[];
   $box.empty(); $.each(labels,function(i,name){ $box.append('<label><input type="checkbox" name="jaf_label[]" value="'+esc(name)+'"'+(String(name||'').trim().toLowerCase()==='berita'?' checked="checked"':'')+'> '+esc(name)+'</label>'); });
   if(!labels.length) $box.html('<em>Belum ada Label untuk profil ini. Atur di tab Blogger.</em>');
 }
 $('#jaf-extract-material').on('click',function(){
   var b=$(this),mat=$('#jaf-material').val().trim();
   if(!mat){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Materi wajib diisi terlebih dahulu.</div>'); return; }
   if(mat.length<30){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">Materi terlalu pendek untuk diekstrak.</div>'); return; }
   b.prop('disabled',true); $('#jaf-extract-material').prop('disabled',true);
   $('#jaf-manual-extract-status').empty();
   startWorkflow('extract','Mengekstrak materi','Membersihkan materi langsung dan mengambil bagian artikel yang layak diproses.',8,86);
   $.post(JAF.ajax,{action:'jaf_extract_material',nonce:JAF.nonce,material:mat},function(r){
     if(!r.success){ failWorkflow('extract','Ekstraksi gagal','Materi belum dapat disiapkan.',r.data?.message||'Ekstraksi gagal.'); return; }
     $('#jaf-material').val(r.data.material||'');
     var preview=(r.data.material||'').substring(0,1800);
     completeWorkflow('extract','Ekstraksi selesai',(r.data.mode==='html'?'HTML':'Teks')+' berhasil dibersihkan — '+(r.data.paragraphs||0)+' paragraf • '+(r.data.words||0)+' kata • '+(r.data.length||0)+' karakter.');
     $('#jaf-manual-extract-status').html('<details id="jaf-extract-preview" class="jaf-extract-preview"><summary>Lihat Hasil Ekstraksi</summary><pre>'+esc(preview)+(r.data.material.length>1800?'\n\n… preview dipotong. Materi lengkap tetap tersimpan di kolom Materi Artikel.':'')+'</pre></details>');
     window.setTimeout(function(){ runPreflight(true); },220);
   }).fail(function(){ failWorkflow('extract','Ekstraksi gagal','Server tidak merespons dengan benar.','Server error saat ekstraksi materi.'); }).always(function(){ b.prop('disabled',false); });
 });

 $('#jaf-extract-url').on('click',function(){
   var b=$(this),url=$('#jaf-url').val().trim();
   if(!url){ ensureWorkflow().html('<div class="jaf-inline-alert is-error">URL wajib diisi.</div>'); return; }
   b.prop('disabled',true); $('#jaf-extract-preview').remove();
   startWorkflow('extract','Mengekstrak materi','Mengambil isi utama artikel dan membersihkan konten yang tidak diperlukan.',8,86);
   $.post(JAF.ajax,{action:'jaf_extract_url',nonce:JAF.nonce,url:url},function(r){
     if(!r.success){failWorkflow('extract','Ekstraksi gagal','Materi belum dapat disiapkan.',r.data?.message||'Ekstraksi gagal.');return;}
     $('#jaf-material').val(r.data.material||'');
     var modeLabel=(r.data.mode==='full_article'?'Full Article':'Partial');
     var preview=(r.data.material||'').substring(0,1800);
     completeWorkflow('extract','Ekstraksi selesai',(modeLabel==='Full Article'?'Full Article':'Partial')+' berhasil disiapkan — '+(r.data.paragraphs||0)+' paragraf • '+(r.data.words||0)+' kata • '+(r.data.length||0)+' karakter.');
     $('#jaf-source-url').append('<details id="jaf-extract-preview" class="jaf-extract-preview"><summary>Lihat Hasil Ekstraksi</summary><pre>'+esc(preview)+(r.data.material.length>1800?'\n\n… preview dipotong. Materi lengkap tetap tersimpan di kolom Materi Artikel.':'')+'</pre></details>');
     window.setTimeout(function(){ runPreflight(true); },220);
   }).fail(function(){failWorkflow('extract','Ekstraksi gagal','Server tidak merespons dengan benar.','Server error saat ekstraksi.');}).always(function(){b.prop('disabled',false);});
 });

 $('#jaf-generate').on('click',function(){
   var b=$(this),mat=$('#jaf-material').val().trim(),target=$('#jaf-publish-target').val()||'';
   if(!safetyGuardBeforeStart(target)) return;
   if($('#jaf-background-mode').prop('checked')){ backgroundStart(target); return; }
   if(target==='multi'){ startMultiWebsite(); return; }
   b.prop('disabled',true); $('#jaf-material,#jaf-url,#jaf-category input,#jaf-tags input,#jaf-label input,#jaf-type,#jaf-location,#jaf-language,#jaf-publish-target,#jaf-extract-url,#jaf-extract-material,#jaf-wp-profile').prop('disabled',true); $('#jaf-result').empty();
   startWorkflow('article','Membuat artikel','OpenAI sedang menyusun artikel sesuai pengaturan yang dipilih.',8,86);
   $.post(JAF.ajax,{action:'jaf_generate_article',nonce:JAF.nonce,material:mat,type:$('#jaf-type').val(),location:$('#jaf-location').val(),language:$('#jaf-language').val()||'id',target:target,blogger_profile:$('#jaf-blogger-profile').val()||'',wordpress_profile:$('#jaf-wp-profile').val()||'',categories:$('#jaf-category input:checked').map(function(){return $(this).val();}).get(),tags:$('#jaf-tags input:checked').map(function(){return $(this).val();}).get(),labels:$('#jaf-label input:checked').map(function(){return $(this).val();}).get()},function(r){
     if(!r.success){failWorkflow('article','Pembuatan artikel gagal','Artikel belum selesai dibuat.',r.data?.message||'Tahap artikel gagal.');resetButtons();return;}
     var job=r.data.job, f=r.data.fields||{};
     resultShell(); $('.jaf-result-grid').append(articleCard(f,r.data.payload));
     window.setTimeout(function(){ autoScrollTo('.jaf-article-card'); },220);
     startWorkflow('thumb','Membuat thumbnail','Menyiapkan gambar 16:9 berdasarkan artikel yang sudah dibuat.',8,86);
     $.post(JAF.ajax,{action:'jaf_generate_thumbnail',nonce:JAF.nonce,job:job},function(t){
       if(!t.success){failWorkflow('thumb','Thumbnail gagal','Artikel sudah selesai, tetapi thumbnail belum berhasil dibuat.',t.data?.message||'Thumbnail gagal.');$('.jaf-result-grid').append('<div class="jaf-inline-alert is-error">Artikel sudah berhasil dibuat. Silakan ulangi proses thumbnail.</div>');resetButtons();return;}
       var d=t.data, img=d.image_url||'', target=effectivePublishTarget()||'wordpress';
       $('.jaf-result-grid').append(thumbCard(img,target));
       completeWorkflow('thumb','Thumbnail selesai','Artikel dan thumbnail sudah siap. Tahap berikutnya adalah publikasi.');
       $('#jaf-apply').data('job',job);
       $('#jaf-publish-card').show().html(publishCard(target,target==='blogger'?'Siap dikirim ke Blogger':'Siap diterapkan ke WordPress','Periksa hasil di atas, lalu lanjutkan publikasi jika sudah sesuai.'));
       resetButtons();
       window.setTimeout(function(){ autoScrollTo('.jaf-thumb-card'); },220);
       window.setTimeout(function(){ autoScrollTo('#jaf-apply'); },900);
     }).fail(function(){failWorkflow('thumb','Thumbnail gagal','Server memutus proses thumbnail. Artikel tetap tersedia.','Server error saat membuat thumbnail.');resetButtons();});
   }).fail(function(){failWorkflow('article','Pembuatan artikel gagal','Server tidak merespons dengan benar.','Server error atau timeout saat membuat artikel.');resetButtons();});
 });

 function publishJobToBlogger(job){
   var labels=$('#jaf-label input:checked').map(function(){return $(this).val();}).get();
   $.post(JAF.ajax,{action:'jaf_blogger_publish',nonce:JAF.nonce,job:job,labels:labels},function(r){
     if(!r.success){var diag=r.data||{};failWorkflow('publish','Publikasi Blogger gagal','Artikel belum diterbitkan.',diag.message||'Gagal publish Blogger.');$('#jaf-publish-card').show().html(publicationDiagnosticCard(diag));$('#jaf-apply').prop('disabled',false);return;}
     completeWorkflow('publish','Publikasi selesai','Artikel dan thumbnail berhasil dipublikasikan ke Blogger.');
     $('#jaf-publish-card').show().html(publicationSuccessCard('blogger',r.data.url||''));
     $('#jaf-apply').remove();
     hideWorkflowAfterSinglePublish('blogger',false);
     window.setTimeout(function(){ autoScrollTo('.jaf-publish-success',0); },260);
   }).fail(function(xhr){
     var detail=''; if(xhr&&xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message) detail=xhr.responseJSON.data.message; else if(xhr&&xhr.status) detail='HTTP '+xhr.status;
     failWorkflow('publish','Publikasi Blogger gagal','Server tidak merespons dengan benar.',detail||'Server error saat publish Blogger.');$('#jaf-publish-card').show().html(publicationDiagnosticCard({stage:'PUBLISH',http:xhr&&xhr.status?xhr.status:0,message:detail||'Server error saat publish Blogger.',target:$('#jaf-blogger-profile option:selected').text()||'Blogger'}));$('#jaf-apply').prop('disabled',false);
   });
 }
 $(document).on('click','#jaf-apply',function(){
   var b=$(this),job=b.data('job'); if(!job)return;
   var target=effectivePublishTarget();
   if(target!=='wordpress' && target!=='blogger'){
     ensureWorkflow().html('<div class="jaf-inline-alert is-error">Pilih tujuan Publish terlebih dahulu.</div>');
     return;
   }
   if(window.JapurSuiteConfirm){ window.JapurSuiteConfirm(target==='blogger'?'Upload artikel dan thumbnail ke Blogger sekarang?':'Terapkan artikel dan Publish sekarang?',function(){publishNow(target,b,job);},{title:target==='blogger'?'Publikasi ke Blogger':'Publikasi ke WordPress',confirmText:target==='blogger'?'Upload & Terbitkan':'Terbitkan'}); return; }
   publishNow(target,b,job);
 });
 function publishNow(target,b,job){
   if(b.data('background-job')){ backgroundPublish(String(b.data('background-job')),b); return; }
   b.prop('disabled',true); startWorkflow('publish',target==='blogger'?'Mengirim ke Blogger':'Menerapkan ke WordPress',target==='blogger'?'Mengunggah artikel dan thumbnail ke Blogger.':'Menerapkan field artikel, kategori, tag, dan thumbnail ke WordPress.',10,90);
   if(target==='blogger'){publishJobToBlogger(job);return;}
   var cats=$('#jaf-category input:checked').map(function(){return $(this).val();}).get(), tags=$('#jaf-tags input:checked').map(function(){return $(this).val();}).get();
   $.post(JAF.ajax,{action:'jaf_apply_publish',nonce:JAF.nonce,job:job,categories:cats,tags:tags},function(r){
     if(!r.success){var diag=r.data||{};failWorkflow('publish','Publikasi WordPress gagal','Artikel belum diterbitkan.',diag.message||'Gagal menerapkan artikel.');$('#jaf-publish-card').show().html(publicationDiagnosticCard(diag));b.prop('disabled',false);return;}
     completeWorkflow('publish','Publikasi selesai','Artikel dan thumbnail berhasil diterapkan dan dipublikasikan ke WordPress.');
     $('#jaf-publish-card').show().html(publicationSuccessCard('wordpress',r.data.url||''));
     b.remove();
     hideWorkflowAfterSinglePublish('wordpress',false);
     window.setTimeout(function(){ autoScrollTo('.jaf-publish-success',0); },260);
   }).fail(function(xhr){
     var detail=''; if(xhr&&xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message) detail=xhr.responseJSON.data.message; else if(xhr&&xhr.status) detail='HTTP '+xhr.status;
     failWorkflow('publish','Publikasi WordPress gagal','Server tidak merespons dengan benar.',detail||'Server error saat menerapkan artikel.');$('#jaf-publish-card').show().html(publicationDiagnosticCard({stage:'PUBLISH',http:xhr&&xhr.status?xhr.status:0,message:detail||'Server error saat menerapkan artikel.',target:$('#jaf-wp-profile option:selected').text()||'WordPress'}));b.prop('disabled',false);
   });
 }
 $(document).on('click','.jaf-rebuild',function(){
   resetResultForNewRun();
 });
 $(document).on('click','.jaf-close-success',function(){
   try{sessionStorage.setItem('jaf_scroll_top_after_reload','1');}catch(e){}
   window.location.reload();
 });
 try{
   if(sessionStorage.getItem('jaf_scroll_top_after_reload')==='1'){
     sessionStorage.removeItem('jaf_scroll_top_after_reload');
     window.setTimeout(function(){window.scrollTo(0,0);},80);
   }
 }catch(e){}
 $('#jaf-test-api').on('click',function(){var b=$(this);b.prop('disabled',true);$('#jaf-test').text(' Menguji...');$.post(JAF.ajax,{action:'jaf_test_api',nonce:JAF.nonce},function(r){$('#jaf-test').text(r.success?' OK: API OK':' ERROR: '+(r.data?.message||'Gagal'));}).always(function(){b.prop('disabled',false);});});
});
