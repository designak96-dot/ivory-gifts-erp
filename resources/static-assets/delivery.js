document.addEventListener('DOMContentLoaded',()=>{
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
