var intervalId = 0;
var scanActive = false;


function stopProgressBar() {

  clearInterval(intervalId);
  scanActive = false;
  document.getElementById('ajax_progressbar').style.visibility = 'hidden';
  document.getElementById('progressborder').style.display = 'none';
  document.getElementById('progressbar').style.width = '0';
  el = document.getElementById('statusbar');
  el.style.display = 'none';
  el.innerHTML = '';

}


function pgcRequestProgress() {
  clearInterval(intervalId);
  jQuery.ajax({
   type: "POST",
   url: pgcSettings.plugin_url + '/pgc-ajax.php',
   data: {action: 'showprogress',
           _ajax_nonce: pgcSettings.ajax_nonce
   },
   success: function(msg) {
     if (msg==undefined || msg=='') {
       alert('Error: Empty answer is received');
       return;
     }
     if (msg.indexOf('<pgc>error')<0) {
       beginTag = msg.indexOf('<pgc>');
       endTag = msg.indexOf('</pgc>');
       if (beginTag>=0 && endTag>0) {
         msg = msg.substring(beginTag + 5);
         endTag = msg.indexOf('</pgc>');
         msg = msg.substring(0, endTag);
         if (msg!='100') {
           data = msg.split('&');
           total = data[0];
           current = data[1];
           message = data[2];
           if (total>0) {
             percents = Math.round(100*current/total);
           } else {
             percents = '0';
           }
         } else {
           percents = '100';
           message = 'processing is finished';
         }
         progress = document.getElementById('progressbar');
         progress.innerHTML = percents +'%';
         progress.style.width = percents*3 +'px';
         document.getElementById('statusbar').innerHTML = message;
         if (scanActive) {
          intervalId = setInterval('pgcRequestProgress()', 1000);
         }
       } else {
         stopProgressBar();
         alert('Wrong answer format 2: '+ msg);                  
       }
     } else {       
       stopProgressBar();
       alert(msg);
     }

   },
   error: function(jqXHR, textStatus, errorThrown) {     
     stopProgressBar();
     alert(textStatus +' - '+ errorThrown);
   }
 });

}
// end of pgcRequestProgress()


function pgcScanButtonClick() {
  document.getElementById('scanresults').style.display = 'none';
  scanActive = true;
  document.getElementById('progressborder').style.display = 'block';
  document.getElementById('ajax_progressbar').style.visibility = 'visible';

  el = document.getElementById('statusbar');
  el.innerHTML = 'start scanning...';
  el.style.display = 'block';

  if (document.getElementById('search_nonewp_tables').checked) {
    searchCriteria = 1;
  } else if (document.getElementById('search_wptables_structure_changes').checked) {
    searchCriteria = 2;
  } else {
    searchCriteria = 0;
  }
  if (document.getElementById('show_hidden_tables').checked) {
    showHiddenTables = 1;
  } else {
    showHiddenTables = 0;
  }
  jQuery.ajax({
   type: "POST",
   url: pgcSettings.plugin_url + '/pgc-ajax.php',
   data: {action: 'scandbtables',
          search_criteria: searchCriteria,
          show_hidden_tables: showHiddenTables,
          _ajax_nonce: pgcSettings.ajax_nonce
   },
   success: function(msg){
     if (msg==undefined || msg=='') {
       alert('Error: Empty answer is received');
     } else {
      if (msg.indexOf('<pgc>error')==0) {
        alert(msg);
      } else {
        beginTag = msg.indexOf('<pgc>');
        endTag = msg.indexOf('</pgc>');
        if (beginTag>=0 && endTag>0) {
          msg = msg.substring(beginTag + 5);
          endTag = msg.indexOf('</pgc>');
          msg = msg.substring(0, endTag);
          el = document.getElementById('scanresults');
          el.innerHTML = msg;
          el.style.display = 'block';
        } else {
          alert('Wrong answer format 1: '+ msg);
        }
      }
     }
     stopProgressBar();
   },
   error: function(jqXHR, textStatus, errorThrown) {
     stopProgressBar();     
     alert(textStatus +' - '+ errorThrown);     
   }
 });
  if (scanActive) {
    intervalId = setInterval('pgcRequestProgress()', 3000);
  }
}


function pgc_HideTable(element, tableName) {

ajaxEl = document.getElementById('ajax_'+ tableName);
ajaxEl.style.visibility = 'visible';
if (document.getElementById('hidden_'+ tableName).checked) {
  action = 'hidetable';
} else {
  action = 'showtable';  
}
jQuery.ajax({
   type: "POST",
   url: pgcSettings.plugin_url + '/pgc-ajax.php',
   data: {action: action,
            table_name: tableName,
           _ajax_nonce: pgcSettings.ajax_nonce
   },
   success: function(msg) {
     ajaxEl.style.visibility = 'hidden';
     if (msg==undefined || msg=='') {
       alert('Error: Empty answer is received');
       return;
     }
     if (msg.indexOf('<pgc>error')<0) {
       beginTag = msg.indexOf('<pgc>');
       endTag = msg.indexOf('</pgc>');
       if (beginTag>=0 && endTag>0) {
         msg = msg.substring(beginTag + 5);
         endTag = msg.indexOf('</pgc>');
         msg = msg.substring(0, endTag);
         if (msg!='OK') {
           alert(msg);
         } else if (action=='hidetable') {
          jQuery(element).parent().parent().remove();
          jQuery('#pgc_plugin_tables tbody tr:odd').removeClass('pgc_even');
          jQuery('#pgc_plugin_tables tbody tr:odd').addClass('pgc_odd');
          jQuery('#pgc_plugin_tables tbody tr:even').removeClass('pgc_odd');
          jQuery('#pgc_plugin_tables tbody tr:even').addClass('pgc_even');
         } else if (action=='showtable') {
          // place holder
         }
       } else {
         alert('Wrong answer format 2: '+ msg);
         }
     } else {
       alert(msg);
     }
   },
   error: function(jqXHR, textStatus, errorThrown) {
     stopProgressBar();     
     alert(textStatus +' - '+ errorThrown);     
   }
 });

}

