document.addEventListener('DOMContentLoaded',()=>{
    // Quick-dropdown toggle (Overdue / Waiting Deposit / Need Design).
    document.body.addEventListener('click',(e)=>{
        const toggle=e.target.closest('[data-toggle-dropdown]');
        if(toggle){
            const panel=document.getElementById(toggle.dataset.toggleDropdown);
            const isOpen=panel && !panel.hasAttribute('hidden');
            document.querySelectorAll('.quick-dropdown-panel').forEach(p=>p.setAttribute('hidden',''));
            if(panel && !isOpen) panel.removeAttribute('hidden');
            return;
        }
        if(!e.target.closest('.quick-dropdown-panel')) document.querySelectorAll('.quick-dropdown-panel').forEach(p=>p.setAttribute('hidden',''));
    });

    // Inline "Design" change inside the Need Design dropdown — applies
    // the workflow rules immediately (Designed also sets Confirmation to
    // Waiting For Deposit) and updates the visible list/count right away,
    // without a full page reload.
    document.querySelectorAll('[data-need-design-select]').forEach(select=>{
        select.addEventListener('change',async()=>{
            const form=select.closest('[data-need-design-form]');
            const row=select.closest('[data-need-design-row]');
            select.disabled=true;
            try{
                const res=await fetch(form.action,{
                    method:'POST',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('input[name=_token]').value,'X-Requested-With':'XMLHttpRequest'},
                    body:new FormData(form),
                });
                if(!res.ok)throw new Error('Update failed');
                if(select.value==='designed'&&row){
                    row.remove();
                    const btn=document.querySelector('[data-toggle-dropdown="qd-need-design"]');
                    if(btn){
                        const remaining=document.querySelectorAll('[data-need-design-row]').length;
                        btn.textContent=`Need Design — Overdue + Next 10 Days (${remaining})`;
                    }
                    const panel=document.querySelector('[data-need-design-panel]');
                    if(panel && !panel.querySelector('[data-need-design-row]')) panel.innerHTML='<p class="muted" style="padding:10px">No Need Designer orders overdue or in the next 10 days.</p>';
                }
            }catch{select.disabled=false;alert('Could not update — please try again.')}
        });
    });

    const live=document.querySelector('[data-delivery-live]');
    if(live){
        let running=false,failures=0;
        const poll=async()=>{
            if(running||document.hidden)return;
            running=true;
            try{
                const response=await fetch(live.dataset.liveUrl,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
                if(!response.ok)throw new Error('Delivery refresh failed');
                const data=await response.json();
                if(data.version!==live.dataset.version){live.innerHTML=data.html;live.dataset.version=data.version}
                failures=0;
            }catch{failures=Math.min(failures+1,5)}finally{running=false}
        };
        setInterval(poll,12000);
        document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll()});
        window.addEventListener('focus',poll);
    }
    document.querySelectorAll('[data-next-date]').forEach(button=>button.addEventListener('click',async()=>{
        const input=document.querySelector('[data-delivery-date]'),label=document.querySelector('[data-next-date-label]');
        if(!input)return;
        button.disabled=true;
        try{
            const url=new URL(button.dataset.url,location.origin);
            if(input.value)url.searchParams.set('from',input.value);
            const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
            if(!response.ok)throw new Error('Could not check availability');
            const data=await response.json();input.value=data.date;if(label)label.textContent=`Available: ${data.label} · limit ${data.limit} per day`;
        }catch{if(label)label.textContent='Could not check availability. Please try again.'}finally{button.disabled=false}
    }));
});
