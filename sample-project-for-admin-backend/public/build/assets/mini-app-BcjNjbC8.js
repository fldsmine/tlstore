var e=window.Telegram?.WebApp??null;function t(){if(e){e.ready(),e.expand();try{e.setHeaderColor(`#F4F1EA`),e.setBackgroundColor(`#F4F1EA`),e.disableVerticalSwipes?.()}catch{}}}function n(){return e?.initData??``}function r(){try{return e?.initDataUnsafe?.user??null}catch{return null}}function i(){try{return e?.initDataUnsafe?.start_param??null}catch{return null}}function a(t=`light`){try{let n=e?.HapticFeedback;if(!n)return;t===`success`||t===`error`?n.notificationOccurred(t):n.impactOccurred(`light`)}catch{}}var o=null;function s({text:t,visible:n=!0,enabled:r=!0,onClick:i}={}){let a=e?.MainButton;if(a){if(o&&=(a.offClick(o),null),!n){a.hide();return}a.setText(t??`CONTINUE`),a[r?`enable`:`disable`](),i&&(o=i,a.onClick(o)),a.show()}}function c(){try{e?.MainButton?.hide()}catch{}}function l(t){try{t?e?.MainButton?.showProgress():e?.MainButton?.hideProgress()}catch{}}var u=null;function d(t){let n=e?.BackButton;if(n){if(u&&=(n.offClick(u),null),!t){n.hide();return}u=t,n.onClick(u),n.show()}}function f(t){if(e?.openLink)try{e.openLink(t);return}catch{}window.open(t,`_blank`,`noopener`)}function p(t,n=``){if(e?.switchInlineQuery,e?.openTelegramLink){let r=new URLSearchParams({url:t});n&&r.set(`text`,n),e.openTelegramLink(`https://t.me/share/url?${r.toString()}`);return}navigator.share?navigator.share({url:t,text:n}).catch(()=>{}):f(t)}var m=`nextel.miniapp.token`,h=`nextel.miniapp.user`,g=class extends Error{constructor(e,t,n=null){super(t),this.status=e,this.errors=n}fieldMessage(){if(!this.errors)return null;for(let e of Object.values(this.errors))if(Array.isArray(e)&&e.length)return e[0];return null}};function _(){try{return sessionStorage.getItem(m)??localStorage.getItem(m)}catch{return null}}function v(e,t=!1){try{sessionStorage.setItem(m,e),t?localStorage.setItem(m,e):localStorage.removeItem(m)}catch{}}function ee(e){try{sessionStorage.setItem(h,JSON.stringify(e))}catch{}}function te(){try{sessionStorage.removeItem(m),sessionStorage.removeItem(h),localStorage.removeItem(m)}catch{}}var ne=new Set;function re(e){ne.add(e)}async function y(e,{method:t=`GET`,body:n,auth:r=!0}={}){let i={Accept:`application/json`},a=_();n!==void 0&&(i[`Content-Type`]=`application/json`),r&&a&&(i.Authorization=`Bearer ${a}`);let o;try{o=await fetch(`/api/v1${e}`,{method:t,headers:i,body:n===void 0?void 0:JSON.stringify(n)})}catch{throw new g(0,`Network error — check your connection and try again.`)}let s=null;try{s=await o.json()}catch{s=null}if(o.ok&&s?.success)return s.data??null;o.status===401&&r&&(te(),ne.forEach(e=>e()));let c=s?.message??(o.status===429?`Too many attempts — slow down a little.`:`Something went wrong. Please try again.`);throw new g(o.status,c,s?.errors??null)}var ie={home:`/dashboard`,earn:`/dashboard/earnings`,services:`/dashboard`,wallet:`/dashboard/transactions`,profile:`/dashboard/profile`,alerts:`/cloud/inbox`,referrals:`/dashboard/invite`,numbers:`/dashboard/beneficiaries`,website:`/dashboard/my-website`,vas:`/dashboard/vas`,withdraw:`/dashboard/withdraw`,vendor:`/dashboard/agent`,chats:`/dashboard/agent/chats`};async function b(e=null){try{await y(`/web-session`,{method:`POST`})}catch{}window.location.assign(ie[e]??`/dashboard`)}var ae=new Map,x=null;function oe(e){x=e}function S(e,{render:t,tab:n=!1,chrome:r=!0}){ae.set(e,{render:t,tab:n,chrome:r})}async function C(e,t={}){let n=ae.get(e);if(!n||!x)return;let r=document.querySelector(`.ma-tabbar`);r&&(r.style.display=n.chrome?``:`none`),x.classList.toggle(`ma-view-bare`,!n.chrome),document.querySelectorAll(`.ma-tab`).forEach(t=>{t.classList.toggle(`is-active`,t.dataset.route===e)}),x.innerHTML=``,window.scrollTo(0,0);try{await n.render(x,t)}catch(n){console.error(`[mini-app] render failed:`,n),x.innerHTML=`
            <div class="ma-empty">
                <i class="ri-error-warning-line"></i>
                <div class="font-semibold">Something went wrong</div>
                <div class="mt-1 text-sm">${String(n?.message??n)}</div>
                <button class="ma-btn ma-btn-primary mt-4" style="max-width:12rem;margin-left:auto;margin-right:auto" data-retry>Try again</button>
            </div>`,x.querySelector(`[data-retry]`).addEventListener(`click`,()=>C(e,t))}}function w(e=`home`){d(()=>C(e))}function se(){d(null)}var T={user:null,unread:0},ce=new Set;function le(e){ce.add(e)}function ue(){ce.forEach(e=>e(T))}function E(e){T.user=e,ee(e),ue()}async function de(){try{T.unread=(await y(`/notifications/unread-count`))?.unread_count??0,ue()}catch{}}function fe(){T.user=null,T.unread=0,te(),ue()}function D(e){let t=document.createElement(`template`);return t.innerHTML=e.trim(),t.content.firstElementChild}function O(e){return String(e??``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e])}var pe=new Intl.NumberFormat(`en-US`,{style:`currency`,currency:`USD`,minimumFractionDigits:2,maximumFractionDigits:2});function k(e){let t=Number(e);return pe.format(Number.isFinite(t)?t:0)}function A(e){if(!e)return``;let t=new Date(e);if(Number.isNaN(t.getTime()))return``;let n=new Date;return t.toDateString()===n.toDateString()?t.toLocaleTimeString([],{hour:`2-digit`,minute:`2-digit`}):t.toLocaleDateString([],{month:`short`,day:`numeric`})}function me(e,t){return`${e?.[0]??``}${t?.[0]??``}`.trim().toUpperCase()||`?`}function j(e,t=`info`,n=2600){document.querySelectorAll(`.ma-toast`).forEach(e=>e.remove());let r=D(`<div class="ma-toast ${t===`error`?`is-error`:t===`success`?`is-success`:``}">${O(e)}</div>`);document.body.appendChild(r),t===`error`&&a(`error`),t===`success`&&a(`success`),setTimeout(()=>r.remove(),n)}function M(e=4){let t=[56,88,72,72,64,64];return D(`<div aria-hidden="true">${Array.from({length:e},(e,n)=>`<div class="ma-skeleton" style="height:${t[n%t.length]}px;margin-bottom:0.75rem"></div>`).join(``)}</div>`)}function N(e,t,n=``){return D(`<div class="ma-empty"><i class="${e}"></i><div class="font-semibold" style="color:var(--ma-text)">${O(t)}</div><div class="mt-1 text-sm">${O(n)}</div></div>`)}function P(e,t){let n=D(`
        <div class="ma-empty">
            <i class="ri-error-warning-line"></i>
            <div class="font-semibold" style="color:var(--ma-text)">Couldn’t load this</div>
            <div class="mt-1 text-sm">${O(e)}</div>
            <button class="ma-btn ma-btn-primary mt-4" style="max-width:12rem;margin-left:auto;margin-right:auto">Try again</button>
        </div>`);return n.querySelector(`button`).addEventListener(`click`,t),n}function F(e){return e&&typeof e.fieldMessage==`function`?e.fieldMessage()??e.message:e?.message??`Something went wrong. Please try again.`}function I(e){if(!e)return``;let t=new Date(e);return Number.isNaN(t.getTime())?``:t.toDateString()===new Date().toDateString()?`Today`:t.toLocaleDateString([],{month:`short`,day:`numeric`})}function L(e){let t=Number(e);return(Number.isFinite(t)?t:0).toLocaleString(`en-US`,{minimumFractionDigits:2,maximumFractionDigits:2})}function R(e,t=null){let n=D(`
        <div class="ma-appbar">
            <div class="min-w-0 flex-1">
                <div class="ma-heading truncate text-[19px] font-medium leading-tight">${e}</div>
                ${t?`<span class="ma-appbar-badge mt-1 inline-block">${t}</span>`:``}
            </div>
            <button class="ma-bell" data-bell aria-label="Notifications">
                <i class="ri-notification-3-line"></i>
                <span class="ma-badge" data-bell-badge style="display:none">0</span>
            </button>
        </div>`);return n.querySelector(`[data-bell]`).addEventListener(`click`,()=>{a(`light`),C(`alerts`)}),he(n),n}function he(e=document){let t=T.unread??0;e.querySelectorAll(`[data-bell-badge]`).forEach(e=>{e.textContent=t>99?`99+`:String(t),e.style.display=t===0?`none`:`flex`})}le(()=>he());function ge(e=`Enter your transaction PIN`){return new Promise(t=>{let n=D(`
            <div class="ma-sheet-backdrop">
                <div class="ma-sheet">
                    <div class="flex items-center justify-between">
                        <h3 class="ma-heading font-medium text-lg">${O(e)}</h3>
                        <button type="button" data-close class="ma-icon !h-9 !w-9" aria-label="Close"><i class="ri-close-line"></i></button>
                    </div>
                    <input class="ma-input mt-4 text-center text-2xl tracking-[0.5em]" type="password" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••">
                    <div class="ma-hint mt-2 text-center">Your PIN is checked server-side and rate-limited.</div>
                    <button type="button" data-confirm class="ma-btn ma-btn-primary mt-4" disabled>Confirm</button>
                </div>
            </div>`),r=n.querySelector(`input`),i=n.querySelector(`[data-confirm]`),a=!1,o=e=>{a||(a=!0,n.remove(),t(e))};r.addEventListener(`input`,()=>{r.value=r.value.replace(/\D/g,``).slice(0,4),i.disabled=r.value.length!==4}),r.addEventListener(`keydown`,e=>{e.key===`Enter`&&r.value.length===4&&o(r.value)}),i.addEventListener(`click`,()=>o(r.value)),n.querySelector(`[data-close]`).addEventListener(`click`,()=>o(null)),n.addEventListener(`click`,e=>{e.target===n&&o(null)}),document.body.appendChild(n),setTimeout(()=>r.focus(),80)})}async function _e(e){e.innerHTML=``,e.appendChild(R(`Dashboard`,ve())),e.appendChild(M(5));let t=T.user??{};try{let[n,r,i]=await Promise.all([y(`/announcements`).catch(()=>({data:[]})),y(`/wallets`),y(`/packages`)]);e.innerHTML=``,e.appendChild(R(`Dashboard`,ve()));let a=t.first_name??`there`;e.appendChild(D(`
            <div class="mt-1 mb-4">
                <div class="text-[15px] font-semibold">Hey ${O(a)} 👋</div>
                <div class="ma-hint text-[0.8rem]">Welcome back to your Nextel workspace.</div>
            </div>`));let o=Array.isArray(n?.data)?n.data:n?.announcements??[];if(o.length){let t=D(`<div class="no-scrollbar -mx-1 mb-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-1 pb-1"></div>`);o.slice(0,3).forEach((e,n)=>{t.appendChild(D(`
                    <div class="ma-card min-w-[85%] snap-center">
                        ${e.kicker?`<div class="mb-1 text-[0.6rem] font-bold uppercase tracking-[0.18em]" style="color:var(--ma-primary-light)">${O(e.kicker)}</div>`:``}
                        <h3 class="ma-heading text-[0.95rem] font-bold leading-snug">${O(e.title)}</h3>
                        ${e.body?`<p class="ma-hint mt-1 text-[0.8rem] leading-relaxed">${O(be(e.body,110))}</p>`:``}
                        <div class="ma-hint mt-3 flex items-center justify-between text-[0.68rem]">
                            <span>${O(I(e.published_at??e.created_at))}</span>
                            ${o.length>1?`<span>${n+1} / ${Math.min(o.length,3)}</span>`:``}
                        </div>
                    </div>`))}),e.appendChild(t)}e.appendChild(D(`
            <section class="ma-card mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wide">Total balance</div>
                        <div class="ma-heading mt-0.5 text-[26px] font-bold leading-none">${k(r.total_balance_usd)}</div>
                        <div class="ma-hint mt-1.5 text-[0.7rem]">Lifetime earned ${k(r.lifetime_earnings_usd)}</div>
                    </div>
                    <button class="ma-btn ma-btn-primary ma-btn-auto mt-1" data-link="withdraw"><i class="ri-bank-line"></i> Withdraw</button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5">
                    ${ye(r.wallets??[])}
                </div>
            </section>`));let s=[{route:`plans`,icon:`ri-sim-line`,label:`Activate eSIM`},{route:`vas`,icon:`ri-smartphone-line`,label:`Top-up`},{route:`withdraw`,icon:`ri-bank-line`,label:`Withdraw`},{route:`earn`,icon:`ri-pie-chart-2-line`,label:`Shares`},{route:`website`,icon:`ri-global-line`,label:`My Website`},{route:`numbers`,icon:`ri-phone-line`,label:`Calls & SMS`},{route:`numbers`,icon:`ri-user-heart-line`,label:`Beneficiaries`},{route:`chats`,icon:`ri-customer-service-2-line`,label:`Support`}],c=D(`<section class="mb-5 grid grid-cols-4 gap-y-4"></section>`);s.forEach(e=>{c.appendChild(D(`
                <button class="ma-quick" data-link="${e.route}" aria-label="${O(e.label)}">
                    <span class="ma-quick-icon"><i class="${e.icon}"></i></span>
                    <span>${O(e.label)}</span>
                </button>`))}),e.appendChild(c);let l=i?.packages??[];if(l.length){let t=D(`<section class="mb-4"><h3 class="ma-heading mb-2 text-base font-bold">eSIM plans</h3></section>`),n=D(`<div class="no-scrollbar -mx-1 flex gap-3 overflow-x-auto px-1 pb-1"></div>`);l.forEach(e=>{n.appendChild(D(`
                    <div class="ma-card min-w-[215px]">
                        <div class="flex items-center justify-between gap-2">
                            <span class="ma-chip ma-chip-muted">${O(e.name)}</span>
                            ${e.is_featured?`<span class="ma-chip ma-chip-accent">Popular</span>`:``}
                        </div>
                        <div class="ma-heading mt-2 text-xl font-bold">${k(e.price_usd)}</div>
                        <ul class="ma-hint mt-2 space-y-1 text-[0.72rem]">
                            <li><i class="ri-phone-line mr-1"></i>${L(e.daily_call_minutes)} min/day to other networks</li>
                            <li><i class="ri-cloud-line mr-1"></i>${e.cloud_calls?`Cloud calls`:`Standard calls`}${e.cloud_sms?` + SMS`:``}</li>
                            <li><i class="ri-gift-line mr-1"></i>${k(e.direct_referral_bonus_usd)} referral bonus</li>
                        </ul>
                        <button class="ma-btn ma-btn-accent mt-3" data-link="plans">Get this plan</button>
                    </div>`))}),t.appendChild(n),e.appendChild(t)}e.appendChild(D(`
            <section class="rounded-[20px] p-5 text-white" style="background:var(--ma-primary)">
                <h3 class="ma-heading text-lg font-bold leading-snug">Get your Nextel number today.</h3>
                <p class="mt-1 text-[0.82rem] text-white/70">Free calls on Nextel and generous minutes to every other network.</p>
                <button class="mt-3 inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.85rem] font-bold"
                        style="background:var(--ma-secondary);color:var(--ma-primary)" data-link="plans">
                    Buy eSIM plan <i class="ri-arrow-right-up-line"></i>
                </button>
            </section>`))}catch(t){e.innerHTML=``,e.appendChild(R(`Dashboard`,ve())),e.appendChild(P(xe(t),()=>_e(e)));return}e.addEventListener(`click`,e=>{let t=e.target.closest(`[data-link]`);t&&(a(`light`),C(t.dataset.link))})}function ve(){let e=T.user?.package;return e?`<i class="ri-medal-line"></i> ${O(e.name)}`:null}function ye(e){let t=[...e].sort((e,t)=>(t.balance_usd??0)-(e.balance_usd??0)).slice(0,2);return t.length?t.map(e=>`
        <div class="rounded-[14px] border border-black/5 bg-white p-3">
            <div class="ma-hint truncate text-[0.62rem] font-semibold uppercase tracking-wide">${O(e.label)}</div>
            <div class="mt-0.5 truncate text-[0.95rem] font-bold">${k(e.balance_usd)}</div>
        </div>`).join(``):``}function be(e,t){return!e||e.length<=t?e??``:`${e.slice(0,t-1)}…`}function xe(e){return e?.message??`Something went wrong. Please try again.`}async function Se(e,t={}){let n=t?.tab===`portal`?`portal`:`today`;e.innerHTML=``,e.appendChild(R(`Earnings`));let r=D(`
        <div class="mb-4 grid grid-cols-2 gap-1 rounded-2xl p-1" style="background:color-mix(in srgb, var(--ma-muted) 14%, transparent)">
            <button data-tab="today" class="rounded-xl py-2 text-[0.8rem] font-semibold">Daily claims</button>
            <button data-tab="portal" class="rounded-xl py-2 text-[0.8rem] font-semibold">Shareholder Portal</button>
        </div>`),i=e=>{r.querySelectorAll(`[data-tab]`).forEach(t=>{let n=t.dataset.tab===e;t.style.background=n?`var(--ma-surface)`:`transparent`,t.style.color=n?`var(--ma-primary)`:`var(--ma-muted)`})},o=D(`<div></div>`),s=async e=>{i(e),a(`light`),e===`portal`?await Te(o):await z(o)};r.addEventListener(`click`,e=>{let t=e.target.closest(`[data-tab]`);t&&s(t.dataset.tab)}),e.appendChild(r),e.appendChild(o);let c=D(`
        <div class="ma-card mt-4 space-y-1">
            <button class="ma-row w-full" data-go="rewards">
                <span class="ma-icon"><i class="ri-trophy-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Rewards</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Consistency targets and payouts</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-go="ads">
                <span class="ma-icon"><i class="ri-megaphone-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Ads</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Share campaigns for extra earnings</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-go="referrals">
                <span class="ma-icon"><i class="ri-team-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">My Team</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Invite rewards and downline</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
        </div>`);c.addEventListener(`click`,e=>{let t=e.target.closest(`[data-go]`);t&&(a(`light`),C(t.dataset.go))}),e.appendChild(c),await s(n)}async function z(e){e.innerHTML=``,e.appendChild(M(5));let t;try{t=await y(`/earnings`)}catch(t){e.innerHTML=``,e.appendChild(P(F(t),()=>z(e)));return}e.innerHTML=`
        ${t.usage_verified===!1?`<div class="ma-card mb-3 text-[0.72rem]" style="color:var(--ma-muted)"><i class="ri-information-line mr-1"></i>The daily call pool is not independently verified against carrier records.</div>`:``}
        <div class="grid grid-cols-3 gap-2">
            ${Ce(`Today`,k(t.today_earnings_usd))}
            ${Ce(`Claimed`,k(t.claimed_today_usd))}
            ${Ce(`Unclaimed`,k(t.unclaimed_usd))}
        </div>
        <div class="ma-card mt-3 flex items-center justify-between text-sm">
            <span class="ma-muted">Share minutes left this term</span>
            <b>${L(t.share_minutes_remaining)} / ${L(t.share_term_allocated_minutes)}</b>
        </div>
        <div class="ma-hint mt-2 text-center text-[0.7rem]">Available to claim now: ${L(t.share_minutes_available_now)} minutes · unused accrued minutes roll forward.</div>
        <div class="ma-hint mt-2 text-center text-[0.7rem]">Daily cap remaining: ${k(t.daily_earning_cap_remaining_usd)} of ${k(t.daily_earning_cap_usd)}</div>
        ${Number(t.unclaimed_usd??0)>0?`<button data-claim-all class="ma-btn ma-btn-primary mt-3 w-full">Claim all available</button>`:``}
        <div id="records" class="mt-3"></div>`;let n=e.querySelector(`[data-claim-all]`);n?.addEventListener(`click`,async()=>{n.disabled=!0,n.textContent=`Claiming…`;try{let t=await y(`/earnings/claim-all`,{method:`POST`}),n=Number(t?.amount_usd??0);if(n>0){a(`success`);let e=Number(t.records_claimed??0);j(`Claimed ${k(n)} across ${e} call${e===1?``:`s`} 🎉`,`success`)}else j(`No eligible call earnings were available to claim.`,`info`);await z(e)}catch(e){j(F(e),`error`),n.disabled=!1,n.textContent=`Claim all available`}});let r=e.querySelector(`#records`),i=t.records??[];if(!i.length){r.appendChild(N(`ri-phone-line`,`No pool records yet`,`Generated daily-pool entries will appear here. They are not independently verified carrier usage.`));return}i.forEach(t=>r.appendChild(we(t,e)))}function Ce(e,t){return`<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${O(e)}</div><div class="mt-0.5 text-sm font-bold">${t}</div></div>`}function we(e,t){let n=e.claimed,r=e.can_claim,i={already_claimed:`Already claimed`,share_plan_inactive:`Active share plan required`,call_type_not_in_package:`Not in your package`,term_minutes_exhausted:`No share minutes left`,share_minutes_not_accrued:`Next share-minute allowance has not accrued`,no_claimable_amount:`No claimable amount`,daily_earning_cap:`No claim fits the remaining daily cap`,call_record_expired:`Record expired`}[e.blocked_reason]??`Cannot claim right now`,o=Number(e.duration_minutes??0),s=Number(n?e.claimed_minutes??0:e.claimable_minutes??0),c=s>0&&s+.001<o?` · partial ${L(s)} of ${L(o)} min`:o>0?` · ${L(o)} min`:``,l=D(`
        <div class="ma-row">
            <div class="ma-icon"><i class="ri-phone-fill"></i></div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">${O(e.type)} · ${O(e.network??``)}</div>
                <div class="ma-hint text-xs">${O(A(e.occurred_at))}${c}${n?` · claimed`:``}</div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold">${k(e.amount_usd)}</div>
            </div>
        </div>`);if(!n){let n=D(`<button class="ma-btn ${r?`ma-btn-primary`:``} ma-btn-sm mt-2" ${r?``:`disabled`}>
            ${r?`Claim`:O(i)}
        </button>`);r&&n.addEventListener(`click`,async()=>{n.disabled=!0,n.textContent=`Claiming…`;try{let n=await y(`/earnings/${e.id}/claim`,{method:`POST`});a(`success`);let r=Number(n.claimed_minutes??0)+.001<Number(n.pool_duration_minutes??0)?` for ${L(n.claimed_minutes)} of ${L(n.pool_duration_minutes)} min`:``;j(`Claimed ${k(n.amount_usd)}${r} 🎉`,`success`),await z(t)}catch(e){j(F(e),`error`),n.disabled=!1,n.textContent=`Claim`}});let o=D(`<div class="mb-1"></div>`);return o.appendChild(l),o.appendChild(n),o}return l}async function Te(e){e.innerHTML=``,e.appendChild(M(5));let t;try{t=await y(`/earnings/portal`)}catch(t){e.innerHTML=``,e.appendChild(P(F(t),()=>Te(e)));return}e.innerHTML=`
        ${t.usage_verified===!1?`<div class="ma-card mb-3 text-[0.72rem]" style="color:var(--ma-muted)"><i class="ri-information-line mr-1"></i>The daily call pool is not independently verified against carrier records.</div>`:``}
        <div class="ma-card mb-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wide">Claimable now</div>
                    <div class="ma-heading mt-0.5 text-2xl font-bold">${k(t.total_claimable_usd)}</div>
                </div>
                <div class="text-right">
                    <div class="ma-hint text-[0.64rem] uppercase">Claimed today</div>
                    <div class="text-sm font-bold" style="color:var(--ma-success)">${k(t.total_claimed_usd)}</div>
                </div>
            </div>
            <div class="ma-hint mt-3 flex items-center justify-between text-[0.72rem]">
                <span><i class="ri-time-line mr-1"></i>${L(t.share_minutes_remaining)} of ${L(t.share_term_allocated_minutes)} share minutes left this term</span>
                ${t.package_name?`<span class="ma-chip ma-chip-accent">${O(t.package_name)}</span>`:``}
            </div>
            <div class="ma-hint mt-1 text-[0.68rem]">Available to claim now: ${L(t.share_minutes_available_now)} minutes</div>
            <div class="ma-hint mt-1 text-[0.68rem]">Partial claims use the remaining share minutes or daily-cap room, with payouts reduced proportionally.</div>
        </div>
        <div id="portal-cards"></div>
        <p class="ma-hint mt-3 text-center text-[0.72rem]">Claim individual calls from the Daily claims tab.</p>`;let n=e.querySelector(`#portal-cards`),r=t.cards??[];if(!r.length){n.appendChild(N(`ri-pie-chart-2-line`,`No call types yet`,`Pool activity appears here once calls flow.`));return}r.forEach(e=>{n.appendChild(D(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="ma-icon"><i class="ri-phone-box-line"></i></span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold">${O(e.name)}</div>
                            <div class="ma-hint text-[0.7rem]">${e.in_package?`In package · ${L(e.share_percent)}% share`:`Not in your package · ${L(e.share_percent)}% pool share`}</div>
                        </div>
                    </div>
                    ${e.in_package?`<span class="ma-chip ma-chip-success">Yours</span>`:`<span class="ma-chip ma-chip-muted">Pool</span>`}
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${e.calls}</div>
                        <div class="ma-hint text-[0.62rem]">Calls</div>
                    </div>
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${L(e.minutes)}</div>
                        <div class="ma-hint text-[0.62rem]">Minutes</div>
                    </div>
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${k(e.pool_earnings_usd)}</div>
                        <div class="ma-hint text-[0.62rem]">Pool</div>
                    </div>
                </div>
                <div class="ma-hint mt-2.5 flex items-center justify-between text-[0.72rem]">
                    <span>Claimable <b style="color:var(--ma-primary)">${k(e.claimable_usd)}</b></span>
                    <span>Claimed <b>${k(e.claimed_usd)}</b></span>
                </div>
            </div>`))})}var Ee=[{route:`plans`,icon:`ri-sim-line`,title:`Shares & Plans`,desc:`eSIM tiers, activation and upgrade tokens`},{route:`paylater`,icon:`ri-timer-flash-line`,title:`Pay Later`,desc:`Activate minutes now, settle with a token`},{route:`loan`,icon:`ri-hand-coin-line`,title:`Loan`,desc:`Request float and track repayments`},{route:`vas`,icon:`ri-shopping-bag-3-line`,title:`VAS & Bills`,desc:`Airtime, data, TV and electricity top-ups`},{route:`website`,icon:`ri-global-line`,title:`My Website`,desc:`Renew your Nextel website subscription`},{route:`chats`,icon:`ri-customer-service-2-line`,title:`Live Chats`,desc:`Talk to your vendor or Nextel support`}];async function De(e){let t=T.user??{};e.innerHTML=``,e.appendChild(R(`Services`));let n=D(`<div class="space-y-3"></div>`);if(Ee.forEach(e=>{n.appendChild(D(`
            <button class="ma-card flex w-full items-center gap-3 text-left" data-route="${e.route}">
                <span class="ma-icon"><i class="${e.icon}"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">${O(e.title)}</span>
                    <span class="ma-hint block truncate text-[0.75rem]">${O(e.desc)}</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>`))}),e.appendChild(n),t.is_agent){let t=D(`
            <button class="ma-card mt-3 flex w-full items-center gap-3 text-left" data-route="vendor"
                    style="background:var(--ma-primary);color:#fff">
                <span class="ma-icon" style="background:rgba(255,255,255,0.12);border-color:transparent;color:var(--ma-secondary)"><i class="ri-vip-crown-line"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">Vendor Dashboard</span>
                    <span class="block truncate text-[0.75rem] text-white/60">Mint tokens, manage batches and float</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg text-white/60"></i>
            </button>`);e.appendChild(t)}e.appendChild(D(`
        <p class="ma-hint mt-4 text-center text-[0.72rem]">
            Debit cards are issued on the web dashboard.<br>Everything else is right here.
        </p>`)),e.addEventListener(`click`,e=>{let t=e.target.closest(`[data-route]`);t&&(a(`light`),C(t.dataset.route))})}var Oe=[{id:`all`,label:`All`},{id:`earned`,label:`Earned`},{id:`pending`,label:`Pending`},{id:`withdrawn`,label:`Withdrawn`}],B=`all`,V=1,ke=1,Ae=!1;async function je(e){B=`all`,V=1,await Me(e)}async function Me(e){e.innerHTML=``,e.appendChild(R(`Wallet`)),e.appendChild(M(6));let t,n=null;try{t=await y(`/wallets`),n=(await y(`/bank-account`).catch(()=>null))?.bank_account??null}catch(t){e.innerHTML=``,e.appendChild(R(`Wallet`)),e.appendChild(P(F(t),()=>Me(e)));return}let r=D(`
        <div>
            <div class="ma-card text-center">
                <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wider">Total balance</div>
                <div class="ma-heading mt-1 text-3xl font-bold">${k(t.total_balance_usd)}</div>
                <div class="ma-hint mt-1 text-xs">Lifetime earned: ${k(t.lifetime_earnings_usd)}</div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2.5">
                <button class="ma-btn ma-btn-primary" data-go="withdraw"><i class="ri-bank-line"></i> Withdraw</button>
                <button class="ma-btn" data-go="numbers"><i class="ri-phone-line"></i> Beneficiaries</button>
            </div>

            <button class="ma-card ma-card-tight mt-3 flex w-full items-center gap-3 text-left" data-go="withdraw">
                <span class="ma-icon"><i class="ri-bank-card-line"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[0.82rem] font-semibold">${n?`Payout: ${O(n.account_name||n.masked_number||`Saved account`)}`:`Set up your payout account`}</span>
                    <span class="ma-hint block truncate text-[0.7rem]">${n?`${O(n.bank?.name??`Bank`)} · ${O(n.masked_number??``)}`:`Bank or mobile money — needed before withdrawing`}</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>

            <div class="mt-3 grid grid-cols-2 gap-2" id="balances"></div>

            <div class="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1" id="filters"></div>
            <div id="transactions" class="mt-1"></div>
            <div id="more"></div>
        </div>`);e.innerHTML=``,e.appendChild(R(`Wallet`)),e.appendChild(r);let i=r.querySelector(`#balances`);(t.wallets??[]).forEach(e=>{i.appendChild(D(`
            <div class="ma-card ma-card-tight">
                <div class="ma-hint truncate text-[0.63rem] font-semibold uppercase">${O(e.label)}</div>
                <div class="mt-0.5 truncate text-sm font-bold">${k(e.balance_usd)}</div>
            </div>`))});let o=r.querySelector(`#filters`);Oe.forEach(e=>{let t=D(`<button class="ma-chip ${e.id===B?`ma-chip-accent`:`ma-chip-muted`} whitespace-nowrap" data-filter="${e.id}">${e.label}</button>`);t.addEventListener(`click`,()=>{B=e.id,V=1,a(`light`),H(r)}),o.appendChild(t)}),r.addEventListener(`click`,e=>{let t=e.target.closest(`[data-go]`);t&&(a(`light`),C(t.dataset.go))}),await H(r,!0)}async function H(e,t=!1){let n=e.querySelector(`#transactions`),r=e.querySelector(`#more`);if(!n)return;t&&(n.innerHTML=``,r.innerHTML=``);let i;try{i=await y(`/transactions?filter=${encodeURIComponent(B)}&page=${V}&per_page=20`)}catch(r){t&&n.appendChild(P(F(r),()=>H(e,!0)));return}let a=i.items??[];if(ke=i.pagination?.last_page??1,t&&(e.querySelectorAll(`[data-filter]`).forEach(e=>{let t=e.dataset.filter===B;e.classList.toggle(`ma-chip-accent`,t),e.classList.toggle(`ma-chip-muted`,!t)}),!a.length)){n.appendChild(N(`ri-exchange-line`,`No transactions`,`Your activity will show up here.`));return}if(a.forEach(e=>n.appendChild(Ne(e))),r.innerHTML=``,V<ke){let t=D(`<button class="ma-btn mt-3">Load more</button>`);t.addEventListener(`click`,async()=>{Ae||=(Ae=!0,t.disabled=!0,t.textContent=`Loading…`,V+=1,await H(e),!1)}),r.appendChild(t)}}function Ne(e){let t=!!e.is_credit;return D(`
        <div class="ma-row">
            <div class="ma-icon" style="${t?``:`background:color-mix(in srgb, var(--ma-danger) 12%, transparent);color:var(--ma-danger);border-color:transparent`}">
                <i class="${t?`ri-arrow-down-circle-line`:`ri-arrow-up-circle-line`}"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">${O(e.type_name??e.type??`Transaction`)}</div>
                <div class="ma-hint truncate text-xs">${O(e.description??e.reference??``)} · ${O(A(e.created_at))}</div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold" style="color:${t?`var(--ma-success)`:`var(--ma-danger)`}">
                    ${t?`+`:`−`}${k(e.amount_usd)}
                </div>
                <div class="ma-hint text-[0.65rem]">${O(e.wallet_label??``)}</div>
            </div>
        </div>`)}async function Pe(e){e.innerHTML=``,e.appendChild(R(`Profile`)),e.appendChild(M(4));let t;try{t=await y(`/user`)}catch(t){e.innerHTML=``,e.appendChild(R(`Profile`)),e.appendChild(P(t.message,()=>Pe(e)));return}let n=t.user??{},r=n.onboarding??{},i=n.telegram??null;E(n),e.innerHTML=``,e.appendChild(R(`Profile`,n.package?O(n.package.name):null));let o=Fe();e.appendChild(o),o.innerHTML=`
        <div class="ma-card flex items-center gap-3">
            ${n.avatar_url?`<img src="${O(n.avatar_url)}" alt="" class="h-14 w-14 rounded-full object-cover">`:`<div class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background:var(--ma-accent);color:var(--ma-accent-text)">${O(me(n.first_name,n.last_name))}</div>`}
            <div class="min-w-0 flex-1">
                <div class="truncate font-bold">${O(n.full_name??``)}</div>
                <div class="ma-hint truncate text-xs">@${O(n.username)} · ${O(n.email??``)}</div>
                <div class="ma-hint truncate text-xs">${O(n.phone??``)} · ${O(n.country??``)}</div>
            </div>
        </div>

        <div class="ma-card mt-3">
            <div class="ma-hint mb-2 text-xs font-semibold uppercase tracking-wider">Account setup</div>
            ${Ie(`Email verified`,r.email_verified)}
            ${Ie(`Package activated`,r.activated)}
            ${Ie(`Transaction PIN`,r.pin_set)}
        </div>

        <div class="ma-card mt-3 space-y-1">
            <button class="ma-row w-full" data-open="settings">
                <span class="ma-icon"><i class="ri-settings-3-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Profile & Settings</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Name, photo, currency, password, PIN</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-open="referrals">
                <span class="ma-icon"><i class="ri-team-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">My Team</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Referrals and invite rewards</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            ${n.is_agent?`
            <button class="ma-row w-full" data-open="vendor">
                <span class="ma-icon"><i class="ri-vip-crown-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Vendor Dashboard</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Mint tokens, transfers, float</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>`:``}
        </div>

        <div class="ma-card mt-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold"><i class="ri-telegram-line"></i> Telegram sign-in</div>
                    <div class="ma-hint mt-0.5 text-xs">${i?.linked?`Linked${i.username?` to @`+O(i.username):``} — instant open next time.`:`Not linked — you log in with password each time.`}</div>
                </div>
                ${i?.linked?`<button class="ma-btn ma-btn-danger ma-btn-sm" id="unlink">Unlink</button>`:`<span class="ma-chip ma-chip-muted">Off</span>`}
            </div>
        </div>

        <button class="ma-btn ma-btn-danger mt-4" id="logout"><i class="ri-logout-box-r-line"></i> Log out</button>`,o.addEventListener(`click`,e=>{let t=e.target.closest(`[data-open]`);t&&(a(`light`),C(t.dataset.open))}),o.querySelector(`#unlink`)?.addEventListener(`click`,async t=>{let n=t.currentTarget;n.disabled=!0;try{await y(`/telegram/unlink`,{method:`POST`}),a(`success`),j(`Telegram unlinked`,`success`),await Pe(e)}catch(e){j(e.message,`error`),n.disabled=!1}}),o.querySelector(`#logout`).addEventListener(`click`,async()=>{try{await y(`/logout`,{method:`POST`})}catch{}fe(),te(),await C(`login`)})}function Fe(){return document.createElement(`div`)}function Ie(e,t){return`
        <div class="flex items-center justify-between py-1.5 text-sm" style="border-bottom:1px solid color-mix(in srgb, var(--ma-text) 6%, transparent)">
            <span>${O(e)}</span>
            <i class="${t?`ri-checkbox-circle-fill`:`ri-close-circle-line`}" style="color:${t?`var(--ma-success)`:`var(--ma-hint)`}"></i>
        </div>`}var U=1,Le=1,Re=!1;async function ze(e){w(`home`),U=1,await Be(e)}async function Be(e){e.innerHTML=`
        <div class="flex items-center justify-between py-2">
            <h1 class="text-xl font-bold">Alerts</h1>
            <button class="ma-btn ma-btn-ghost ma-btn-sm text-xs" id="read-all"><i class="ri-check-double-line"></i> Read all</button>
        </div>
        <div id="list"></div>
        <div id="more"></div>`,e.querySelector(`#read-all`).addEventListener(`click`,async t=>{let n=t.currentTarget;n.disabled=!0;try{await y(`/notifications/read-all`,{method:`POST`}),a(`success`),await de(),await W(e.querySelector(`#list`),!0)}catch(e){j(e.message,`error`),n.disabled=!1}}),await W(e.querySelector(`#list`),!0)}async function W(e,t=!1){if(!e)return;t&&(U=1,e.innerHTML=``,e.appendChild(M(4)));let n;try{n=await y(`/notifications?page=${U}&per_page=20`)}catch(n){t&&(e.innerHTML=``,e.appendChild(P(n.message,()=>W(e,!0))));return}t&&(e.innerHTML=``);let r=n.items??[];if(Le=n.pagination?.last_page??1,t&&!r.length){e.appendChild(N(`ri-notification-3-line`,`All caught up`,`Notifications from calls, payments and the team land here.`));return}r.forEach(t=>e.appendChild(Ve(t,e)));let i=e.parentElement.querySelector(`#more`);if(i.innerHTML=``,U<Le){let t=D(`<button class="ma-btn mt-3">Load more</button>`);t.addEventListener(`click`,async()=>{Re||=(Re=!0,t.disabled=!0,t.textContent=`Loading…`,U+=1,await W(e),!1)}),i.appendChild(t)}}function Ve(e,t){let n=!e.read_at,r=D(`
        <div class="ma-row ${n?``:`opacity-60`}" style="cursor:pointer">
            <div class="ma-icon" style="${n?``:`background:color-mix(in srgb, var(--ma-text) 8%, transparent);color:var(--ma-hint)`}">
                <i class="${e.is_sponsored?`ri-megaphone-line`:`ri-notification-2-line`}"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm ${n?`font-bold`:`font-semibold`}">${O(e.subject??e.sender_name??`Notification`)}</div>
                <div class="ma-hint mt-0.5 line-clamp-2 text-xs">${O(e.body??``)}</div>
                <div class="ma-hint mt-0.5 text-[0.65rem]">${A(e.created_at)}</div>
            </div>
            ${n?`<span class="h-2.5 w-2.5 flex-none rounded-full" style="background:var(--ma-accent)"></span>`:``}
        </div>`);return r.addEventListener(`click`,async()=>{if(n)try{await y(`/notifications/${e.id}/read`,{method:`POST`}),r.classList.remove(`font-bold`),r.classList.add(`opacity-60`),r.querySelector(`.rounded-full`)?.remove(),await de()}catch{}}),r}async function He(e){w(`home`),await Ue(e)}async function Ue(e){e.innerHTML=``,e.appendChild(M(4));let t;try{t=await y(`/ads`)}catch(t){e.innerHTML=``,e.appendChild(P(t.message,()=>Ue(e)));return}e.innerHTML=`
        <h1 class="py-2 text-xl font-bold">Share &amp; Earn</h1>
        <div class="grid grid-cols-3 gap-2">
            ${We(`Today`,t.today_usd)}
            ${We(`This week`,t.week_usd)}
            ${We(`Lifetime`,t.lifetime_usd)}
        </div>
        <div id="ads" class="mt-4"></div>`;let n=e.querySelector(`#ads`);if(t.enabled===!1){n.appendChild(N(`ri-megaphone-line`,`Ads are paused`,`Check back later — sharing will resume soon.`));return}let r=t.ads??[];if(!r.length){n.appendChild(N(`ri-megaphone-line`,`No ads right now`,`New campaigns appear here when advertisers launch them.`));return}r.forEach(t=>n.appendChild(Ge(t,e)))}function We(e,t){return`<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${e}</div><div class="mt-0.5 text-sm font-bold">${k(t)}</div></div>`}function Ge(e,t){let n=!!e.earned_current,r=D(`
        <div class="ma-card mb-3">
            <div class="flex items-start gap-3">
                ${e.image_url?`<img src="${O(e.image_url)}" alt="" class="h-12 w-12 flex-none rounded-xl object-cover" loading="lazy">`:`<div class="ma-icon"><i class="ri-megaphone-line"></i></div>`}
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${O(e.name)}</div>
                    <div class="ma-hint text-xs">${O(e.advertiser??``)}</div>
                    <div class="mt-1 text-xs font-bold" style="color:var(--ma-success)">+${k(e.reward_usd)}</div>
                </div>
                ${n?`<span class="ma-chip ma-chip-success">Earned</span>`:``}
            </div>
            ${e.description?`<p class="ma-hint mt-2 text-xs">${O(e.description)}</p>`:``}
            <div class="mt-3 grid grid-cols-2 gap-2">
                ${e.target_url?`<button class="ma-btn ma-btn-sm" data-open><i class="ri-external-link-line"></i> View</button>`:``}
                <button class="ma-btn ma-btn-primary ma-btn-sm" data-share ${n?`disabled`:``}>
                    ${n?`Already earned`:`I shared it`}
                </button>
            </div>
        </div>`);r.querySelector(`[data-open]`)?.addEventListener(`click`,()=>f(e.target_url));let i=r.querySelector(`[data-share]`);return n||i.addEventListener(`click`,async()=>{i.disabled=!0,i.textContent=`Recording…`;try{let n=await y(`/ads/${e.id}/share`,{method:`POST`});a(`success`),j(`+${k(n.amount_usd)} added to Ads Revenue`,`success`),await Ue(t)}catch(e){j(e instanceof g?e.fieldMessage()??e.message:e.message,`error`),i.disabled=!1,i.textContent=`I shared it`}}),r}async function Ke(e){w(`home`),await G(e)}async function G(e){e.innerHTML=``,e.appendChild(M(4)),c();let t;try{t=await y(`/rewards`)}catch(t){e.innerHTML=``,e.appendChild(P(t.message,()=>G(e)));return}e.innerHTML=`
        <h1 class="py-2 text-xl font-bold">Target Rewards</h1>
        <div class="grid grid-cols-3 gap-2">
            ${qe(`Streak`,`${t.streak??0}d`)}
            ${qe(`Lifetime`,k(t.lifetime_earned_usd))}
            ${qe(`Bonus`,`${Number(t.reward_percent??0).toFixed(0)}%`)}
        </div>
        <div id="today" class="mt-4"></div>
        <h2 class="ma-muted mb-2 mt-6 text-sm font-bold uppercase tracking-wide">History</h2>
        <div id="history"></div>`;let n=e.querySelector(`#today`),r=e.querySelector(`#history`);if(t.enabled===!1){n.appendChild(N(`ri-trophy-line`,`Rewards are paused`,`Daily targets will return soon.`));return}t.today_target?n.appendChild(Je(t.today_target,e)):n.appendChild(Ye(t,e));let i=t.history??[];if(!i.length){r.appendChild(N(`ri-history-line`,`No history yet`,`Set your first daily target above.`));return}i.forEach(t=>r.appendChild(Xe(t,e)))}function qe(e,t){return`<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${e}</div><div class="mt-0.5 text-sm font-bold">${O(t)}</div></div>`}function Je(e,t){let n=e.status===`achieved`||e.status===`claimed`,r=e.status===`claimed`,i=Number(e.progress_usd??0),o=Number(e.target_amount_usd??0),s=o>0?Math.min(100,Math.round(i/o*100)):0,c=D(`
        <div class="ma-card">
            <div class="flex items-center justify-between">
                <span class="ma-hint text-xs uppercase tracking-wider">Today’s target</span>
                <span class="ma-chip ${r?`ma-chip-muted`:n?`ma-chip-success`:`ma-chip-muted`}">${O(e.status)}</span>
            </div>
            <div class="mt-2 text-2xl font-bold">${k(i)} <span class="ma-muted text-sm font-normal">of ${k(o)}</span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full" style="background:color-mix(in srgb, var(--ma-text) 10%, transparent)">
                <div class="h-full rounded-full" style="width:${s}%;background:${n?`var(--ma-success)`:`var(--ma-accent)`}"></div>
            </div>
            <div class="mt-2 ma-hint text-xs">Hit ${k(o)} today in spendable earnings to win <b>${k(e.reward_amount_usd)}</b>.</div>
        </div>`);if(n&&!r){let n=D(`<button class="ma-btn ma-btn-primary mt-3">Claim reward</button>`);n.addEventListener(`click`,async()=>{n.disabled=!0,n.textContent=`Claiming…`;try{await y(`/rewards/${e.id}/claim`,{method:`POST`}),a(`success`),j(`Reward claimed 🎉`,`success`),await G(t)}catch(e){j(e instanceof g?e.fieldMessage()??e.message:e.message,`error`),n.disabled=!1,n.textContent=`Claim reward`}}),c.appendChild(n)}return c}function Ye(e,t){let n=Number(e.min_target_usd??1),r=Number(e.max_target_usd??100),i=D(`
        <div class="ma-card">
            <div class="font-semibold">Set today’s target</div>
            <p class="ma-hint mt-1 text-xs">Earn between ${k(n)} and ${k(r)} today and get a bonus of ${Number(e.reward_percent??0).toFixed(0)}% of the target.</p>
            <input class="ma-input mt-3" type="number" inputmode="decimal" min="${n}" max="${r}" step="0.01" placeholder="Target amount (USD)" id="target-amount">
        </div>`),o=i.querySelector(`#target-amount`);return s({text:`SET TARGET`,visible:!0,onClick:async()=>{let e=parseFloat(o.value);if(!Number.isFinite(e)||e<n||e>r){j(`Enter an amount between ${k(n)} and ${k(r)}`,`error`);return}l(!0);try{await y(`/rewards/target`,{method:`POST`,body:{amount:e}}),a(`success`),j(`Target set — go earn!`,`success`),await G(t)}catch(e){j(e instanceof g?e.fieldMessage()??e.message:e.message,`error`)}finally{l(!1)}}}),i}function Xe(e,t){let n=e.status===`claimed`,r=e.status===`achieved`,i=D(`
        <div class="ma-row">
            <div class="ma-icon"><i class="ri-trophy-line"></i></div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold">${O(e.date)}</div>
                <div class="ma-hint text-xs">${k(e.progress_usd)} / ${k(e.target_amount_usd)}</div>
            </div>
            ${n?`<span class="ma-chip ma-chip-muted">Claimed</span>`:r?`<button class="ma-btn ma-btn-primary ma-btn-sm text-xs" data-claim>Claim ${k(e.reward_amount_usd)}</button>`:`<span class="ma-chip ma-chip-muted">Missed</span>`}
        </div>`);return i.querySelector(`[data-claim]`)?.addEventListener(`click`,async n=>{let r=n.currentTarget;r.disabled=!0;try{await y(`/rewards/${e.id}/claim`,{method:`POST`}),a(`success`),j(`Reward claimed 🎉`,`success`),await G(t)}catch(e){j(e instanceof g?e.fieldMessage()??e.message:e.message,`error`),r.disabled=!1}}),i}async function Ze(e){w(`home`),await Qe(e)}async function Qe(e){e.innerHTML=``,e.appendChild(M(4));let t;try{t=await y(`/referrals`)}catch(t){e.innerHTML=``,e.appendChild(P(t.message,()=>Qe(e)));return}e.innerHTML=`
        <h1 class="py-2 text-xl font-bold">Referrals</h1>
        <div class="ma-card text-center">
            <div class="ma-hint text-xs uppercase tracking-wider">Your code</div>
            <div class="mt-1 text-2xl font-bold tracking-widest">${O(t.code)}</div>
            <div class="ma-hint mt-1 text-xs">Earn ${k(t.earned_usd)} so far from your team</div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">Direct</div><div class="mt-0.5 text-lg font-bold">${t.direct_count}</div></div>
            <div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">Indirect</div><div class="mt-0.5 text-lg font-bold">${t.indirect_count}</div></div>
        </div>
        <button class="ma-btn ma-btn-primary mt-3" id="share"><i class="ri-share-forward-line"></i> Invite friends</button>
        <button class="ma-btn mt-2" id="copy"><i class="ri-link"></i> Copy link</button>
        <h2 class="ma-muted mb-1 mt-5 text-sm font-bold uppercase tracking-wide">Direct team</h2>
        <div id="direct"></div>
        <h2 class="ma-muted mb-1 mt-4 text-sm font-bold uppercase tracking-wide">Indirect team</h2>
        <div id="indirect"></div>`;let n=t.link;e.querySelector(`#share`).addEventListener(`click`,()=>{a(`light`),p(n,`Join me on Nextel — earn from every call. Use my code: `+t.code)}),e.querySelector(`#copy`).addEventListener(`click`,async()=>{try{await navigator.clipboard.writeText(n),a(`success`),j(`Link copied`,`success`)}catch{j(`Could not copy — long-press the link instead`,`error`)}}),$e(e.querySelector(`#direct`),t.direct??[]),$e(e.querySelector(`#indirect`),t.indirect??[])}function $e(e,t){if(!t.length){e.appendChild(D(`<div class="ma-hint py-3 text-sm">No one here yet.</div>`));return}t.forEach(t=>{let n=[t.first_name,t.last_name].filter(Boolean).join(` `)||t.username;e.appendChild(D(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-user-line"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${O(n)}</div>
                    <div class="ma-hint text-xs">@${O(t.username)}</div>
                </div>
                <span class="ma-chip ${t.package_name?`ma-chip-success`:`ma-chip-muted`}">${O(t.package_name??`Joined`)}</span>
            </div>`))})}var et={NG:`+234`,GH:`+233`,CM:`+237`,ZA:`+27`,KE:`+254`,UG:`+256`,SL:`+232`,MW:`+265`,ZM:`+260`,LR:`+231`,GM:`+220`,TZ:`+255`,RW:`+250`,BW:`+267`,ZW:`+263`,CI:`+225`,BF:`+226`,TG:`+228`,SN:`+221`,BJ:`+229`,ML:`+223`,NE:`+227`};function K({title:e,subtitle:t,backTo:n=null,centered:r=!1}={}){let i=D(`
        <div class="ma-auth">
            <span class="ma-auth-circle" style="top:-90px;right:-110px;width:230px;height:230px"></span>
            <span class="ma-auth-circle" style="top:250px;left:-95px;width:130px;height:130px"></span>
            <span class="ma-auth-circle" style="bottom:-120px;left:-80px;width:230px;height:230px"></span>
            <div class="ma-auth-inner">
                ${n?`<button class="ma-auth-back" data-back><i class="ri-arrow-left-s-line"></i> Back</button>`:`<span class="ma-auth-back" style="visibility:hidden"><i class="ri-arrow-left-s-line"></i> Back</span>`}
                <img src="/images/public/logo-coloured.png" alt="Nextel Connect"
                     class="mx-auto -mt-6 h-14 w-auto object-contain"
                     onerror="this.style.display='none'">
                <h1 class="ma-heading mt-5 ${r?`text-center`:``} text-[26px] font-bold" style="color:var(--ma-primary)">${O(e)}</h1>
                ${t?`<p class="ma-hint mt-1 ${r?`text-center`:``} text-[0.85rem]">${O(t)}</p>`:``}
                <div class="ma-auth-body"></div>
                <p class="ma-auth-foot">
                    <span style="width:10px;height:10px;border-radius:999px;background:var(--ma-secondary);display:inline-block"></span>
                    Trusted network for Nigerians
                </p>
            </div>
        </div>`);return n&&i.querySelector(`[data-back]`).addEventListener(`click`,()=>{a(`light`),C(n)}),i}function q({label:e,name:t,icon:n,type:r=`text`,placeholder:i=``,value:a=``,inputmode:o=null,maxlength:s=null}){let c=D(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">${O(e)}</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="${n}"></i></span>
                <input class="ma-input ma-pill" name="${t}" type="${r}" placeholder="${O(i)}"
                       value="${O(a)}" autocomplete="off" ${o?`inputmode="${o}"`:``} ${s?`maxlength="${s}"`:``}>
                ${r===`password`?`<button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)" data-eye aria-label="Show password"><i class="ri-eye-line"></i></button>`:``}
            </div>
        </div>`),l=c.querySelector(`input`),u=c.querySelector(`[data-eye]`);return u?.addEventListener(`click`,()=>{let e=l.type===`password`;l.type=e?`text`:`password`,u.innerHTML=`<i class="${e?`ri-eye-off-line`:`ri-eye-line`}"></i>`}),c}function tt(e,t){return D(`
        <label class="flex cursor-pointer items-center gap-2 text-[0.82rem]" style="color:var(--ma-muted)">
            <input type="checkbox" name="${t}" class="ma-check"> ${O(e)}
        </label>`)}var nt=!1;function rt(e){nt||(nt=!0,e.addEventListener(`click`,e=>{let t=e.target.closest(`[data-nav]`);t&&(a(`light`),C(t.dataset.nav))}))}async function it(e,t={}){let i=t?.telegramName??r()?.first_name??null;e.innerHTML=``;let o=K({title:`Welcome Back`,subtitle:i?`Telegram: ${i} — log in once to link this account.`:`Login to continue to your Nextel account`});e.appendChild(o);let s=o.querySelector(`.ma-auth-body`);s.appendChild(q({label:`Email or username`,name:`login`,icon:`ri-user-3-line`,placeholder:`Email or username`})),s.appendChild(q({label:`Password`,name:`password`,icon:`ri-lock-2-line`,type:`password`,placeholder:`Password`}));let c=D(`
        <div class="mt-1 flex items-center justify-between">
            <span data-slot-check></span>
            <button type="button" class="ma-linkbtn" data-nav="forgot">Forgot Password?</button>
        </div>`);c.querySelector(`[data-slot-check]`).replaceWith(tt(`Remember Me`,`remember`)),s.appendChild(c);let l=document.createElement(`div`);l.className=`mt-6 flex gap-3`,l.innerHTML=`
        <button type="button" class="ma-btn ma-btn-primary" data-login style="border-radius:16px">Login</button>
        <button type="button" class="ma-btn ma-btn-peach ma-btn-auto" data-telegram style="border-radius:16px">
            <i class="ri-fingerprint-2-line"></i> Telegram
        </button>`,s.appendChild(l),s.appendChild(D(`
        <p class="mt-5 text-center text-[0.85rem]" style="color:var(--ma-muted)">
            Don't have an account?
            <button type="button" class="ma-linkbtn" data-nav="register">Sign up</button>
        </p>`)),rt(e),l.querySelector(`[data-telegram]`).addEventListener(`click`,async()=>{let e=n();if(!e)return j(`One-tap sign-in works inside the Telegram app.`,`info`,3e3);a(`light`);let t=l.querySelector(`[data-telegram]`);t.disabled=!0;try{let t=await y(`/telegram/auth`,{method:`POST`,auth:!1,body:{init_data:e}});if(t?.linked&&t?.token)return v(t.token,!0),E(t.user),a(`success`),b();j(`This Telegram account isn’t linked yet — log in below once.`,`info`,3500)}catch(e){j(F(e),`error`)}finally{t.disabled=!1}}),l.querySelector(`[data-login]`).addEventListener(`click`,async()=>{let e=s.querySelector(`[name="login"]`).value.trim(),t=s.querySelector(`[name="password"]`).value,n=s.querySelector(`[name="remember"]`).checked;if(!e||!t)return j(`Enter your email/username and password.`,`error`);let r=l.querySelector(`[data-login]`);r.disabled=!0,r.textContent=`Logging in…`;try{let r=await y(`/login`,{method:`POST`,auth:!1,body:{login:e,password:t,device_name:`Telegram Mini App`}});v(r.token,n),E(r.user),await at(),a(`success`),await b()}catch(e){j(e instanceof g?e.fieldMessage()??e.message:F(e),`error`),r.disabled=!1,r.textContent=`Login`}})}async function at(){let e=n();if(e)try{await y(`/telegram/link`,{method:`POST`,body:{init_data:e}})}catch{}}async function ot(e){w(`login`);let t=r(),i=[t?.first_name,t?.last_name].filter(Boolean).join(` `);e.innerHTML=``;let o=K({title:`Create Account`,subtitle:`Join Nextel Connect and get started today`,backTo:`login`,centered:!0});e.appendChild(o);let s=o.querySelector(`.ma-auth-body`);s.appendChild(q({label:`Full name`,name:`full_name`,icon:`ri-user-3-line`,placeholder:`Your name`,value:i})),s.appendChild(q({label:`Email`,name:`email`,icon:`ri-mail-line`,type:`email`,placeholder:`Enter your email address`})),s.appendChild(q({label:`Username`,name:`username`,icon:`ri-at-line`,placeholder:`Choose preferred username`,value:t?.username??``,maxlength:30}));let c={code:`NG`,name:`Nigeria`,flag:`🇳🇬`},l=D(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Country</label>
            <button class="ma-input ma-pill w-full text-left" data-country style="display:flex;align-items:center;gap:.6rem">
                <span style="color:var(--ma-muted)"><i class="ri-earth-line"></i></span>
                <span data-flag>🇳🇬</span>
                <span data-name class="flex-1">Nigeria</span>
                <i class="ri-arrow-down-s-line" style="color:var(--ma-muted)"></i>
            </button>
        </div>`);s.appendChild(l);let u=D(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Phone number</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-[0.9rem] font-bold" style="color:var(--ma-primary)" data-dial>+234</span>
                <input class="ma-input ma-pill" name="phone" type="tel" inputmode="numeric" placeholder="Phone number" style="padding-left:4.6rem">
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="ri-phone-line"></i></span>
            </div>
        </div>`);s.appendChild(u),s.appendChild(q({label:`Referral code (optional)`,name:`referral_code`,icon:`ri-gift-line`,placeholder:`Referral code`,maxlength:30})),s.appendChild(q({label:`Password`,name:`password`,icon:`ri-lock-2-line`,type:`password`,placeholder:`At least 8 characters`})),s.appendChild(q({label:`Confirm password`,name:`password_confirmation`,icon:`ri-lock-2-line`,type:`password`,placeholder:`Repeat password`})),s.appendChild(tt(`I agree to the Terms & Conditions`,`agree_terms`));let d=D(`<button class="ma-btn ma-btn-primary mt-5" style="border-radius:16px"><i class="ri-user-add-line"></i> Create Account</button>`);s.appendChild(d),l.querySelector(`[data-country]`).addEventListener(`click`,()=>{ct(e,e=>{c=e,l.querySelector(`[data-flag]`).textContent=e.flag??`🌍`,l.querySelector(`[data-name]`).textContent=e.name,u.querySelector(`[data-dial]`).textContent=et[e.code]??``,a(`light`)})}),d.addEventListener(`click`,async()=>{let e=e=>s.querySelector(`[name="${e}"]`)?.value??``,t=e(`full_name`).trim(),r=e(`email`).trim(),i=e(`username`).trim().replace(/^@/,``),o=e(`phone`).replace(/[^\d]/g,``),l=e(`referral_code`).trim().replace(/^@/,``),u=e(`password`),f=e(`password_confirmation`),p=s.querySelector(`[name="agree_terms"]`).checked;if(!t)return j(`Enter your full name.`,`error`);if(!/^\S+@\S+\.\S+$/.test(r))return j(`Enter a valid email address.`,`error`);if(i.length<3)return j(`Username needs at least 3 characters.`,`error`);if(!o)return j(`Enter your phone number.`,`error`);if(u.length<8)return j(`Password must be at least 8 characters.`,`error`);if(u!==f)return j(`Passwords do not match.`,`error`);if(!p)return j(`Please agree to the Terms & Conditions to continue.`,`error`);let m=`${et[c.code]??``}${o}`.slice(0,20);d.disabled=!0,d.textContent=`Creating your account…`;try{let e=await y(`/register`,{method:`POST`,auth:!1,body:{full_name:t,username:i,email:r,phone:m,country:c.code,password:u,password_confirmation:f,referral_code:l||null,agree_terms:!0,init_data:n()||null}});if(v(e.token,!0),E(e.user),await st(),a(`success`),e.verification_required)return j(e.verification_channel===`telegram`?`Account created — check your Telegram for the code.`:`Account created — verify your email to finish.`,`success`,3500),C(`verify`,{email:r,channel:e.verification_channel??`email`});j(`Welcome to Nextel! 🎉`,`success`,3500),await b()}catch(e){j(F(e),`error`),d.disabled=!1,d.innerHTML=`<i class="ri-user-add-line"></i> Create Account`}})}async function st(){let e=n();if(e)try{await y(`/telegram/link`,{method:`POST`,body:{init_data:e}})}catch{}}function ct(e,t){let n=D(`
        <div class="ma-sheet-backdrop" style="align-items:center;padding:1.25rem">
            <div class="w-full max-w-[22rem] rounded-[28px] p-5" style="background:var(--ma-surface);max-height:75dvh;display:flex;flex-direction:column">
                <h3 class="ma-heading mb-3 text-lg font-bold" style="color:var(--ma-primary)">Select Country</h3>
                <div class="relative mb-2">
                    <input class="ma-input ma-pill" data-search placeholder="Search country" autocomplete="off">
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto" data-list>
                    <div class="flex justify-center py-6"><div class="ma-spinner"></div></div>
                </div>
            </div>
        </div>`),r=n.querySelector(`[data-list]`),i=n.querySelector(`[data-search]`),a=[],o=(e=``)=>{let i=e.trim().toLowerCase(),o=a.filter(e=>!i||e.name.toLowerCase().includes(i));r.innerHTML=``,o.forEach(e=>{let i=D(`
                <button class="flex w-full items-center gap-3 rounded-2xl px-2 py-3 text-left" data-code="${O(e.code)}">
                    <span class="text-xl">${O(e.flag??`🌍`)}</span>
                    <span class="flex-1 text-[0.95rem]" style="color:var(--ma-text)">${O(e.name)}</span>
                    <span class="ma-hint text-[0.8rem]">${O(et[e.code]??``)}</span>
                </button>`);i.addEventListener(`click`,()=>{t(e),n.remove()}),r.appendChild(i)}),o.length||(r.innerHTML=`<div class="ma-hint py-6 text-center text-sm">No country matches.</div>`)};i.addEventListener(`input`,()=>o(i.value)),y(`/countries`,{auth:!1}).then(e=>{a=e.countries??[],o()}).catch(e=>{r.innerHTML=`<div class="ma-hint py-6 text-center text-sm">${O(F(e))}</div>`}),n.addEventListener(`click`,e=>{e.target===n&&n.remove()}),e.appendChild(n),setTimeout(()=>i.focus(),120)}async function lt(e){w(`login`),e.innerHTML=``;let t=K({title:`Forgot Password?`,subtitle:`We’ll email you a 6-digit reset code.`,backTo:`login`,centered:!0});e.appendChild(t);let n=t.querySelector(`.ma-auth-body`);n.classList.add(`ma-auth-body-low`),n.appendChild(q({label:`Email`,name:`email`,icon:`ri-mail-line`,type:`email`,placeholder:`Enter your email address`}));let r=D(`<button class="ma-btn ma-btn-primary" style="border-radius:16px"><i class="ri-send-plane-line"></i> Send reset code</button>`);n.appendChild(r);let i=D(`
        <div class="hidden">
            <div class="mb-4 mt-5">
                <label class="mb-1.5 block text-[0.85rem] font-medium">Reset code</label>
                <input class="ma-input ma-pill text-center text-xl tracking-[0.4em]" name="code" inputmode="numeric" maxlength="6" placeholder="••••••">
            </div>
            
        </div>`);n.appendChild(i);let o=!1,s=D(`<button class="ma-btn ma-btn-accent hidden mt-1" style="border-radius:16px"><i class="ri-lock-unlock-line"></i> Reset password</button>`);n.appendChild(s);let c=()=>n.querySelector(`[name="email"]`).value.trim();r.addEventListener(`click`,async()=>{let e=c();if(!/^\S+@\S+\.\S+$/.test(e))return j(`Enter a valid email address.`,`error`);r.disabled=!0,r.textContent=`Sending…`;try{await y(`/password/forgot`,{method:`POST`,auth:!1,body:{email:e}}),a(`success`),j(`Reset code sent — check your inbox.`,`success`),i.classList.remove(`hidden`),s.classList.remove(`hidden`),o||(o=!0,i.appendChild(q({label:`New password`,name:`password`,icon:`ri-lock-2-line`,type:`password`,placeholder:`At least 8 characters`})),i.appendChild(q({label:`Confirm new password`,name:`password_confirmation`,icon:`ri-lock-2-line`,type:`password`,placeholder:`Repeat password`}))),n.querySelector(`[name="code"]`).focus()}catch(e){j(F(e),`error`)}finally{r.disabled=!1,r.innerHTML=`<i class="ri-send-plane-line"></i> Send reset code`}}),s.addEventListener(`click`,async()=>{let e=n.querySelector(`[name="code"]`).value.trim(),t=n.querySelector(`[name="password"]`).value,r=n.querySelector(`[name="password_confirmation"]`).value;if(e.length!==6)return j(`Enter the 6-digit code.`,`error`);if(t.length<8)return j(`Password must be at least 8 characters.`,`error`);if(t!==r)return j(`Passwords do not match.`,`error`);s.disabled=!0,s.textContent=`Resetting…`;try{await y(`/password/reset`,{method:`POST`,auth:!1,body:{email:c(),code:e,password:t}}),a(`success`),j(`Password reset — log in with your new password.`,`success`,3500),await C(`login`)}catch(e){j(F(e),`error`),s.disabled=!1,s.innerHTML=`<i class="ri-lock-unlock-line"></i> Reset password`}})}async function ut(e,t={}){w(`login`);let r=t?.email??``,i=t?.channel===`telegram`&&n()?`telegram`:`email`;e.innerHTML=``;let o=K({title:`Verify Account`,subtitle:i===`telegram`?`We sent a code to your Telegram${r?` (and to ${r})`:``}.`:`We sent a 6-digit code to ${r||`your inbox`}.`,backTo:`login`,centered:!0});e.appendChild(o);let s=o.querySelector(`.ma-auth-body`);if(i===`telegram`){let e=D(`
            <div class="ma-card ma-card-tight mb-4 flex items-center gap-3">
                <div class="ma-spinner" data-spin></div>
                <div class="min-w-0 flex-1">
                    <div class="text-[0.82rem] font-semibold" data-title>Verifying via Telegram…</div>
                    <div class="ma-hint text-[0.7rem]" data-sub>Confirming this session received the code.</div>
                </div>
                <span class="ma-icon" style="width:2.4rem;height:2.4rem"><i class="ri-telegram-line"></i></span>
            </div>`);s.appendChild(e),p(e)}let c=D(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Verification code</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="ri-shield-keyhole-line"></i></span>
                <input class="ma-input ma-pill text-center text-xl tracking-[0.4em]" name="code" inputmode="numeric" maxlength="6" placeholder="••••••" style="padding-left:2.9rem">
            </div>
            <div class="ma-hint mt-1.5 text-center text-[0.7rem]">${i===`telegram`?`The code is in your Telegram messages.`:`The code is in your email inbox.`}</div>
        </div>`);s.appendChild(c);let l=c.querySelector(`input`);l.addEventListener(`input`,()=>{l.value=l.value.replace(/\D/g,``).slice(0,6)});let u=D(`<button class="ma-btn ma-btn-primary" style="border-radius:16px"><i class="ri-mail-check-line"></i> Verify</button>`);s.appendChild(u);let d=D(`<button class="ma-btn ma-btn-ghost ma-btn-auto mx-auto mt-3 block"><i class="ri-refresh-line"></i> Resend code</button>`);s.appendChild(d),u.addEventListener(`click`,async()=>{let e=l.value.trim();if(e.length!==6)return j(`Enter the 6-digit code.`,`error`);u.disabled=!0,u.textContent=`Verifying…`;try{await y(`/email/verify`,{method:`POST`,body:{code:e}}),a(`success`),j(`Verified — welcome aboard! 🎉`,`success`,3500),await f()}catch(e){j(F(e),`error`),u.disabled=!1,u.innerHTML=`<i class="ri-mail-check-line"></i> Verify`}}),d.addEventListener(`click`,async()=>{d.disabled=!0;try{await y(`/email/resend`,{method:`POST`}),j(`A new code has been sent.`,`success`)}catch(e){j(F(e),`error`),d.disabled=!1}});async function f(){try{E((await y(`/user`)).user??null)}catch{}await b()}async function p(e){let t=e.querySelector(`[data-title]`),r=e.querySelector(`[data-sub]`);for(let e=1;e<=3;e++){try{let e=await y(`/telegram/verify`,{method:`POST`,body:{init_data:n()}});if(e?.verified)return a(`success`),e.user&&E(e.user),j(`Verified via Telegram 🎉`,`success`,3e3),b()}catch{}e<3&&(r.textContent=`Waiting for the Telegram message… (attempt ${e+1} of 3)`,await new Promise(e=>setTimeout(e,4e3)))}e.querySelector(`[data-spin]`).outerHTML=`<i class="ri-error-warning-line text-lg" style="color:var(--ma-warning)"></i>`,t.textContent=`Auto-verify unavailable`,r.textContent=`Enter the code from your Telegram message below.`}}async function dt(e){w(`services`),e.innerHTML=``,e.appendChild(R(`Shares & Plans`)),e.appendChild(M(5));let t,n;try{[t,n]=await Promise.all([y(`/packages`),y(`/minute-plans`).catch(()=>null)])}catch(t){e.innerHTML=``,e.appendChild(R(`Shares & Plans`)),e.appendChild(P(F(t),()=>dt(e)));return}let r=T.user??{},i=r.package??null,o=r.onboarding?.activated??i!==null;e.innerHTML=``,e.appendChild(R(`Shares & Plans`,i?`${O(i.name)} active`:`No active plan`));let s=D(`<div></div>`);if(e.appendChild(s),i&&s.appendChild(D(`
            <div class="ma-card mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="ma-hint text-[0.64rem] font-semibold uppercase">Your plan</div>
                        <div class="ma-heading text-lg font-bold">${O(i.name)}</div>
                    </div>
                    <span class="ma-chip ma-chip-accent"><i class="ri-medal-line"></i> Active</span>
                </div>
                ${n?`
                <div class="ma-hint mt-2 flex items-center justify-between text-[0.75rem]">
                    <span><i class="ri-time-line mr-1"></i>Base ${L(n.base_minutes)} min/day</span>
                    <span>+ ${L(n.bonus_minutes)} bonus</span>
                </div>`:``}
            </div>`)),s.appendChild(D(`<h3 class="ma-heading mb-2 text-base font-bold">eSIM packages</h3>`)),(t?.packages??[]).forEach(t=>{let n=i?.id===t.id,r=n?`current`:o?`upgrade`:`activate`,c=r===`upgrade`&&t.upgrade_price_usd!==null?t.upgrade_price_usd:t.price_usd,l=D(`
            <div class="ma-card mb-3 ${t.is_featured?`ring-2 ring-[#DCEB6A]`:``}">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-[0.95rem] font-bold">${O(t.name)}</div>
                    ${n?`<span class="ma-chip ma-chip-success">Current</span>`:`<div class="ma-heading text-lg font-bold">${k(c)}${r===`upgrade`?`<span class="ma-hint ml-1 text-[0.62rem]">upgrade</span>`:``}</div>`}
                </div>
                ${t.description?`<p class="ma-hint mt-1 text-[0.76rem] leading-relaxed">${O(t.description)}</p>`:``}
                <ul class="ma-hint mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[0.7rem]">
                    <li><i class="ri-phone-line mr-1"></i>${L(t.daily_call_minutes)} min/day</li>
                    <li><i class="ri-ids-line mr-1"></i>${t.call_id_limit} call ID${t.call_id_limit===1?``:`s`}</li>
                    <li><i class="ri-user-add-line mr-1"></i>${k(t.direct_referral_bonus_usd)} direct</li>
                    <li><i class="ri-pie-chart-2-line mr-1"></i>${k(t.max_daily_shareholder_earning_usd)}/day cap</li>
                    ${t.cloud_calls?`<li><i class="ri-cloud-line mr-1"></i>Cloud calls</li>`:``}
                    ${t.cloud_sms?`<li><i class="ri-message-2-line mr-1"></i>Cloud SMS</li>`:``}
                </ul>
                ${(t.features??[]).length?`
                    <div class="no-scrollbar mt-2 flex gap-1.5 overflow-x-auto">
                        ${t.features.map(e=>`<span class="ma-chip ma-chip-muted whitespace-nowrap">${O(e)}</span>`).join(``)}
                    </div>`:``}
                ${n?``:`
                    <button class="ma-btn ${r===`activate`?`ma-btn-primary`:`ma-btn-accent`} mt-3" data-redeem>
                        <i class="ri-coupon-3-line"></i> ${r===`upgrade`?`Upgrade with token`:`Activate with token`}
                    </button>
                    <div class="mt-2 hidden" data-token-form>
                        <input class="ma-input" data-token placeholder="Enter your activation token" autocomplete="off">
                        <button class="ma-btn ma-btn-primary mt-2" data-confirm>Confirm ${O(r)}</button>
                    </div>`}
            </div>`),u=l.querySelector(`[data-redeem]`),d=l.querySelector(`[data-token-form]`),f=l.querySelector(`[data-token]`),p=l.querySelector(`[data-confirm]`);u?.addEventListener(`click`,()=>{d.classList.toggle(`hidden`),d.classList.contains(`hidden`)||f.focus()}),p?.addEventListener(`click`,async()=>{let n=f.value.trim();if(n.length<6)return j(`Enter the token from your vendor.`,`error`);p.disabled=!0,p.textContent=`Processing…`;try{let i=await y(r===`upgrade`?`/upgrade`:`/activate`,{method:`POST`,body:{package_id:t.id,token:n}});a(`success`),j(i?.message??`Done — welcome aboard!`,`success`,3500),y(`/user`).then(e=>E(e.user??null)).catch(()=>{}),dt(e)}catch(e){j(F(e),`error`),p.disabled=!1,p.textContent=`Confirm ${r}`}}),s.appendChild(l)}),n){s.appendChild(D(`<h3 class="ma-heading mb-2 mt-5 text-base font-bold">Additional minutes</h3>`));let t=n.active_top_ups??[];t.length&&t.forEach(e=>{s.appendChild(D(`
                    <div class="ma-card ma-card-tight mb-2 flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="truncate text-[0.82rem] font-semibold">${O(e.plan_name??e.plan?.name??`Top-up`)}</div>
                            <div class="ma-hint text-[0.7rem]">${L(e.daily_call_minutes??0)} extra min/day</div>
                        </div>
                        <span class="ma-chip ma-chip-success">Active</span>
                    </div>`))});let r=n.plans??[];r.length?r.forEach(t=>{let n=D(`
                    <div class="ma-card mb-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold">${O(t.name)}</div>
                                <div class="ma-hint text-[0.72rem]">${L(t.daily_call_minutes)} min/day · ${t.duration_days} days</div>
                            </div>
                            <div class="ma-heading text-base font-bold">${k(t.price_usd)}</div>
                        </div>
                        <button class="ma-btn mt-3" data-redeem><i class="ri-coupon-3-line"></i> Redeem token</button>
                        <div class="mt-2 hidden" data-token-form>
                            <input class="ma-input" data-token placeholder="Enter your minutes token" autocomplete="off">
                            <button class="ma-btn ma-btn-primary mt-2" data-confirm>Redeem</button>
                        </div>
                    </div>`),r=n.querySelector(`[data-token-form]`),i=n.querySelector(`[data-token]`),o=n.querySelector(`[data-confirm]`);n.querySelector(`[data-redeem]`).addEventListener(`click`,()=>{r.classList.toggle(`hidden`),r.classList.contains(`hidden`)||i.focus()}),o.addEventListener(`click`,async()=>{let n=i.value.trim();if(n.length<6)return j(`Enter the token from your vendor.`,`error`);o.disabled=!0,o.textContent=`Redeeming…`;try{await y(`/minutes/redeem`,{method:`POST`,body:{plan_id:t.id,token:n}}),a(`success`),j(`Minutes activated 🎉`,`success`),dt(e)}catch(e){j(F(e),`error`),o.disabled=!1,o.textContent=`Redeem`}}),s.appendChild(n)}):t.length||s.appendChild(N(`ri-timer-flash-line`,`No minute plans on sale`,`Check back later.`))}}async function ft(e){w(`services`),await pt(e)}async function pt(e){e.innerHTML=``,e.appendChild(R(`Pay Later`)),e.appendChild(M(5));let t,n=[];try{[t,n]=await Promise.all([y(`/pay-later`),y(`/minute-plans`).then(e=>e.plans??[]).catch(()=>[])])}catch(t){e.innerHTML=``,e.appendChild(R(`Pay Later`)),e.appendChild(P(F(t),()=>pt(e)));return}e.innerHTML=``,e.appendChild(R(`Pay Later`));let r=D(`<div></div>`);if(e.appendChild(r),!t.enabled){r.appendChild(N(`ri-timer-flash-line`,`Pay Later is not enabled`,`Check back later — this feature rolls out gradually.`));return}let i=t.credits??[],o=i.filter(e=>e.status===`active`);if(o.length&&(r.appendChild(D(`<h3 class="ma-heading mb-2 text-base font-bold">Outstanding</h3>`)),o.forEach(t=>r.appendChild(mt(t,e)))),r.appendChild(D(`
        <div class="ma-card ma-card-tight mb-3 flex items-center justify-between">
            <span class="text-[0.8rem] font-semibold">Eligibility</span>
            <span class="ma-chip ${t.eligible?`ma-chip-success`:`ma-chip-muted`}">${t.eligible?`Eligible`:`Not eligible yet`}</span>
        </div>`)),!t.eligible&&!o.length){r.appendChild(D(`<p class="ma-hint text-center text-[0.76rem]">Keep earning and stay settled to unlock Pay Later.</p>`));return}let s=n.filter(e=>e.paylater_duration_days>0);if(!s.length){r.appendChild(D(`<p class="ma-hint mt-2 text-center text-[0.76rem]">No Pay Later plans on sale right now.</p>`));return}r.appendChild(D(`<h3 class="ma-heading mb-2 mt-4 text-base font-bold">Activate now, settle later</h3>`)),s.forEach(n=>{let i=D(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold">${O(n.name)}</div>
                        <div class="ma-hint text-[0.72rem]">${L(n.daily_call_minutes)} min/day · settle within ${n.paylater_duration_days} days</div>
                    </div>
                    <div class="ma-heading text-base font-bold">${k(n.price_usd)}</div>
                </div>
                <label class="ma-hint mt-2.5 flex items-start gap-2 text-[0.72rem]">
                    <input type="checkbox" data-agree class="mt-0.5">
                    I agree to repay with an activation token before the due date.
                </label>
                <button class="ma-btn ma-btn-primary mt-2.5" data-apply ${t.eligible?``:`disabled`}>
                    <i class="ri-flashlight-line"></i> Activate now
                </button>
            </div>`),o=i.querySelector(`[data-agree]`),s=i.querySelector(`[data-apply]`);s.addEventListener(`click`,async()=>{if(!o.checked)return j(`Please agree to the repayment terms.`,`error`);let t=await ge(`Confirm with your PIN`);if(t){s.disabled=!0,s.textContent=`Activating…`;try{await y(`/pay-later`,{method:`POST`,body:{plan_id:n.id,pin:t,agreed:!0}}),a(`success`),j(`Minutes activated — start earning immediately 🎉`,`success`,3500),pt(e)}catch(e){j(F(e),`error`),s.disabled=!1,s.innerHTML=`<i class="ri-flashlight-line"></i> Activate now`}}}),r.appendChild(i)});let c=i.filter(e=>e.status!==`active`);c.length&&(r.appendChild(D(`<h3 class="ma-heading mb-2 mt-4 text-base font-bold">History</h3>`)),c.forEach(e=>{r.appendChild(D(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-history-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">${O(e.plan_name)} · ${k(e.amount_usd)}</div>
                        <div class="ma-hint text-xs">${O(I(e.created_at))} · due ${O(e.due_date)}</div>
                    </div>
                    <span class="ma-chip ${e.status===`repaid`?`ma-chip-success`:`ma-chip-danger`}">${O(e.status)}</span>
                </div>`))}))}function mt(e,t){let n=D(`
        <div class="ma-card mb-3" style="border:1.5px solid color-mix(in srgb, var(--ma-warning) 45%, transparent)">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <div class="truncate text-sm font-bold">${O(e.plan_name)}</div>
                    <div class="ma-hint text-[0.72rem]">${L(e.minutes)} min · due ${O(e.due_date)}</div>
                </div>
                <div class="ma-heading text-base font-bold">${k(e.amount_usd)}</div>
            </div>
            <button class="ma-btn ma-btn-accent mt-3" data-repay><i class="ri-coupon-3-line"></i> Repay with token</button>
            <div class="mt-2 hidden" data-token-form>
                <input class="ma-input" data-token placeholder="Enter your activation token" autocomplete="off">
                <button class="ma-btn ma-btn-primary mt-2" data-confirm>Confirm repayment</button>
            </div>
        </div>`),r=n.querySelector(`[data-token-form]`),i=n.querySelector(`[data-token]`),o=n.querySelector(`[data-confirm]`);return n.querySelector(`[data-repay]`).addEventListener(`click`,()=>{r.classList.toggle(`hidden`),r.classList.contains(`hidden`)||i.focus()}),o.addEventListener(`click`,async()=>{let n=i.value.trim();if(n.length<6)return j(`Enter the token from your vendor.`,`error`);o.disabled=!0,o.textContent=`Settling…`;try{await y(`/pay-later/${e.id}/repay`,{method:`POST`,body:{token:n}}),a(`success`),j(`Pay Later repaid — you’re all settled ✅`,`success`,3500),pt(t)}catch(e){j(F(e),`error`),o.disabled=!1,o.textContent=`Confirm repayment`}}),n}async function ht(e){w(`services`),await J(e)}async function J(e){e.innerHTML=``,e.appendChild(R(`Loan`)),e.appendChild(M(4));let t;try{t=await y(`/loans`)}catch(t){e.innerHTML=``,e.appendChild(R(`Loan`)),e.appendChild(P(F(t),()=>J(e)));return}e.innerHTML=``,e.appendChild(R(`Loan`));let n=D(`<div></div>`);if(e.appendChild(n),!t.enabled){n.appendChild(N(`ri-hand-coin-line`,`Loans are not enabled`,`This feature rolls out gradually.`));return}let r=t.application_fee??{},i=t.application_fee_payment??{},o=t.guarantor_form_payment??{},s=Number(t.min_amount_usd??1),c=r.type??`fixed`,l=i.currency??r.currency??``,u=c===`percentage`?`${L(r.percentage)}% of the requested principal${l?`, payable in ${O(l)} through the active channel`:``}. Vendor fee coupons are not available.`:`Fixed fee: ${O(r.currency??``)} ${Number(r.amount??0).toFixed(2)}${i.available?`, or ${Number(i.amount??0).toFixed(2)} ${O(i.currency??``)} through the active channel`:``}. Eligible vendor coupons are also accepted.`;if(n.appendChild(D(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">How it works</div>
            <ul class="ma-hint space-y-1.5 text-[0.78rem]">
                <li><i class="ri-arrow-up-line mr-1" style="color:var(--ma-primary-light)"></i>Borrow between <b>${k(s)}</b> and <b>${k(t.max_amount_usd)}</b>.</li>
                <li><i class="ri-percent-line mr-1" style="color:var(--ma-primary-light)"></i>${L(t.service_fee_percent)}% service fee on approval.</li>
                <li><i class="ri-calendar-line mr-1" style="color:var(--ma-primary-light)"></i>Repay within ${t.repayment_days} days.</li>
                <li><i class="ri-bank-line mr-1" style="color:var(--ma-primary-light)"></i>${u}</li>
            </ul>
        </div>`)),t.has_unresolved_application??t.has_processing){let e=t.pending_fee_payment,r=D(`
            <div class="ma-card ma-card-tight mb-3 flex flex-col gap-3">
                <div class="flex items-center gap-2.5">
                    <i class="ri-time-line text-lg" style="color:var(--ma-warning)"></i>
                    <span class="text-[0.8rem] font-semibold">${t.has_processing?`An application is being reviewed.`:`A loan-fee payment is awaiting verification.`}</span>
                </div>
                ${e?.url?`<button class="ma-btn ma-btn-primary" data-resume><i class="ri-bank-card-line"></i> Continue fee payment</button>`:``}
            </div>`);r.querySelector(`[data-resume]`)?.addEventListener(`click`,()=>f(e.url)),n.appendChild(r)}else{let r=new Date(Date.now()+Math.min(t.repayment_days,14)*864e5),o=Object.entries(t.loan_types??{}).map(([e,t])=>`<option value="${O(e)}">${O(t)}</option>`).join(``),l=!!i.available,u=D(`
            <div class="ma-card mb-3">
                <div class="ma-heading mb-2 text-base font-bold">Apply for a loan</div>
                <label class="ma-hint text-[0.7rem] font-semibold uppercase">Loan type</label>
                <select class="ma-input mt-1" name="loan_type">${o}</select>
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Amount (USD)</label>
                <input class="ma-input mt-1" name="amount" type="number" min="${s}" max="${Number(t.max_amount_usd)}" step="0.01" inputmode="decimal" placeholder="e.g. ${s}">
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Repayment date</label>
                <input class="ma-input mt-1" name="due_date" type="date" value="${r.toISOString().slice(0,10)}">
                <div class="mt-3 rounded-xl border border-white/10 p-3">
                    <div class="ma-hint mb-2 text-[0.7rem] font-semibold uppercase">Bank account for manual disbursement</div>
                    <label class="ma-hint text-xs">Account name</label>
                    <input class="ma-input mt-1" name="account_name" maxlength="120" autocomplete="name" placeholder="Name on the bank account">
                    <label class="ma-hint mt-2 block text-xs">Account number</label>
                    <input class="ma-input mt-1" name="account_number" maxlength="34" inputmode="text" autocomplete="off" placeholder="Account number">
                    <label class="ma-hint mt-2 block text-xs">Bank name</label>
                    <input class="ma-input mt-1" name="bank_name" maxlength="120" placeholder="Bank name">
                    <p class="ma-hint mt-2 text-xs">If approved, staff will manually send the funds to this account.</p>
                </div>
                ${c===`fixed`?`
                    <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Loan-fee coupon (optional)</label>
                    <input class="ma-input mt-1 uppercase" name="fee_coupon_code" maxlength="32" placeholder="Enter vendor coupon code">
                `:``}
                <label class="ma-hint mt-3 flex items-start gap-2 text-xs">
                    <input class="mt-0.5" name="agreed" type="checkbox">
                    <span>I accept the loan agreement, confirm the repayment date and bank details, and understand the application fee is non-refundable and does not guarantee approval.</span>
                </label>
                ${c===`percentage`&&!l?`<p class="ma-hint mt-2 text-xs text-red-300">Percentage fees require an active payment channel. Applications cannot be submitted right now.</p>`:``}
                <button class="ma-btn ma-btn-primary mt-3" data-apply ${c===`percentage`&&!l?`disabled`:``}><i class="ri-send-plane-line"></i> ${c===`fixed`?`Continue`:`Pay fee and submit`}</button>
            </div>`);u.querySelector(`[data-apply]`).addEventListener(`click`,async()=>{let n=parseFloat(u.querySelector(`[name="amount"]`).value),r=u.querySelector(`[name="due_date"]`).value,i=u.querySelector(`[name="account_name"]`).value.trim(),o=u.querySelector(`[name="account_number"]`).value.trim(),l=u.querySelector(`[name="bank_name"]`).value.trim(),d=u.querySelector(`[name="agreed"]`).checked;if(!Number.isFinite(n)||n<s)return j(`Enter a loan amount of at least ${k(s)}.`,`error`);if(n>t.max_amount_usd)return j(`Maximum is ${k(t.max_amount_usd)}.`,`error`);if(!r)return j(`Pick a repayment date.`,`error`);if(!i||!o||!l)return j(`Enter the account name, account number and bank name for disbursement.`,`error`);if(!d)return j(`Accept the loan agreement to continue.`,`error`);let p=u.querySelector(`[data-apply]`);p.disabled=!0,p.textContent=`Submitting…`;let m={loan_type:u.querySelector(`[name="loan_type"]`).value,amount:n,due_date:r,agreed:d,disbursement_account_name:i,disbursement_account_number:o,disbursement_bank_name:l},h=u.querySelector(`[name="fee_coupon_code"]`)?.value.trim();h&&(m.fee_coupon_code=h);try{let t=await y(`/loans`,{method:`POST`,body:m});a(`success`),t.checkout_url?(j(`Payment of ${t.application_fee?.amount==null?`the application fee`:`${Number(t.application_fee.amount).toFixed(2)} ${t.application_fee.currency}`} opened. Your application will be submitted after verification.`,`success`,4500),f(t.checkout_url)):j(`Fee coupon accepted — your loan application is now under review.`,`success`,3500),await J(e)}catch(e){j(e.fieldMessage?.()||F(e),`error`),p.disabled=!1,p.innerHTML=`<i class="ri-send-plane-line"></i> ${c===`fixed`?`Continue`:`Pay fee and submit`}`}}),n.appendChild(u)}let d=t.loans??[];d.length&&(n.appendChild(D(`<h3 class="ma-heading mb-2 mt-4 text-base font-bold">Your loans</h3>`)),d.forEach(t=>{let r={approved:`ma-chip-success`,paid:`ma-chip-success`,processing:`ma-chip-warning`,guarantor_form_required:`ma-chip-warning`,rejected:`ma-chip-danger`}[t.status]??`ma-chip-muted`,i=t.status_label??{approved:`Approved`,paid:`Paid / disbursed`,processing:`Pending review`,guarantor_form_required:`Guarantor form required`,rejected:`Rejected`}[t.status]??t.status;if(n.appendChild(D(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-hand-coin-line"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${k(t.amount_usd)} → repay ${k(t.total_repayment_usd)}</div>
                    <div class="ma-hint truncate text-xs">${O(I(t.created_at))} · due ${O(t.due_date)}${t.rejection_reason?` · ${O(t.rejection_reason)}`:``}</div>
                </div>
                <span class="ma-chip ${r}">${O(i)}</span>
            </div>`)),t.guarantor_form?.required){let r=D(`
                <button type="button" class="ma-card ma-card-tight mt-2 w-full flex items-center gap-3 text-left" aria-label="Open guarantor form">
                    <span class="flex h-[58px] w-[48px] shrink-0 flex-col justify-between rounded-lg border border-white/15 bg-white p-1.5 shadow-sm">
                        <span class="flex items-center justify-between"><i class="ri-file-text-line text-sm" style="color:var(--ma-primary-light)"></i><span class="text-[5px] font-bold tracking-wide" style="color:var(--ma-primary-light)">NEXTEL</span></span>
                        <span class="space-y-1"><span class="block h-1 w-7 rounded bg-slate-200"></span><span class="block h-1 w-5 rounded bg-slate-200"></span><span class="block h-1 w-8 rounded bg-slate-200"></span></span>
                        <span class="text-[5px] font-bold uppercase tracking-wide text-slate-500">Form</span>
                    </span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">Guarantor form</span><span class="ma-hint mt-0.5 block text-xs">${t.guarantor_form.pending_payment?`Payment pending · tap to continue`:t.guarantor_form.fee_paid?`Payment verified · complete the form`:`Required · fee ₦${L(t.guarantor_form.fee_amount_ngn)} NGN`}</span></span>
                    <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-primary-light)"></i>
                </button>`);r.addEventListener(`click`,()=>gt(e,t,o)),n.appendChild(r)}else if(t.guarantor_form?.view_url||t.guarantor_form?.download_url){let e=D(`<button type="button" class="ma-btn ma-btn-ghost mt-2"><i class="ri-file-text-line"></i> View / download guarantor form</button>`);e.addEventListener(`click`,()=>f(t.guarantor_form.view_url||t.guarantor_form.download_url)),n.appendChild(e)}}))}function gt(e,t,n={}){let r=t.guarantor_form??{},i=D(`
        <div class="ma-sheet-backdrop" data-guarantor-modal>
            <section class="ma-sheet max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="guarantor-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="guarantor-title" class="ma-heading text-lg font-bold">Guarantor form</h2>
                        <p class="ma-hint mt-1 text-xs">${O(t.loan_type_label??`Loan`)} · before manual disbursement</p>
                    </div>
                    <button type="button" data-close class="ma-icon !h-9 !w-9" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="mt-4" data-guarantor-content></div>
            </section>
        </div>`),a=i.querySelector(`[data-guarantor-content]`),o=()=>i.remove();if(i.querySelector(`[data-close]`).addEventListener(`click`,o),i.addEventListener(`click`,e=>{e.target===i&&o()}),r.form_submitted_at&&(r.view_url||r.download_url))a.innerHTML=`
            <div class="ma-card ma-card-tight flex items-start gap-3">
                <i class="ri-checkbox-circle-line text-xl" style="color:var(--ma-success)"></i>
                <div><div class="text-sm font-semibold">Guarantor form submitted</div><p class="ma-hint mt-1 text-xs">Your form is complete. Staff can record a manual disbursement after this step.</p></div>
            </div>
            <button type="button" class="ma-btn ma-btn-primary mt-3" data-download><i class="ri-file-text-line"></i> View / download guarantor form</button>`,a.querySelector(`[data-download]`).addEventListener(`click`,()=>f(r.view_url||r.download_url));else if(r.fee_paid){a.innerHTML=`
            <div class="ma-card ma-card-tight mb-3">
                <div class="text-sm font-semibold">Payment verified · form unlocked</div>
                <p class="ma-hint mt-1 text-xs">Fee paid: ₦${L(r.fee_amount_ngn)} NGN. Complete the guarantor’s details and confirm their consent.</p>
            </div>
            <form data-guarantor-form class="flex flex-col gap-2.5">
                <label class="ma-hint text-xs font-semibold">Guarantor’s full name</label>
                <input class="ma-input" name="full_name" maxlength="120" autocomplete="name" required>
                <label class="ma-hint text-xs font-semibold">Relationship to applicant</label>
                <input class="ma-input" name="relationship" maxlength="80" required>
                <label class="ma-hint text-xs font-semibold">Phone number</label>
                <input class="ma-input" name="phone" type="tel" maxlength="32" autocomplete="tel" required>
                <label class="ma-hint text-xs font-semibold">Email (optional)</label>
                <input class="ma-input" name="email" type="email" maxlength="255" autocomplete="email">
                <label class="ma-hint text-xs font-semibold">Residential address</label>
                <textarea class="ma-input" name="residential_address" rows="2" maxlength="255" required></textarea>
                <label class="ma-hint text-xs font-semibold">Electronic signature · type the full name above</label>
                <input class="ma-input" name="signature" maxlength="120" autocomplete="off" required>
                <label class="ma-card ma-card-tight mt-1 flex items-start gap-2 text-xs">
                    <input type="checkbox" name="consent" class="mt-0.5" required>
                    <span>I confirm the guarantor consented to provide these details and be contacted about this loan. I am authorized to submit this information.</span>
                </label>
                <p class="ma-hint text-xs" data-form-alert role="status" hidden></p>
                <button type="submit" class="ma-btn ma-btn-primary mt-1"><i class="ri-check-line"></i> Submit guarantor form</button>
            </form>`;let n=a.querySelector(`[data-guarantor-form]`),i=a.querySelector(`[data-form-alert]`);n.addEventListener(`submit`,async r=>{r.preventDefault();let a=Object.fromEntries(new FormData(n).entries()),s=String(a.full_name??``).trim(),c=String(a.signature??``).trim();if(c!==s){i.hidden=!1,i.textContent=`Type the guarantor’s full name exactly as shown above for the electronic signature.`;return}let l=n.querySelector(`[type="submit"]`);l.disabled=!0,l.textContent=`Submitting…`,i.hidden=!0;try{await y(`/loans/${encodeURIComponent(t.id)}/guarantor-form`,{method:`POST`,body:{full_name:s,relationship:String(a.relationship??``).trim(),phone:String(a.phone??``).trim(),email:String(a.email??``).trim()||null,residential_address:String(a.residential_address??``).trim(),signature:c,consent:n.querySelector(`[name="consent"]`).checked}}),o(),j(`Guarantor form submitted. You can now view or download the PDF.`,`success`),await J(e)}catch(e){i.hidden=!1,i.textContent=F(e),l.disabled=!1,l.innerHTML=`<i class="ri-check-line"></i> Submit guarantor form`}})}else{let s=r.pending_payment;a.innerHTML=`
            <div class="ma-card ma-card-tight">
                <div class="text-sm font-semibold">Required before manual disbursement</div>
                <p class="ma-hint mt-1 text-xs">Pay a fixed fee of ₦${L(r.fee_amount_ngn??1500)} NGN. We verify payment before collecting guarantor details. This is not a loan transfer.</p>
            </div>
            <div class="mt-3" data-payment-actions></div>
            <p class="ma-hint mt-3 text-xs" data-payment-alert role="status"></p>`;let c=a.querySelector(`[data-payment-actions]`),l=a.querySelector(`[data-payment-alert]`);s?(c.innerHTML=`
                <div class="ma-card ma-card-tight">
                    <div class="flex items-center justify-between gap-2"><span class="ma-hint text-xs">Payment status</span><span class="ma-chip ma-chip-warning">Awaiting verification</span></div>
                    <div class="ma-hint mt-2 break-all text-xs">Reference: ${O(s.reference)}</div>
                    ${s.checkout_url?`<button type="button" class="ma-btn ma-btn-primary mt-3" data-continue><i class="ri-bank-card-line"></i> Continue payment</button>`:`<p class="ma-hint mt-3 text-xs">Checkout is being prepared. Refresh shortly to continue.</p>`}
                    <button type="button" class="ma-btn ma-btn-ghost mt-2" data-cancel><i class="ri-close-circle-line"></i> Cancel unpaid request</button>
                    <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh payment status</button>
                </div>`,c.querySelector(`[data-continue]`)?.addEventListener(`click`,()=>f(s.checkout_url)),c.querySelector(`[data-refresh]`)?.addEventListener(`click`,async n=>{let r=n.currentTarget;r.disabled=!0,r.textContent=`Checking…`;try{let n=await y(`/loans/${encodeURIComponent(t.id)}/guarantor-form/payment/${encodeURIComponent(s.reference)}/refresh`,{method:`POST`,body:{}});n.status===`completed`?(j(`Payment verified. The guarantor form is unlocked.`,`success`),o(),await J(e)):(l.textContent=n.status===`pending`?`Payment is still pending. Continue checkout or check again shortly.`:n.status===`unavailable`?`The provider could not confirm payment right now. Try again shortly.`:`This payment request is no longer pending. Refreshing your loan status.`,n.status!==`pending`&&n.status!==`unavailable`?(o(),await J(e)):(r.disabled=!1,r.innerHTML=`<i class="ri-refresh-line"></i> Refresh payment status`))}catch(e){r.disabled=!1,r.innerHTML=`<i class="ri-refresh-line"></i> Refresh payment status`,l.textContent=F(e)}}),c.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>_t({loanId:t.id,reference:s.reference,view:e,modal:i}))):n.available?(c.innerHTML=`
                <div class="ma-card ma-card-tight mb-2">
                    <div class="flex items-center justify-between gap-2"><span class="ma-hint text-xs">Active channel charge</span><span class="text-xs font-semibold">${L(n.charge_amount)} ${O(n.currency)}</span></div>
                    <p class="ma-hint mt-1 text-[0.68rem]">Fixed fee equivalent: ₦${L(n.fee_ngn)} NGN</p>
                </div>
                <button type="button" class="ma-btn ma-btn-primary mt-2" data-start><i class="ri-bank-card-line"></i> Pay fee and unlock form</button>
                <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh loan status</button>`,c.querySelector(`[data-start]`).addEventListener(`click`,async e=>{let n=e.currentTarget;n.disabled=!0,n.textContent=`Opening payment…`,l.textContent=``;try{f((await y(`/loans/${encodeURIComponent(t.id)}/guarantor-form/payment`,{method:`POST`,body:{}})).checkout_url),l.textContent=`Checkout opened. After payment, return here and refresh to unlock the form.`}catch(e){l.textContent=F(e),n.disabled=!1,n.innerHTML=`<i class="ri-bank-card-line"></i> Pay fee and unlock form`}}),c.querySelector(`[data-refresh]`).addEventListener(`click`,async()=>{o(),await J(e)})):(c.innerHTML=`
                <p class="ma-hint text-sm">${O(n.reason??`The guarantor-form fee channel is unavailable right now.`)}</p>
                <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh payment options</button>`,c.querySelector(`[data-refresh]`).addEventListener(`click`,async()=>{o(),await J(e)}))}document.body.appendChild(i)}function _t({loanId:e,reference:t,view:n,modal:r}){let i=D(`
        <div class="ma-sheet-backdrop" data-cancel-confirm>
            <section class="ma-sheet" role="dialog" aria-modal="true" aria-labelledby="cancel-form-title">
                <h3 id="cancel-form-title" class="ma-heading text-base font-bold">Cancel payment request?</h3>
                <p class="ma-hint mt-2 text-sm">We’ll verify with the provider first. The request can be removed only after a confirmed failure or expiry; pending, unverifiable, and successful payments will be kept.</p>
                <div class="mt-4 flex gap-2">
                    <button type="button" data-dismiss class="ma-btn ma-btn-ghost">Keep request</button>
                    <button type="button" data-confirm class="ma-btn ma-btn-danger">Cancel request</button>
                </div>
            </section>
        </div>`);document.body.appendChild(i),i.querySelector(`[data-dismiss]`).addEventListener(`click`,()=>i.remove()),i.addEventListener(`click`,e=>{e.target===i&&i.remove()}),i.querySelector(`[data-confirm]`).addEventListener(`click`,async a=>{let o=a.currentTarget;o.disabled=!0,o.textContent=`Checking…`;try{let a=await y(`/loans/${encodeURIComponent(e)}/guarantor-form/payment/${encodeURIComponent(t)}`,{method:`DELETE`});i.remove(),r.remove(),j(a.status===`cancelled`?`The provider confirmed a failure or expiry. The request was cancelled; you can start a new payment.`:a.status===`completed`?`Payment verified. The request was kept and your form is unlocked.`:a.status===`pending`?`Payment is still pending with the provider. The request was kept; continue checkout or check again shortly.`:a.status===`unavailable`?`Payment status could not be verified. The request was left unchanged.`:a.status===`needs_review`?`This payment needs staff review and cannot be deleted.`:`This payment request is no longer pending.`,a.status===`cancelled`||a.status===`completed`?`success`:`info`,3600),await J(n)}catch(e){o.disabled=!1,o.textContent=`Cancel request`,j(F(e),`error`)}})}var Y={category:null,provider:null,variation:null,customerName:null};async function vt(e){w(`services`),Y={category:null,provider:null,variation:null,customerName:null},await X(e)}async function X(e){e.innerHTML=``,e.appendChild(R(`VAS & Bills`)),e.appendChild(M(4));let t;try{t=await y(`/vas/categories`)}catch(t){e.innerHTML=``,e.appendChild(R(`VAS & Bills`)),e.appendChild(P(F(t),()=>X(e)));return}if(e.innerHTML=``,e.appendChild(R(`VAS & Bills`)),!t.enabled){e.appendChild(N(`ri-shopping-bag-3-line`,`Bill payments are offline`,`The VTU service is paused right now — try again later.`));return}let n={airtime:`ri-smartphone-line`,data:`ri-wifi-line`,tv:`ri-tv-2-line`,electricity:`ri-flashlight-line`,education:`ri-graduation-cap-line`},r=D(`<div class="grid grid-cols-2 gap-3"></div>`);(t.categories??[]).forEach(t=>{let i=D(`
            <button class="ma-card text-left">
                <span class="ma-icon mb-2 inline-flex"><i class="${n[t.key]??`ri-coupon-3-line`}"></i></span>
                <div class="text-sm font-bold">${O(t.label)}</div>
                <div class="ma-hint mt-0.5 text-[0.7rem] leading-snug">${O(t.blurb??`Instant top-up`)}</div>
            </button>`);i.addEventListener(`click`,()=>{a(`light`),yt(e,t.key)}),r.appendChild(i)}),e.appendChild(r),e.appendChild(xt())}async function yt(e,t){e.innerHTML=``,e.appendChild(R(`VAS & Bills`)),e.appendChild(M(4));let n;try{n=await y(`/vas/categories/${encodeURIComponent(t)}`)}catch(t){return j(F(t),`error`),X(e)}let r=n.category;Y={category:r,provider:null,variation:null,customerName:null},e.innerHTML=``,e.appendChild(R(r.label));let i=D(`<button class="ma-btn ma-btn-ghost ma-btn-auto mb-3"><i class="ri-arrow-left-line"></i> All categories</button>`);i.addEventListener(`click`,()=>X(e)),e.appendChild(i);let o=D(`<div></div>`);e.appendChild(o);let s=n.providers??[];o.appendChild(D(`<h3 class="ma-heading mb-2 text-base font-bold">Choose a provider</h3>`));let c=D(`<div class="grid grid-cols-2 gap-3"></div>`);s.forEach(t=>{let n=D(`
            <button class="ma-card text-center">
                ${t.logo_url?`<img src="${O(t.logo_url)}" alt="" class="mx-auto mb-2 h-10 w-10 rounded-xl object-contain" onerror="this.style.display='none'">`:`<span class="ma-icon mx-auto mb-2"><i class="ri-store-2-line"></i></span>`}
                <div class="text-sm font-bold">${O(t.label)}</div>
                <div class="ma-hint mt-0.5 text-[0.68rem]">${O(t.recipient)}</div>
            </button>`);n.addEventListener(`click`,()=>{a(`light`),Y.provider=t,bt(e,o,c,t)}),c.appendChild(n)}),o.appendChild(c)}async function bt(e,t,n,r){n.remove(),t.innerHTML=``;let i=Y.category;t.appendChild(D(`
        <div class="ma-card mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="ma-icon"><i class="ri-store-2-line"></i></span>
                <div>
                    <div class="text-sm font-bold">${O(r.label)}</div>
                    <div class="ma-hint text-[0.7rem]">${O(i.label)}</div>
                </div>
            </div>
            <button class="ma-btn ma-btn-ghost ma-btn-sm" data-change>Change</button>
        </div>`)),t.querySelector(`[data-change]`).addEventListener(`click`,()=>yt(e,i.key));let o=(i.pricing??`amount`)!==`amount`,s=r.verify||i.verify,c=D(`
        <div class="ma-card mb-3">
            <label class="ma-hint text-[0.7rem] font-semibold uppercase">${O(r.recipient??`Recipient`)}</label>
            <input class="ma-input mt-1" name="recipient" placeholder="${O(r.recipient??`Recipient`)}">

            ${i.key===`electricity`?`
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Meter type</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <button class="ma-btn ma-btn-sm" data-meter="prepaid">Prepaid</button>
                    <button class="ma-btn ma-btn-sm" data-meter="postpaid">Postpaid</button>
                </div>`:``}

            ${i.subscription?`
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Renewal</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <button class="ma-btn ma-btn-sm" data-sub="renew">Renew existing</button>
                    <button class="ma-btn ma-btn-sm" data-sub="new">New subscription</button>
                </div>`:``}

            ${s?`<button class="ma-btn mt-3" data-verify><i class="ri-search-line"></i> Verify customer</button><div class="ma-hint mt-1 text-[0.72rem]" data-verify-result></div>`:``}

            <div class="mt-3" data-plan-slot></div>

            <button class="ma-btn ma-btn-primary mt-3" data-buy disabled><i class="ri-shopping-cart-2-line"></i> Buy now</button>
        </div>`);t.appendChild(c);let l=`prepaid`,u=`renew`,d=[];c.querySelectorAll(`[data-meter]`).forEach(e=>{e.addEventListener(`click`,()=>{l=e.dataset.meter,c.querySelectorAll(`[data-meter]`).forEach(t=>t.classList.toggle(`ma-btn-primary`,t===e))})}),c.querySelector(`[data-meter="prepaid"]`)?.classList.add(`ma-btn-primary`),c.querySelectorAll(`[data-sub]`).forEach(e=>{e.addEventListener(`click`,()=>{u=e.dataset.sub,c.querySelectorAll(`[data-sub]`).forEach(t=>t.classList.toggle(`ma-btn-primary`,t===e))})}),c.querySelector(`[data-sub="renew"]`)?.classList.add(`ma-btn-primary`);let f=c.querySelector(`[name="recipient"]`),p=c.querySelector(`[data-plan-slot]`),m=c.querySelector(`[data-buy]`);if(o){p.innerHTML=`<div class="ma-hint">Loading plans…</div>`;try{if(d=(await y(`/vas/variations/${encodeURIComponent(r.id)}?category=${encodeURIComponent(i.key)}`)).plans??[],!d.length)p.innerHTML=`<div class="ma-hint text-[0.75rem]">No plans listed — enter an amount below.</div>`;else{let e=D(`<select class="ma-input" name="plan"><option value="">Select plan…</option></select>`);d.forEach(t=>{e.appendChild(D(`<option value="${O(t.code)}">${O(t.label)} — ₦${Number(t.amount).toLocaleString()}</option>`))}),p.innerHTML=`<label class="ma-hint text-[0.7rem] font-semibold uppercase">Plan</label>`,p.appendChild(e)}}catch{p.innerHTML=`<div class="ma-hint text-[0.75rem]">Could not load plans — enter an amount below.</div>`}}let h=D(`
        <div class="mt-3">
            <label class="ma-hint text-[0.7rem] font-semibold uppercase">Amount (₦)</label>
            <input class="ma-input mt-1" name="amount" type="number" min="1" inputmode="numeric" placeholder="Amount in naira">
        </div>`);c.insertBefore(h,m);let g=c.querySelector(`[name="amount"]`),_=c.querySelector(`[name="plan"]`),v=c.querySelector(`[data-verify-result]`);s&&c.querySelector(`[data-verify]`).addEventListener(`click`,async()=>{let e=f.value.trim();if(!e)return j(`Enter the ${r.recipient?.toLowerCase()??`recipient`} first.`,`error`);v.textContent=`Verifying…`;try{let t=await y(`/vas/verify`,{method:`POST`,body:{category:i.key,service_id:r.id,recipient:e,meter_type:i.key===`electricity`?l:void 0}});Y.customerName=t.name,v.innerHTML=`<span style="color:var(--ma-success)">✓ ${O(t.name)}</span>`,a(`success`)}catch(e){Y.customerName=null,v.textContent=F(e)}});let ee=()=>!f.value.trim()||s&&!Y.customerName?!1:_?.value?!0:parseFloat(g?.value??``)>0;c.addEventListener(`input`,e=>{e.target===f&&s&&(Y.customerName=null,v&&(v.textContent=``)),m.disabled=!ee()}),_?.addEventListener(`change`,()=>{m.disabled=!ee()}),m.addEventListener(`click`,async()=>{let t=d.find(e=>e.code===_?.value)??null,n=await ge(`Confirm purchase PIN`);if(!n)return;m.disabled=!0,m.textContent=`Purchasing…`;let o={category:i.key,service_id:r.id,recipient:f.value.trim(),phone:f.value.trim(),pin:n};t&&(o.variation_code=t.code),g?.value&&parseFloat(g.value)>0&&(o.amount=parseFloat(g.value)),i.key===`electricity`&&(o.meter_type=l),i.subscription&&(o.subscription_type=u),Y.customerName&&(o.customer_name=Y.customerName);try{await y(`/vas/purchase`,{method:`POST`,body:o}),a(`success`),j(`Purchase submitted — check orders below.`,`success`,3200),X(e)}catch(e){j(F(e),`error`),m.disabled=!1,m.innerHTML=`<i class="ri-shopping-cart-2-line"></i> Buy now`}})}function xt(){let e=D(`<div class="mt-5" id="vas-orders"></div>`);return y(`/vas/orders?per_page=5`).then(t=>{let n=t.items??[];n.length&&(e.innerHTML=`<h3 class="ma-heading mb-2 text-base font-bold">Recent orders</h3>`,n.forEach(t=>{let n={delivered:`ma-chip-success`,pending:`ma-chip-warning`,failed:`ma-chip-danger`,reversed:`ma-chip-danger`}[t.status]??`ma-chip-muted`;e.appendChild(D(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-receipt-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">${O(t.plan_label??t.service??`Order`)} · ₦${Number(t.amount_ngn).toLocaleString()}</div>
                        <div class="ma-hint truncate text-xs">${O(t.recipient??``)} · ${O(A(t.created_at))}${t.token?` · token: ${O(t.token)}`:``}</div>
                    </div>
                    <span class="ma-chip ${n}">${O(t.status)}</span>
                </div>`))}))}).catch(()=>{}),e}async function St(e){w(`services`),await Z(e)}async function Z(e){e.innerHTML=``,e.appendChild(R(`My Website`)),e.appendChild(M(3));let t;try{t=await y(`/website`)}catch(t){e.innerHTML=``,e.appendChild(R(`My Website`)),e.appendChild(P(F(t),()=>Z(e)));return}e.innerHTML=``,e.appendChild(R(`My Website`));let n=D(`<div></div>`);if(e.appendChild(n),n.appendChild(D(`
        <div class="ma-card mb-3 text-center">
            <span class="ma-icon mx-auto mb-2" style="width:3.2rem;height:3.2rem;font-size:1.5rem"><i class="ri-global-line"></i></span>
            <div class="ma-heading text-lg font-bold">${t.active?`Website is live`:`Website expired`}</div>
            <div class="ma-hint mt-1 text-[0.78rem]">
                ${t.expires_at?`${t.active?`Renews`:`Expired`} ${O(I(t.expires_at))} · ${t.duration_days}-day term`:`No active subscription yet.`}
            </div>
            <span class="ma-chip ${t.active?`ma-chip-success`:`ma-chip-danger`} mt-2 inline-flex">${t.active?`Active`:`Inactive`}</span>
        </div>`)),t.renewal_mode===`online`){let r=D(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-bold">Renew online</div>
                        <div class="ma-hint text-[0.72rem]">Pay with card — instant activation.</div>
                    </div>
                    <div class="ma-heading text-lg font-bold">${k(t.renewal_price_usd)}</div>
                </div>
                <button class="ma-btn ma-btn-primary mt-3" data-renew><i class="ri-bank-card-line"></i> Pay & renew</button>
            </div>`);r.querySelector(`[data-renew]`).addEventListener(`click`,async()=>{let t=r.querySelector(`[data-renew]`);t.disabled=!0,t.textContent=`Starting checkout…`;try{let n=await y(`/website/renew`,{method:`POST`});a(`light`),f(n.checkout_url),t.textContent=`Waiting for payment…`,Ct(e,n.reference,t)}catch(e){j(F(e),`error`),t.disabled=!1,t.innerHTML=`<i class="ri-bank-card-line"></i> Pay & renew`}}),n.appendChild(r)}else n.appendChild(D(`
            <div class="ma-card ma-card-tight mb-3">
                <i class="ri-information-line mr-1" style="color:var(--ma-info)"></i>
                <span class="text-[0.78rem]">Online renewal is paused — redeem a code from your vendor below.</span>
            </div>`));let r=D(`
        <div class="ma-card mb-3">
            <div class="text-sm font-bold">Renew with a code</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Customer price ${k(t.code_price_usd)} — codes come from Nextel vendors.</div>
            <input class="ma-input mt-3" data-code placeholder="Enter renewal code" autocomplete="off">
            <button class="ma-btn ma-btn-accent mt-2" data-redeem><i class="ri-coupon-3-line"></i> Redeem code</button>
        </div>`);r.querySelector(`[data-redeem]`).addEventListener(`click`,async()=>{let t=r.querySelector(`[data-code]`).value.trim();if(t.length<6)return j(`Enter the renewal code.`,`error`);let n=r.querySelector(`[data-redeem]`);n.disabled=!0,n.textContent=`Redeeming…`;try{let n=await y(`/website/renew/code`,{method:`POST`,body:{code:t}});a(`success`),j(`Website renewed — now valid until ${I(n.expires_at)}.`,`success`,4e3),Z(e)}catch(e){j(F(e),`error`),n.disabled=!1,n.innerHTML=`<i class="ri-coupon-3-line"></i> Redeem code`}}),n.appendChild(r)}function Ct(e,t,n){let r=0,i=setInterval(async()=>{r+=1;try{let n=(await y(`/payments/${encodeURIComponent(t)}/finalize`,{method:`POST`}))?.status;if(n===`completed`){clearInterval(i),a(`success`),j(`Payment received — website renewed! 🎉`,`success`,4e3),Z(e);return}if(n===`pending`||n===`unavailable`){clearInterval(i),a(`success`),j(`Payment received — your website will activate shortly.`,`success`,4e3),Z(e);return}}catch{}r>=12&&(clearInterval(i),n.disabled=!1,n.innerHTML=`<i class="ri-bank-card-line"></i> Pay & renew`,j(`Still waiting on the payment — reopen this page after paying.`,`info`,4200))},5e3)}var wt=null;async function Tt(e){w(`services`),Et(),await Dt(e)}function Et(){wt&&=(clearInterval(wt),null)}async function Dt(e){Et(),w(`services`),e.innerHTML=``,e.appendChild(R(`Live Chats`)),e.appendChild(M(4));let t;try{t=await y(`/chats`)}catch(t){e.innerHTML=``,e.appendChild(R(`Live Chats`)),e.appendChild(P(F(t),()=>Dt(e)));return}e.innerHTML=``,e.appendChild(R(`Live Chats`));let n=D(`<div></div>`);e.appendChild(n);let r=D(`
        <div class="ma-card mb-4">
            <div class="text-sm font-bold">Start a conversation</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Enter the username of your vendor or a Nextel agent.</div>
            <div class="mt-2 flex gap-2">
                <input class="ma-input flex-1" data-username placeholder="@username (no @)">
                <button class="ma-btn ma-btn-primary ma-btn-auto" data-open>Open</button>
            </div>
        </div>`);r.querySelector(`[data-open]`).addEventListener(`click`,async()=>{let t=r.querySelector(`[data-username]`).value.trim().replace(/^@/,``);if(!t)return j(`Enter the agent’s username.`,`error`);let n=r.querySelector(`[data-open]`);n.disabled=!0;try{let n=await y(`/chats`,{method:`POST`,body:{agent_username:t}});a(`success`),Ot(e,n.chat)}catch(e){j(F(e),`error`),n.disabled=!1}}),n.appendChild(r);let i=t.chats??[];if(!i.length){n.appendChild(N(`ri-chat-3-line`,`No conversations yet`,`Open one above — replies arrive right here.`));return}n.appendChild(D(`<h3 class="ma-heading mb-2 text-base font-bold">Conversations</h3>`)),i.forEach(t=>{let r=t.other??{},i=t.unread_count??0,o=D(`
            <button class="ma-card mb-2 flex w-full items-center gap-3 text-left">
                ${r.avatar_url?`<img src="${O(r.avatar_url)}" alt="" class="h-11 w-11 rounded-full object-cover">`:`<span class="ma-icon">${r.is_agent?`<i class="ri-customer-service-2-line"></i>`:`<i class="ri-user-3-line"></i>`}</span>`}
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">${O(r.full_name??r.username??`Conversation`)}</span>
                    <span class="ma-hint block truncate text-[0.72rem]">@${O(r.username??``)}</span>
                </span>
                <span class="flex flex-col items-end gap-1">
                    <span class="ma-hint text-[0.65rem]">${O(A(t.last_message_at))}</span>
                    ${i?`<span class="ma-chip ma-chip-accent">${i} new</span>`:``}
                </span>
            </button>`);o.addEventListener(`click`,()=>{a(`light`),Ot(e,t)}),n.appendChild(o)})}async function Ot(e,t){Et(),w(null);let n=t.other??{},r=O(n.full_name??n.username??`Chat`);e.innerHTML=``,e.appendChild(R(r,n.is_agent?`Agent`:null));let i=D(`<button class="ma-btn ma-btn-ghost ma-btn-auto mb-2"><i class="ri-arrow-left-line"></i> All chats</button>`);i.addEventListener(`click`,()=>Dt(e)),e.appendChild(i);let o=D(`<div class="ma-card mb-3 flex min-h-[40vh] flex-col gap-2 overflow-y-auto" style="max-height:52vh"></div>`);e.appendChild(o);let s=D(`
        <div class="flex gap-2" style="padding-bottom:0.25rem">
            <input class="ma-input flex-1" data-body placeholder="Write a message…" maxlength="5000">
            <button class="ma-btn ma-btn-primary ma-btn-auto" data-send aria-label="Send"><i class="ri-send-plane-fill"></i></button>
        </div>`);e.appendChild(s);let c=s.querySelector(`[data-body]`),l=s.querySelector(`[data-send]`),u=[],d=()=>{o.innerHTML=``,u.forEach(e=>o.appendChild(kt(e))),o.scrollTop=o.scrollHeight},f=async()=>{try{u=[...(await y(`/chats/${t.id}/messages?per_page=60`)).items??[]].sort((e,t)=>new Date(e.created_at)-new Date(t.created_at)),d()}catch{u.length||o.appendChild(P(`Could not load messages.`,f))}},p=async()=>{let e=c.value.trim();if(e){l.disabled=!0;try{let n=await y(`/chats/${t.id}/messages`,{method:`POST`,body:{body:e}});c.value=``,a(`light`),n?.message&&u.push(n.message),d(),await f()}catch(e){j(F(e),`error`)}finally{l.disabled=!1}}};l.addEventListener(`click`,p),c.addEventListener(`keydown`,e=>{e.key===`Enter`&&p()}),o.appendChild(At()),await f(),y(`/chats/${t.id}/read`,{method:`POST`}).catch(()=>{}),wt=setInterval(f,6e3)}function kt(e){if(e.type===`system`)return D(`<div class="ma-hint my-1 text-center text-[0.7rem]">${O(e.body)}</div>`);let t=T.user?.is_agent?e.sender===`agent`:e.sender===`customer`;return D(`
        <div class="flex ${t?`justify-end`:`justify-start`}">
            <div class="ma-bubble ${t?`ma-bubble-me`:`ma-bubble-them`}">
                ${O(e.body)}
                ${e.attachment_url?`<a href="${O(e.attachment_url)}" class="mt-1 block underline" target="_blank" rel="noopener">${O(e.attachment_name??`Attachment`)}</a>`:``}
                <div class="mt-1 text-right text-[0.6rem] opacity-60">${O(A(e.created_at))}</div>
            </div>
        </div>`)}function At(){return D(`<div class="flex justify-center py-6"><div class="ma-spinner"></div></div>`)}async function jt(e){w(`wallet`),e.innerHTML=``,e.appendChild(R(`Beneficiaries`)),e.appendChild(M(4));let t;try{t=await y(`/numbers`)}catch(t){e.innerHTML=``,e.appendChild(R(`Beneficiaries`)),e.appendChild(P(F(t),()=>jt(e)));return}e.innerHTML=``,e.appendChild(R(`Beneficiaries`));let n=t.numbers??[];if(!n.length){e.appendChild(N(`ri-phone-line`,`No numbers yet`,`Activate an eSIM plan to receive your Nextel number.`));return}let r=n.filter(e=>!e.is_expired),i=n.filter(e=>e.is_expired),o=D(`<div></div>`);e.appendChild(o),o.appendChild(D(`
        <div class="ma-card ma-card-tight mb-3 flex items-center justify-between">
            <span class="text-[0.82rem] font-semibold"><i class="ri-phone-line mr-1" style="color:var(--ma-primary-light)"></i>${r.length} active number${r.length===1?``:`s`}</span>
            <span class="ma-hint text-[0.7rem]">Live calls run from the web app</span>
        </div>`)),[...r,...i].forEach(e=>{let t=D(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-phone-fill"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold tracking-wide">${O(e.number)}</div>
                    <div class="ma-hint text-xs">
                        ${e.source===`package`?`Package number`:`Additional number`}
                        ${e.expires_at?` · ${e.is_expired?`expired`:`until`} ${O(I(e.expires_at))}`:` · permanent`}
                    </div>
                </div>
                <span class="ma-chip ${e.is_expired?`ma-chip-danger`:`ma-chip-success`}">${e.is_expired?`Expired`:`Active`}</span>
                <button class="ma-icon" style="width:2.25rem;height:2.25rem" data-copy aria-label="Copy number"><i class="ri-file-copy-line"></i></button>
            </div>`);t.querySelector(`[data-copy]`).addEventListener(`click`,async()=>{try{await navigator.clipboard.writeText(e.number),a(`success`),j(`Number copied`,`success`,1500)}catch{j(e.number,`info`,2500)}}),o.appendChild(t)})}async function Q(e,t={}){w(`profile`),e.innerHTML=``,e.appendChild(R(`Profile & Settings`)),e.appendChild(M(5));let n,r=[];try{n=(await y(`/user`)).user??{},r=(await y(`/countries`).catch(()=>({countries:[]}))).countries??[]}catch(t){e.innerHTML=``,e.appendChild(R(`Profile & Settings`)),e.appendChild(P(F(t),()=>Q(e)));return}E(n),e.innerHTML=``,e.appendChild(R(`Profile & Settings`));let i=D(`<div class="space-y-3"></div>`);e.appendChild(i),i.appendChild(Mt(n)),i.appendChild(Nt(n,r)),i.appendChild(Pt(n,t?.section===`security`)),i.appendChild(Ft(n)),i.appendChild(It(n));let a=D(`<button class="ma-btn ma-btn-danger mt-1"><i class="ri-logout-box-r-line"></i> Log out</button>`);a.addEventListener(`click`,async()=>{try{await y(`/logout`,{method:`POST`})}catch{}fe(),await C(`login`)}),i.appendChild(a)}function Mt(e){let t=D(`
        <div class="ma-card">
            <div class="mb-3 flex items-center gap-3">
                ${e.avatar_url?`<img src="${O(e.avatar_url)}" alt="" class="h-14 w-14 rounded-full object-cover" data-avatar-img>`:`<span class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background:var(--ma-accent);color:var(--ma-accent-text)" data-avatar-img>${O(Lt(e))}</span>`}
                <div class="min-w-0 flex-1">
                    <div class="truncate font-bold">${O(e.full_name??``)}</div>
                    <div class="ma-hint truncate text-xs">@${O(e.username??``)} · ${O(e.email??``)}</div>
                </div>
                <button class="ma-icon" data-avatar-pick aria-label="Change photo"><i class="ri-camera-line"></i></button>
                <input type="file" accept="image/*" class="hidden" data-avatar-file>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <input class="ma-input" name="first_name" value="${O(e.first_name??``)}" placeholder="First name">
                <input class="ma-input" name="last_name" value="${O(e.last_name??``)}" placeholder="Last name">
            </div>
            <input class="ma-input mt-2" name="phone" value="${O(e.phone??``)}" placeholder="Phone number">
            <input class="ma-input mt-2" name="telegram" value="${O(Rt(e.telegram_url))}" placeholder="Telegram username (optional)">
            <textarea class="ma-input mt-2" name="biography" rows="2" maxlength="300" placeholder="Bio (optional)">${O(e.biography??``)}</textarea>
            <button class="ma-btn ma-btn-primary mt-3" data-save>Save profile</button>
        </div>`),n=t.querySelector(`[data-avatar-file]`);return t.querySelector(`[data-avatar-pick]`).addEventListener(`click`,()=>n.click()),n.addEventListener(`change`,async()=>{let e=n.files?.[0];if(!e)return;if(e.size>4*1024*1024)return j(`Image must be under 4 MB.`,`error`);let t=new FormData;t.append(`photo`,e);try{let e=await fetch(`/api/v1/profile/avatar`,{method:`POST`,headers:{Authorization:`Bearer ${_()}`,Accept:`application/json`},body:t}),n=await e.json();if(!e.ok||!n.success)throw Error(n.message??`Upload failed.`);a(`success`),j(`Photo updated`,`success`);let r=n.data?.user?.avatar_url??n.data?.avatar_url;r&&T.user&&E({...T.user,avatar_url:r})}catch(e){j(e.message,`error`)}finally{n.value=``}}),t.querySelector(`[data-save]`).addEventListener(`click`,async()=>{let e=t.querySelector(`[data-save]`);e.disabled=!0,e.textContent=`Saving…`;let n={first_name:t.querySelector(`[name="first_name"]`).value.trim(),last_name:t.querySelector(`[name="last_name"]`).value.trim()||null,phone:t.querySelector(`[name="phone"]`).value.trim()||null,telegram:t.querySelector(`[name="telegram"]`).value.trim()||null,biography:t.querySelector(`[name="biography"]`).value.trim()||null};try{let e=await y(`/profile`,{method:`PUT`,body:n});e?.user&&E(e.user),a(`success`),j(`Profile saved`,`success`)}catch(e){j(F(e),`error`)}finally{e.disabled=!1,e.textContent=`Save profile`}}),t}function Nt(e,t){let n=[`USD`,...new Set(t.map(e=>e.currency_code).filter(Boolean))],r=e.display_currency??`USD`,i=D(`
        <div class="ma-card">
            <div class="text-sm font-bold">Display currency</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Balances are stored in USD — this mirrors the web switcher.</div>
            <div class="mt-2.5 flex flex-wrap gap-2">
                ${n.map(e=>`<button class="ma-chip ${e===r?`ma-chip-accent`:`ma-chip-muted`}" data-code="${O(e)}">${O(e)}</button>`).join(``)}
            </div>
        </div>`);return i.addEventListener(`click`,async t=>{let n=t.target.closest(`[data-code]`);if(!(!n||n.dataset.code===r))try{await y(`/profile`,{method:`PUT`,body:{first_name:e.first_name??``,last_name:e.last_name??null,phone:e.phone??null,telegram:Rt(e.telegram_url)||null,biography:e.biography??null,display_currency:n.dataset.code===`USD`?null:n.dataset.code}}),a(`success`),j(`Display currency set to ${n.dataset.code}`,`success`),T.user&&E({...T.user,display_currency:n.dataset.code===`USD`?null:n.dataset.code}),i.querySelectorAll(`[data-code]`).forEach(e=>{let t=e.dataset.code===n.dataset.code;e.classList.toggle(`ma-chip-accent`,t),e.classList.toggle(`ma-chip-muted`,!t)})}catch(e){j(F(e),`error`)}}),i}function Pt(e,t=!1){let n=!!e.has_transaction_pin,r=D(`
        <div class="ma-card">
            <div class="text-sm font-bold"><i class="ri-shield-check-line mr-1" style="color:var(--ma-primary-light)"></i>Security</div>

            <div class="mt-3 border-t pt-3" style="border-color:color-mix(in srgb, var(--ma-border) 40%, transparent)">
                <div class="text-[0.8rem] font-semibold">Change password</div>
                <input class="ma-input mt-2" type="password" data-cur autocomplete="current-password" placeholder="Current password">
                <input class="ma-input mt-2" type="password" data-new autocomplete="new-password" placeholder="New password">
                <input class="ma-input mt-2" type="password" data-new2 autocomplete="new-password" placeholder="Repeat new password">
                <button class="ma-btn mt-2" data-password>Update password</button>
            </div>

            <div class="mt-3 border-t pt-3" style="border-color:color-mix(in srgb, var(--ma-border) 40%, transparent)">
                <div class="flex items-center justify-between">
                    <div class="text-[0.8rem] font-semibold">Transaction PIN</div>
                    <span class="ma-chip ${n?`ma-chip-success`:`ma-chip-warning`}">${n?`Set`:`Not set`}</span>
                </div>

                ${n?`
                    <input class="ma-input mt-2" type="password" inputmode="numeric" maxlength="4" data-pin-cur placeholder="Current PIN">
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new placeholder="New PIN">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new2 placeholder="Repeat new">
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button class="ma-btn" data-pin-change>Change PIN</button>
                        <button class="ma-btn ma-btn-ghost ma-btn-auto" data-pin-reset>Forgot?</button>
                    </div>
                    <div class="mt-2 hidden" data-reset-box>
                        <input class="ma-input" data-reset-token placeholder="Reset token from support/vendor">
                        <button class="ma-btn mt-2" data-reset-go>Reset PIN with token</button>
                    </div>`:`
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new placeholder="New PIN">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new2 placeholder="Repeat new">
                    </div>
                    <button class="ma-btn mt-2" data-pin-setup>Set PIN</button>`}
            </div>
        </div>`),i=e=>e.value.replace(/\D/g,``).slice(0,4);r.querySelector(`[data-password]`)?.addEventListener(`click`,async()=>{let e=r.querySelector(`[data-cur]`).value,t=r.querySelector(`[data-new]`).value,n=r.querySelector(`[data-new2]`).value;if(!e||!t)return j(`Fill in your current and new password.`,`error`);if(t!==n)return j(`New passwords do not match.`,`error`);try{await y(`/account/password`,{method:`PUT`,body:{current_password:e,password:t,password_confirmation:n}}),a(`success`),j(`Password changed`,`success`),[`[data-cur]`,`[data-new]`,`[data-new2]`].forEach(e=>{r.querySelector(e).value=``})}catch(e){j(F(e),`error`)}}),r.querySelector(`[data-pin-setup]`)?.addEventListener(`click`,async()=>{let e=i(r.querySelector(`[data-pin-new]`)),t=i(r.querySelector(`[data-pin-new2]`));if(e.length!==4)return j(`PIN must be 4 digits.`,`error`);if(e!==t)return j(`PINs do not match.`,`error`);try{await y(`/account/pin`,{method:`POST`,body:{pin:e,pin_confirmation:t}}),a(`success`),j(`Transaction PIN set`,`success`),T.user&&E({...T.user,has_transaction_pin:!0}),Q(r.closest(`#view`))}catch(e){j(F(e),`error`)}}),r.querySelector(`[data-pin-change]`)?.addEventListener(`click`,async()=>{let e=i(r.querySelector(`[data-pin-cur]`)),t=i(r.querySelector(`[data-pin-new]`)),n=i(r.querySelector(`[data-pin-new2]`));if(e.length!==4||t.length!==4)return j(`PINs must be 4 digits.`,`error`);if(t!==n)return j(`New PINs do not match.`,`error`);try{await y(`/account/pin`,{method:`PUT`,body:{current_pin:e,pin:t,pin_confirmation:n}}),a(`success`),j(`PIN changed`,`success`)}catch(e){j(F(e),`error`)}});let o=r.querySelector(`[data-reset-box]`);return r.querySelector(`[data-pin-reset]`)?.addEventListener(`click`,()=>o.classList.toggle(`hidden`)),r.querySelector(`[data-reset-go]`)?.addEventListener(`click`,async()=>{let e=r.querySelector(`[data-reset-token]`).value.trim(),t=i(r.querySelector(`[data-pin-new]`)),n=i(r.querySelector(`[data-pin-new2]`));if(e.length<6)return j(`Enter your reset token.`,`error`);if(t.length!==4||t!==n)return j(`Enter a new 4-digit PIN twice.`,`error`);try{await y(`/account/pin/reset`,{method:`POST`,body:{token:e,pin:t,pin_confirmation:n}}),a(`success`),j(`PIN reset successfully`,`success`)}catch(e){j(F(e),`error`)}}),t&&setTimeout(()=>r.scrollIntoView({behavior:`smooth`,block:`start`}),150),r}function Ft(e){let t=e.telegram?.linked??!1,n=D(`
        <div class="ma-card flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm font-bold"><i class="ri-telegram-line mr-1" style="color:var(--ma-info)"></i>Telegram sign-in</div>
                <div class="ma-hint mt-0.5 text-[0.72rem]">${t?`Linked${e.telegram?.username?` to @${O(e.telegram.username)}`:``} — instant open next time.`:`Not linked — you log in with password each time.`}</div>
            </div>
            ${t?`<button class="ma-btn ma-btn-danger ma-btn-sm" data-unlink>Unlink</button>`:`<span class="ma-chip ma-chip-muted">Off</span>`}
        </div>`);return n.querySelector(`[data-unlink]`)?.addEventListener(`click`,async e=>{let t=e.currentTarget;t.disabled=!0;try{await y(`/telegram/unlink`,{method:`POST`}),a(`success`),j(`Telegram unlinked`,`success`),Q(n.closest(`#view`))}catch(e){j(F(e),`error`),t.disabled=!1}}),n}function It(e){if(e.onboarding?.email_verified)return D(`
            <div class="ma-card ma-card-tight flex items-center justify-between">
                <span class="text-[0.82rem] font-semibold"><i class="ri-mail-check-line mr-1" style="color:var(--ma-success)"></i>Email verified</span>
                <span class="ma-chip ma-chip-success">${O(e.email??``)}</span>
            </div>`);let t=D(`
        <div class="ma-card">
            <div class="text-sm font-bold"><i class="ri-mail-line mr-1" style="color:var(--ma-warning)"></i>Verify your email</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Withdrawals and purchases need a verified email.</div>
            <div class="mt-2 flex gap-2">
                <input class="ma-input flex-1" data-code inputmode="numeric" maxlength="6" placeholder="6-digit code">
                <button class="ma-btn ma-btn-primary ma-btn-auto" data-verify>Verify</button>
            </div>
            <button class="ma-btn ma-btn-ghost ma-btn-sm mt-2" data-resend>Resend code</button>
        </div>`);return t.querySelector(`[data-verify]`).addEventListener(`click`,async()=>{let e=t.querySelector(`[data-code]`).value.trim();if(!e)return j(`Enter the code from your email.`,`error`);try{await y(`/email/verify`,{method:`POST`,body:{code:e}}),a(`success`),j(`Email verified 🎉`,`success`),Q(t.closest(`#view`))}catch(e){j(F(e),`error`)}}),t.querySelector(`[data-resend]`).addEventListener(`click`,async e=>{e.currentTarget.disabled=!0;try{await y(`/email/resend`,{method:`POST`}),j(`Code sent — check your inbox.`,`success`)}catch(t){j(F(t),`error`),e.currentTarget.disabled=!1}}),t}function Lt(e){return`${e?.first_name?.[0]??``}${e?.last_name?.[0]??``}`.trim().toUpperCase()||`?`}function Rt(e){return String(e??``).replace(/^https?:\/\/(www\.)?t\.me\//,``).replace(/^@/,``)}async function zt(e){w(`services`),e.innerHTML=``,e.appendChild(R(`Vendor Dashboard`)),e.appendChild(M(5));let t,n;try{[t,n]=await Promise.all([y(`/agent/summary`),y(`/agent/plans`)])}catch(t){e.innerHTML=``,e.appendChild(R(`Vendor Dashboard`)),e.appendChild(P(F(t),()=>zt(e)));return}e.innerHTML=``,e.appendChild(R(`Vendor Dashboard`,t.is_special_vendor?`Special vendor`:`Vendor`));let r=D(`<div></div>`);e.appendChild(r),r.appendChild(D(`
        <div class="ma-card mb-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="ma-hint text-[0.64rem] font-semibold uppercase">Float balance</div>
                    <div class="ma-heading mt-0.5 text-2xl font-bold">${k(t.float_balance_usd)}</div>
                </div>
                <span class="ma-icon" style="width:2.8rem;height:2.8rem"><i class="ri-safe-2-line"></i></span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                ${Bt(`Minted`,t.tokens.total)}
                ${Bt(`Active`,t.tokens.active)}
                ${Bt(`Used`,t.tokens.used)}
            </div>
        </div>`)),r.appendChild(Vt(t)),r.appendChild(Ht(n.plans??[],e)),r.appendChild(Ut())}function Bt(e,t){return`<div class="rounded-[12px] border border-black/5 bg-white p-2.5"><div class="text-sm font-bold">${t}</div><div class="ma-hint text-[0.62rem]">${e}</div></div>`}function Vt(e){let t=D(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">Transfer float</div>
            <input class="ma-input" data-username placeholder="Recipient @username">
            <input class="ma-input mt-2" data-amount type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Amount (USD)">
            <button class="ma-btn ma-btn-primary mt-2" data-send><i class="ri-send-plane-line"></i> Send transfer</button>
        </div>`);return t.querySelector(`[data-send]`).addEventListener(`click`,async()=>{let n=t.querySelector(`[data-username]`).value.trim().replace(/^@/,``),r=parseFloat(t.querySelector(`[data-amount]`).value);if(!n)return j(`Enter the recipient’s username.`,`error`);if(!Number.isFinite(r)||r<=0)return j(`Enter the amount to transfer.`,`error`);if(r>e.float_balance_usd)return j(`That exceeds your float balance.`,`error`);let i=t.querySelector(`[data-send]`);i.disabled=!0,i.textContent=`Sending…`;try{await y(`/agent/transfer`,{method:`POST`,body:{recipient_username:n,amount:r}}),a(`success`),j(`Sent ${k(r)} to @${n}`,`success`,3200),zt(t.closest(`#view`))}catch(e){j(F(e),`error`),i.disabled=!1,i.innerHTML=`<i class="ri-send-plane-line"></i> Send transfer`}}),t}function Ht(e,t){let n=D(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">Mint tokens</div>
            <select class="ma-input" data-plan>
                <option value="">Select a plan…</option>
                ${[...new Set(e.map(e=>e.group))].map(t=>`
                    <optgroup label="${O(t)}">
                        ${e.filter(e=>e.group===t).map(e=>`<option value="${O(e.key)}">${O(e.label)} — agent ${k(e.agent_price)} / retail ${k(e.price)}</option>`).join(``)}
                    </optgroup>`).join(``)}
            </select>
            <input class="ma-input mt-2" data-qty type="number" min="1" max="500" inputmode="numeric" placeholder="Quantity (max 500)">
            <div class="ma-hint mt-1 text-[0.72rem]" data-cost></div>
            <button class="ma-btn ma-btn-primary mt-2" data-mint><i class="ri-coupon-3-line"></i> Mint tokens</button>
        </div>`),r=n.querySelector(`[data-plan]`),i=n.querySelector(`[data-qty]`),o=n.querySelector(`[data-cost]`),s=()=>{let t=e.find(e=>e.key===r.value),n=parseInt(i.value,10);o.textContent=t&&Number.isFinite(n)&&n>0?`Total cost: ${k(t.agent_price*n)} from your float.`:``};return r.addEventListener(`change`,s),i.addEventListener(`input`,s),n.querySelector(`[data-mint]`).addEventListener(`click`,async()=>{let e=r.value,o=parseInt(i.value,10);if(!e)return j(`Pick a plan to mint.`,`error`);if(!Number.isFinite(o)||o<1)return j(`Enter how many tokens to mint.`,`error`);let s=n.querySelector(`[data-mint]`);s.disabled=!0,s.textContent=`Minting…`;try{let n=await y(`/agent/tokens`,{method:`POST`,body:{plan_key:e,quantity:o}});a(`success`),j(`${n.batch?.quantity??o} token(s) generated — cost ${k(n.cost_usd)}`,`success`,4e3),zt(t)}catch(e){j(F(e),`error`),s.disabled=!1,s.innerHTML=`<i class="ri-coupon-3-line"></i> Mint tokens`}}),n}function Ut(){let e=D(`<div><h3 class="ma-heading mb-2 mt-1 text-base font-bold">Your tokens</h3><div data-rows></div></div>`),t=e.querySelector(`[data-rows]`);return t.appendChild(D(`<div class="flex justify-center py-6"><div class="ma-spinner"></div></div>`)),y(`/agent/tokens?per_page=20`).then(e=>{let n=e.items??[];if(t.innerHTML=``,!n.length){t.appendChild(N(`ri-coupon-3-line`,`No tokens yet`,`Mint your first batch above.`));return}n.forEach(e=>{let n=D(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-coupon-3-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-mono text-[0.82rem] font-semibold">${O(e.code)}</div>
                        <div class="ma-hint truncate text-xs">${O(e.label??e.type_name??``)} · ${O(A(e.created_at))}</div>
                    </div>
                    <span class="ma-chip ${e.used_at?`ma-chip-muted`:`ma-chip-success`}">${e.used_at?`Used`:`Active`}</span>
                    <button class="ma-icon" style="width:2.25rem;height:2.25rem" data-copy aria-label="Copy token"><i class="ri-file-copy-line"></i></button>
                </div>`);n.querySelector(`[data-copy]`).addEventListener(`click`,async()=>{try{await navigator.clipboard.writeText(e.code),a(`success`),j(`Token copied`,`success`,1500)}catch{j(e.code,`info`,2500)}}),t.appendChild(n)})}).catch(e=>{t.innerHTML=``,t.appendChild(P(F(e),()=>{}))}),e}var Wt=[{route:`home`,icon:`ri-home-5-line`,label:`Home`},{route:`earn`,icon:`ri-money-dollar-circle-line`,label:`Earn`},{route:`services`,icon:`ri-apps-2-line`,label:`Services`},{route:`wallet`,icon:`ri-wallet-3-line`,label:`Wallet`},{route:`profile`,icon:`ri-user-3-line`,label:`Profile`}];Gt();async function Gt(){t(),S(`home`,{render:$(_e),tab:!0}),S(`earn`,{render:$(Se),tab:!0}),S(`services`,{render:$(De),tab:!0}),S(`wallet`,{render:$(je),tab:!0}),S(`profile`,{render:$(Pe),tab:!0}),S(`alerts`,{render:ze}),S(`ads`,{render:He}),S(`rewards`,{render:Ke}),S(`referrals`,{render:Ze}),S(`plans`,{render:dt}),S(`paylater`,{render:ft}),S(`loan`,{render:ht}),S(`vas`,{render:vt}),S(`website`,{render:St}),S(`chats`,{render:Tt}),S(`withdraw`,{render:e=>(e.innerHTML=`
                <div class="ma-empty">
                    <i class="ri-loader-4-line animate-spin"></i>
                    <div>Opening withdrawal page…</div>
                </div>`,b(`withdraw`))}),S(`numbers`,{render:jt}),S(`settings`,{render:Q}),S(`vendor`,{render:zt}),S(`login`,{render:it,chrome:!1}),S(`register`,{render:ot,chrome:!1}),S(`forgot`,{render:lt,chrome:!1}),S(`verify`,{render:ut,chrome:!1});let e=document.getElementById(`mini-app`);if(e.innerHTML=`
        <main id="view" class="ma-view"></main>
        <nav class="ma-tabbar" id="tabbar" aria-label="Navigation">
            ${Wt.map(e=>`
                <button class="ma-tab" data-route="${e.route}" aria-label="${e.label}">
                    <i class="${e.icon}"></i>
                    <span>${e.label}</span>
                </button>`).join(``)}
        </nav>`,e.removeAttribute(`aria-busy`),oe(e.querySelector(`#view`)),e.querySelector(`#tabbar`).addEventListener(`click`,e=>{let t=e.target.closest(`.ma-tab`);t&&(a(`light`),C(t.dataset.route))}),re(()=>C(`login`)),new URLSearchParams(window.location.search).has(`logged_out`))return te(),C(`login`);await Kt()}function $(e){return async(t,n)=>{se(),await e(t,n)}}async function Kt(){if(_())try{return await y(`/user`),b(i())}catch{}let e=n();if(e)try{let t=await y(`/telegram/auth`,{method:`POST`,auth:!1,body:{init_data:e}});return t?.linked&&t?.token?(v(t.token),E(t.user),b(i())):C(`login`,{telegramName:t?.telegram_user?.first_name??null})}catch{return C(`login`)}return C(`login`)}