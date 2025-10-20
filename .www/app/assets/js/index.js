// ==============================
// Master KOHO Dashboard JS
// ==============================
const pages = ['home','accounts','payments','profile','etransfer','cards','statements','rewards','contacts'];
const footerNav = document.getElementById('footerNav');
const actionButtons = document.getElementById('actionButtons');
const sheet = document.getElementById('actionSheet');
const supportChat = document.getElementById('supportChat');
const chatBody = document.getElementById('chatBody');
const chatInput = document.getElementById('chatInput');
const spinnerOverlay = document.getElementById('spinnerOverlay');
const mainContent = document.querySelector('main');

let requestLock = false;
let chatLock = false;
let lastActivity = Date.now();
const logoutTimeout = 30*60*1000; // 30 min
const iframesCache = {};

// -----------------------------
// Preload all pages
// -----------------------------
pages.forEach(page=>{
    const iframe = document.createElement('iframe');
    iframe.src = `pages/${page}.php`;
    iframe.id = `iframe-${page}`;
    iframe.style.visibility = 'hidden';
    iframe.style.position = 'absolute';
    iframe.style.top = 0;
    iframe.style.left = 0;
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.style.border = 'none';
    iframe.style.opacity = 0;
    iframe.style.transition = 'opacity 0.4s';
    mainContent.appendChild(iframe);
    iframesCache[page] = {iframe: iframe, loaded: false};
    iframe.onload = ()=>iframesCache[page].loaded=true;
});

// -----------------------------
// Show initial page (home)
// -----------------------------
setTimeout(()=>{
    const home = iframesCache['home'];
    home.iframe.style.visibility = 'visible';
    home.iframe.style.display = 'block';
    home.iframe.style.opacity = 1;
}, 100);

// -----------------------------
// Footer navigation buttons
// -----------------------------
const navButtons = [
  {page:'home',icon:'fa-house',label:'Home'},
  {page:'accounts',icon:'fa-wallet',label:'Accounts'},
  {action:'sheet',icon:'fa-plus-circle',label:'Actions'},
  {page:'payments',icon:'fa-money-bill-transfer',label:'Payments'},
  {page:'profile',icon:'fa-user',label:'Profile'}
];
footerNav.innerHTML = navButtons.map(btn=>
  `<button onclick="${btn.action==='sheet'?'toggleSheet()':`navigateTo('${btn.page}',event)`}">
     <i class="fa-solid ${btn.icon}"></i><span>${btn.label}</span>
   </button>`
).join('');

// -----------------------------
// Action sheet buttons
// -----------------------------
const actions = [
  {page:'etransfer',icon:'fa-money-bill-transfer',label:'e-Transfer'},
  {page:'cards',icon:'fa-credit-card',label:'Cards'},
  {page:'statements',icon:'fa-file-invoice',label:'Statements'},
  {action:'support',icon:'fa-headset',label:'Support'},
  {page:'rewards',icon:'fa-gift',label:'Rewards'},
  {page:'contacts',icon:'fa-address-book',label:'Contacts'}
];
actionButtons.innerHTML = actions.map(a=>
  `<button class="action" onclick="${a.action==='support'?'toggleSupportChat()':`navigateTo('${a.page}')`}">
     <i class="fa-solid ${a.icon}"></i><span>${a.label}</span>
   </button>`
).join('');
actionButtons.querySelectorAll('button.action').forEach(btn=>btn.addEventListener('click',()=>sheet.classList.remove('open')));

// -----------------------------
// Toggle Action Sheet
// -----------------------------
function toggleSheet(){ sheet.classList.toggle('open'); }

// -----------------------------
// Page Navigation
// -----------------------------
function navigateTo(page, evt){
    if(requestLock) return;
    requestLock = true;

    // Show loader overlay
    spinnerOverlay.style.display = 'flex';
    spinnerOverlay.style.backgroundColor = 'rgba(29,18,60,0.95)';

    // Timeout for network error
    const timeout = setTimeout(()=>{
        spinnerOverlay.style.display='none';
        requestLock=false;
        alert('Network error. Please check your connection.');
    },2000);

    // Hide all iframes
    Object.values(iframesCache).forEach(f=>{
        f.iframe.style.visibility='hidden';
        f.iframe.style.display='none';
        f.iframe.style.opacity=0;
    });

    const obj = iframesCache[page];
    obj.iframe.style.display='block';
    obj.iframe.style.visibility='visible';

    // Wait until iframe loaded or max 1s (to speed up tunnel)
    const checkLoaded = setInterval(()=>{
        if(obj.loaded){
            clearInterval(checkLoaded);
            clearTimeout(timeout);
            obj.iframe.style.opacity=1;
            spinnerOverlay.style.display='none';
            requestLock=false;
        }
    },50);

    // Highlight nav button
    footerNav.querySelectorAll('button').forEach(b=>b.classList.remove('active'));
    if(evt) evt.currentTarget.classList.add('active');
}

// -----------------------------
// Support Chat
// -----------------------------
function toggleSupportChat(){
    supportChat.classList.toggle('active');
    scrollChatBottom();
}
function appendMessage(text,type){
    const d=document.createElement('div');
    d.className='message '+type;
    d.textContent=text;
    chatBody.appendChild(d);
    scrollChatBottom();
}
function scrollChatBottom(){ chatBody.scrollTop = chatBody.scrollHeight; }
function sendMessage(){
    const msg = chatInput.value.trim();
    if(!msg) return;
    appendMessage(msg,'user');
    chatInput.value='';
    if(chatLock) return;
    chatLock=true;
    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`support_message=${encodeURIComponent(msg)}`
    }).finally(()=>{ setTimeout(()=>chatLock=false,1000); });
}
async function fetchMessages(){
    if(chatLock) return;
    chatLock=true;
    try{
        const res = await fetch('?fetch_messages=1');
        const data = await res.json();
        data.forEach(m=>appendMessage(`${m.from}: ${m.text}`,'bot'));
    }catch(e){ console.error(e);}
    finally{ setTimeout(()=>chatLock=false,1000); }
}
setInterval(fetchMessages,1000);

// -----------------------------
// Spinner overlay for forms
// -----------------------------
document.querySelectorAll('form').forEach(f=>{
    f.addEventListener('submit',()=>spinnerOverlay.style.display='flex');
});

// -----------------------------
// Inactivity logout
// -----------------------------
function resetActivity(){ lastActivity = Date.now(); }
['mousemove','keydown','click','touchstart'].forEach(e=>document.addEventListener(e,resetActivity));
setInterval(()=>{
    if(Date.now()-lastActivity>logoutTimeout){
        alert('You have been logged out due to inactivity.');
        window.location.href='logout.php';
    }
},1000);

// -----------------------------
// Prevent iframe scroll bubbling
// -----------------------------
document.querySelectorAll('main iframe').forEach(f=>{
    f.addEventListener('wheel',e=>e.stopPropagation(),{passive:false});
    f.addEventListener('touchmove',e=>e.stopPropagation(),{passive:false});
});
