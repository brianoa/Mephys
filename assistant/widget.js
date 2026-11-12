(function () {
  'use strict';
  document.querySelectorAll('.mephys-assistant').forEach(function (host) {
    var launch = host.querySelector('.mephys-assistant-launch');
    var panel = host.querySelector('.mephys-assistant-panel');
    var close = host.querySelector('.mephys-assistant-close');
    var form = host.querySelector('.mephys-assistant-form');
    var messages = host.querySelector('.mephys-assistant-messages');
    var input = form.elements.message;
    function setOpen(open) {
      panel.hidden = !open;
      launch.setAttribute('aria-expanded', String(open));
      if (open) input.focus(); else launch.focus();
    }
    launch.addEventListener('click', function () { setOpen(panel.hidden); });
    close.addEventListener('click', function () { setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false); });
    function bubble(text, who) {
      var div = document.createElement('div');
      div.className = 'mephys-assistant-bubble ' + who;
      div.textContent = String(text); // No HTML injection from model responses.
      messages.appendChild(div);
      messages.scrollTop = messages.scrollHeight;
      return div;
    }
    host.querySelectorAll('[data-question]').forEach(function (btn) {
      btn.addEventListener('click', function () { input.value = btn.dataset.question; input.focus(); });
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var question = input.value.trim();
      if (!question || form.dataset.busy === '1') return;
      input.value = '';
      bubble(question, 'user');
      var answer = bubble('Thinking…', 'assistant');
      var consent = form.elements.ai_consent.checked;
      form.dataset.busy = '1';
      fetch(host.dataset.tokenEndpoint, {credentials:'same-origin',cache:'no-store'})
        .then(function(res){if(!res.ok)throw Error('Cannot start a chat session');return res.json();})
        .then(function(tokenInfo){return fetch(host.dataset.chatEndpoint, {
          method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({message:question, consent:consent, csrf_token:tokenInfo.csrf_token})
        });})
        .then(function (res) {return res.json().then(function(data){if(!res.ok)throw Error(data.answer || data.message || 'Unable to answer');return data;});})
        .then(function(data){answer.textContent = data.answer + (data.mode === 'guide' ? '\n\n(General information guide)' : '');})
        .catch(function(err){answer.textContent = 'Sorry, I could not answer right now. Please contact Mephys by phone or WhatsApp. ' + err.message;})
        .finally(function(){form.dataset.busy='0';messages.scrollTop=messages.scrollHeight;input.focus();});
    });
  });
})();
