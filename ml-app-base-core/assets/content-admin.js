
(function(){
  function ready(fn){ if(document.readyState!=='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var wrap = document.querySelector('.mlpb-admin-wrap');
    if(!wrap){ return; }
    var toastArea = document.getElementById('mlpb-toast-area');
    function showToast(message, type){
      if(!toastArea || !message){ return; }
      var toast = document.createElement('div');
      toast.className = 'mlpb-toast mlpb-toast-' + (type || 'success');
      toast.textContent = message;
      toastArea.appendChild(toast);
      requestAnimationFrame(function(){ toast.classList.add('is-visible'); });
      setTimeout(function(){ toast.classList.remove('is-visible'); setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 220); }, 4200);
    }
    var initialToast = wrap.getAttribute('data-toast-message');
    var initialType = wrap.getAttribute('data-toast-type') || 'success';
    if(initialToast){ showToast(initialToast, initialType); }
    document.querySelectorAll('.mlpb-tab-button').forEach(function(button){
      button.addEventListener('click', function(){
        var target = button.getAttribute('data-tab-target');
        if(!target){ return; }
        document.querySelectorAll('.mlpb-tab-button').forEach(function(btn){ btn.classList.remove('is-active'); });
        document.querySelectorAll('.mlpb-tab-panel').forEach(function(panel){ panel.hidden = true; panel.classList.remove('is-active'); });
        button.classList.add('is-active');
        var panel = document.getElementById(target);
        if(panel){ panel.hidden = false; panel.classList.add('is-active'); }
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', target.replace('mlpb-tab-', ''));
          window.history.replaceState({}, '', url.toString());
          var activeInput = document.getElementById('mlab-active-tab');
          if(activeInput){ activeInput.value = button.getAttribute('data-tab-value') || target.replace('mlpb-tab-', ''); }
        } catch(e){}
      });
    });
    document.querySelectorAll('[data-copy-target]').forEach(function(button){
      button.addEventListener('click', function(){
        var selector = button.getAttribute('data-copy-target');
        var field = document.querySelector(selector);
        if(!field){ return; }
        var text = field.value || field.textContent || '';
        navigator.clipboard.writeText(text).then(function(){ showToast('Prompt copiado.', 'success'); }).catch(function(){ showToast('Falha ao copiar.', 'error'); });
      });
    });
  });
})();


(function () {
    const panel = document.querySelector('[data-news-category-selector]');
    if (!panel) return;

    const picker = panel.querySelector('[data-category-picker]');
    const list = panel.querySelector('[data-selected-category-list]');
    const count = panel.querySelector('[data-selected-count]');
    const empty = panel.querySelector('[data-empty-selected]');
    const selectAll = panel.querySelector('.mlpb-select-all-categories');
    const clearAll = panel.querySelector('.mlpb-clear-categories');

    if (!picker || !list) return;

    const updateState = () => {
        const selected = new Set(
            Array.from(list.querySelectorAll('[data-category-slug]')).map((item) => item.getAttribute('data-category-slug'))
        );

        Array.from(picker.options).forEach((option) => {
            if (!option.value) return;
            option.disabled = selected.has(option.value);
        });

        if (count) count.textContent = String(selected.size);
        if (empty) empty.hidden = selected.size > 0;
        picker.value = '';
    };

    const addCategory = (slug, name) => {
        if (!slug || Array.from(list.querySelectorAll('[data-category-slug]')).some((item) => item.getAttribute('data-category-slug') === slug)) {
            updateState();
            return;
        }

        const item = document.createElement('div');
        item.className = 'mlpb-selected-category-item';
        item.setAttribute('data-category-slug', slug);

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'home_news_category_slugs[]';
        input.value = slug;

        const label = document.createElement('span');
        label.textContent = name || slug;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'mlpb-remove-category';
        remove.textContent = 'Remover';
        remove.setAttribute('aria-label', `Remover ${name || slug}`);

        item.appendChild(input);
        item.appendChild(label);
        item.appendChild(remove);
        list.appendChild(item);
        updateState();
    };

    picker.addEventListener('change', () => {
        const option = picker.options[picker.selectedIndex];
        if (!option || !option.value) return;
        addCategory(option.value, option.getAttribute('data-name') || option.textContent.trim());
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('.mlpb-remove-category');
        if (!button) return;
        const item = button.closest('[data-category-slug]');
        if (item) item.remove();
        updateState();
    });

    if (selectAll) {
        selectAll.addEventListener('click', () => {
            Array.from(picker.options).forEach((option) => {
                if (option.value) addCategory(option.value, option.getAttribute('data-name') || option.textContent.trim());
            });
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', () => {
            list.innerHTML = '';
            updateState();
        });
    }

    updateState();
})();
