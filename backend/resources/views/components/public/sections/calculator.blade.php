<section id="packages" class="relative overflow-hidden bg-background" style="border-top: 1px solid #8c8c8c;"
    x-data="{
        callsPerDay: 24,
        avgDuration: 8,
        earningPerMin: 50,
        selectedPackage: '300',
        get projectedEarning() {
            // Rate dial is illustrative; /1000 keeps the projection in USD scale.
            return Math.round(this.callsPerDay * this.avgDuration * this.earningPerMin * 30 / 1000);
        },
        formatUsd(amount) {
            return '$' + amount.toLocaleString('en-US');
        }
    }">

    <div class="absolute top-0 right-0 pointer-events-none"
         style="width: 900px; height: 900px; background: radial-gradient(circle at 65% 25%, rgba(72,148,138,0.32) 0%, rgba(100,160,100,0.12) 40%, transparent 68%); transform: translate(18%, -18%);"></div>

    <div class="hidden md:block relative z-10 pt-[150px] pb-[100px]">
        <div class="max-w-[1280px] mx-auto px-[60px]">

            <div class="mb-[150px] max-w-[700px]">
                <h2 class="font-heading font-semibold text-primary text-[96px] leading-[1.05] mb-5">
                    See Your Earning<br>Potential
                </h2>
                <p class="font-sans text-muted text-[24px] leading-relaxed">
                    Estimate how much you can earn based on your call minutes, activity, and selected package
                </p>
            </div>

            <div class="grid grid-cols-[385px_1fr] gap-[148px] items-start">

                <div>
                    <div class="flex items-center gap-[10px] mb-8">
                        <svg class="w-[18px] h-[18px] text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                        </svg>
                        <span class="font-heading font-medium text-[24px] text-text">PARAMETERS</span>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-heading font-semibold text-[16px] text-muted uppercase">Calls Per Day</span>
                            <span class="font-sans text-xs text-muted">Qty</span>
                        </div>
                        <div class="flex items-center gap-5">
                            <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                                <button @click="callsPerDay = Math.max(1, callsPerDay + 1)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                                <span class="w-12 text-center font-heading font-light text-[30px] text-text select-none" x-text="String(callsPerDay).padStart(2,'0')"></span>
                                <button @click="callsPerDay = Math.max(1, callsPerDay - 1)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                            </div>
                            <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full transition-all duration-300"
                                     :style="'width:' + Math.min(100, (callsPerDay / 50) * 100) + '%'"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-heading font-semibold text-[16px] text-muted uppercase">Avg Duration</span>
                            <span class="font-sans text-xs text-muted">Min</span>
                        </div>
                        <div class="flex items-center gap-5">
                            <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                                <button @click="avgDuration = Math.max(1, avgDuration + 1)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                                <span class="w-12 text-center font-heading font-light text-[30px] text-text select-none" x-text="String(avgDuration).padStart(2,'0')"></span>
                                <button @click="avgDuration = Math.max(1, avgDuration - 1)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                            </div>
                            <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full transition-all duration-300"
                                     :style="'width:' + Math.min(100, (avgDuration / 30) * 100) + '%'"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-10">
                        <div class="flex items-center justify-between mb-3">
                            <span class="font-heading font-semibold text-[16px] text-muted uppercase">Earning Per Min</span>
                            <span class="font-sans text-xs text-muted">Min</span>
                        </div>
                        <div class="flex items-center gap-5">
                            <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                                <button @click="earningPerMin = Math.max(5, earningPerMin + 5)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                                <span class="w-12 text-center font-heading font-light text-[30px] text-text select-none" x-text="earningPerMin"></span>
                                <button @click="earningPerMin = Math.max(5, earningPerMin - 5)"
                                        class="w-[60px] h-[60px] flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                            </div>
                            <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full transition-all duration-300"
                                     :style="'width:' + Math.min(100, (earningPerMin / 100) * 100) + '%'"></div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="font-heading font-semibold text-[16px] text-muted uppercase block mb-3">Package Selection</span>
                        <div class="flex gap-3">
                            <button @click="selectedPackage = '300'"
                                    :class="selectedPackage === '300' ? 'bg-primary text-secondary' : 'bg-surface text-text border border-border hover:bg-border/40'"
                                    class="px-7 py-5 rounded-2xl font-heading font-medium text-sm transition-all">300Min</button>
                            <button @click="selectedPackage = '500'"
                                    :class="selectedPackage === '500' ? 'bg-primary text-secondary' : 'bg-surface text-text border border-border hover:bg-border/40'"
                                    class="px-7 py-5 rounded-2xl font-heading font-medium text-sm transition-all">500Min</button>
                        </div>
                    </div>
                </div>

                <div class="rounded-[30px] p-[30px] border border-[rgba(220,235,106,0.39)]" style="background: linear-gradient(46.96deg, rgb(250,249,248) 1.49%, rgba(250,249,248,0.58) 98.62%);">

                    <div class="flex items-start justify-between mb-5">
                        <div>
                            <p class="font-heading font-semibold text-[16px] text-muted uppercase mb-1.5">Projected Earnings</p>
                            <p class="font-heading font-semibold text-[38px] text-text" x-text="formatUsd(projectedEarning)"></p>
                        </div>
                        <button class="w-[50px] h-[50px] rounded-2xl bg-background border border-border flex items-center justify-center hover:bg-border/40 transition-colors flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="font-heading font-semibold text-[16px] text-muted uppercase">Activity Variance</span>
                        </div>
                        <span class="font-heading text-xs text-text bg-[rgba(26,26,26,0.11)] rounded-[24px] px-2.5 py-1.5">Week 12 Projections</span>
                    </div>

                    <div class="flex gap-4 mb-6">
                        <div class="flex flex-col justify-between text-right shrink-0 w-20 pb-10">
                            <span class="font-heading text-[18px] text-text">$50</span>
                            <span class="font-heading text-[18px] text-text">$45</span>
                            <span class="font-heading text-[18px] text-text">$15</span>
                            <span class="font-heading text-[18px] text-text">$10</span>
                            <span class="font-heading text-[18px] text-text">$0</span>
                        </div>
                        <div class="relative flex-1" style="height: 210px;">
                            <div class="absolute top-0 left-[38%] -translate-x-1/2 bg-primary text-surface rounded-[24px] px-4 py-[15px] z-10 text-center pointer-events-none">
                                <div class="font-heading font-semibold text-[18px] leading-tight">$30</div>
                                <div class="font-sans text-[14px] text-surface/80 leading-tight">In 7Days</div>
                            </div>
                            <div class="absolute top-[52px] left-[38%] -translate-x-1/2 w-px h-10 border-l border-dashed border-muted/40 z-10"></div>
                            <div class="absolute bottom-0 left-0 right-0 flex items-end justify-between gap-1" style="height: 175px;">
                                @php $bars = [['Mon',81],['Tue',52],['Wed',96],['Thu',71],['Fri',42],['Sat',90],['Sun',79]]; @endphp
                                @foreach($bars as $bar)
                                <div class="flex flex-col items-center gap-1 flex-1">
                                    <div class="w-full rounded-full bg-primary" style="height: {{ $bar[1] }}px; max-width: 36px;"></div>
                                    <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                                        <span class="font-sans text-[10px] font-semibold text-primary leading-none">{{ $bar[0] }}</span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <div class="flex items-start gap-[10px] bg-[rgba(52,199,89,0.12)] border border-[#34c759] rounded-[24px] p-[20px] w-[370px]">
                            <svg class="w-[22px] h-[22px] text-[#34c759] flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2.5l1.5 8 8 1.5-8 1.5-1.5 8-1.5-8-8-1.5 8-1.5z"/>
                            </svg>
                            <div class="flex flex-col gap-[5px]">
                                <p class="font-heading font-medium text-[14px] text-text whitespace-nowrap">Maximize Your Earnings</p>
                                <p class="font-sans text-[12px] text-muted leading-relaxed">Upgrade your package to unlock higher daily returns.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="md:hidden relative z-10 px-5 pt-[100px] pb-16">

        <div class="absolute pointer-events-none" style="left: -8px; top: 83px; width: 450px; height: 252px; z-index: 0;">
            <img src="{{ asset('images/calculator/gradient-mobile.svg') }}" alt="" class="w-full h-full object-cover" />
        </div>

        <div class="mb-[30px] relative z-10">
            <h2 class="font-heading font-semibold text-primary text-[36px] leading-tight mb-3">
                See Your Earning<br>Potential
            </h2>
            <p class="font-sans text-muted text-sm leading-relaxed">
                Estimate how much you can earn based on your call minutes, activity, and selected package.
            </p>
        </div>

        <div class="mb-[30px]">
            <div class="flex items-center gap-[10px] mb-7 p-[10px]">
                <svg class="w-[18px] h-[17px] text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <span class="font-heading font-medium text-[18px] text-text">PARAMETERS</span>
            </div>

            <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-sans text-[10px] font-semibold tracking-[0.15em] text-muted uppercase">Calls Per Day</span>
                    <span class="font-sans text-[10px] text-muted">Qty</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                        <button @click="callsPerDay = Math.max(1, callsPerDay + 1)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                        <span class="w-10 text-center font-heading font-semibold text-xl text-text select-none" x-text="String(callsPerDay).padStart(2,'0')"></span>
                        <button @click="callsPerDay = Math.max(1, callsPerDay - 1)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                    </div>
                    <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300"
                             :style="'width:' + Math.min(100, (callsPerDay / 50) * 100) + '%'"></div>
                    </div>
                </div>
            </div>

            <div class="mb-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-sans text-[10px] font-semibold tracking-[0.15em] text-muted uppercase">Avg Duration</span>
                    <span class="font-sans text-[10px] text-muted">Min</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                        <button @click="avgDuration = Math.max(1, avgDuration + 1)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                        <span class="w-10 text-center font-heading font-semibold text-xl text-text select-none" x-text="String(avgDuration).padStart(2,'0')"></span>
                        <button @click="avgDuration = Math.max(1, avgDuration - 1)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                    </div>
                    <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300"
                             :style="'width:' + Math.min(100, (avgDuration / 30) * 100) + '%'"></div>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-sans text-[10px] font-semibold tracking-[0.15em] text-muted uppercase">Earning Per Min</span>
                    <span class="font-sans text-[10px] text-muted">Min</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex items-center border border-border rounded-full overflow-hidden bg-background flex-shrink-0">
                        <button @click="earningPerMin = Math.max(5, earningPerMin + 5)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">+</button>
                        <span class="w-10 text-center font-heading font-semibold text-xl text-text select-none" x-text="earningPerMin"></span>
                        <button @click="earningPerMin = Math.max(5, earningPerMin - 5)"
                                class="w-10 h-10 flex items-center justify-center text-xl font-light text-text hover:bg-border/40 transition-colors">−</button>
                    </div>
                    <div class="flex-1 h-[5px] bg-primary/20 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full transition-all duration-300"
                             :style="'width:' + Math.min(100, (earningPerMin / 100) * 100) + '%'"></div>
                    </div>
                </div>
            </div>

            <div>
                <span class="font-heading font-semibold text-[12px] text-muted uppercase block mb-2">PACKAGE SELECTION</span>
                <div class="flex gap-[15px]">
                    <button @click="selectedPackage = '300'"
                            :class="selectedPackage === '300' ? 'bg-primary text-secondary' : 'bg-surface text-text'"
                            class="h-[80px] px-[20px] rounded-[20px] font-heading text-[16px] transition-all">300Min</button>
                    <button @click="selectedPackage = '500'"
                            :class="selectedPackage === '500' ? 'bg-primary text-secondary' : 'bg-surface text-text'"
                            class="h-[80px] px-[20px] rounded-[20px] font-heading text-[16px] transition-all">500Min</button>
                </div>
            </div>
        </div>

        <div class="rounded-[30px] p-[30px]" style="background: linear-gradient(59.85deg, rgb(250,249,248) 1.49%, rgba(250,249,248,0.58) 98.62%);" >

            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="font-sans text-[10px] font-semibold tracking-[0.15em] text-muted uppercase mb-1">Projected Earnings</p>
                    <p class="font-heading font-bold text-[38px] text-text" x-text="formatUsd(projectedEarning)"></p>
                </div>
                <button class="w-10 h-10 rounded-xl bg-background border border-border flex items-center justify-center hover:bg-border/40 transition-colors flex-shrink-0 mt-1">
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="font-sans text-[10px] font-semibold tracking-[0.15em] text-muted uppercase">Activity Variance</span>
                </div>
                <span class="font-heading text-[10px] text-text bg-[rgba(26,26,26,0.11)] rounded-[24px] px-2.5 py-1">Week 12 Projections</span>
            </div>

            <div class="relative mb-4" style="height: 200px;">
                <div class="absolute top-0 left-[38%] -translate-x-1/2 bg-[#dfdfdc] text-text rounded-[18px] px-[16px] py-[16px] z-10 text-center pointer-events-none">
                    <div class="font-heading font-semibold text-[14px] leading-tight">$30</div>
                    <div class="font-sans text-[12px] text-muted leading-tight">In 7Days</div>
                </div>
                <div class="absolute top-[44px] left-[38%] -translate-x-1/2 w-px h-8 border-l border-dashed border-muted/40 z-10"></div>
                <div class="absolute bottom-0 left-0 right-0 flex items-end justify-between gap-1" style="height: 170px;">
                    @php $bars = [['Mon',81],['Tue',52],['Wed',96],['Thu',71],['Fri',42],['Sat',90],['Sun',79]]; @endphp
                    @foreach($bars as $bar)
                    <div class="flex flex-col items-center gap-1 flex-1">
                        <div class="w-full rounded-full bg-primary" style="height: {{ $bar[1] }}px; max-width: 30px;"></div>
                        <div class="w-6 h-6 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                            <span class="font-sans text-[8px] font-semibold text-primary leading-none">{{ $bar[0] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end pt-[30px]">
                <div class="flex items-start gap-[10px] bg-[rgba(52,199,89,0.12)] border border-[#34c759] rounded-[24px] p-[20px] w-[268px]">
                    <svg class="w-[22px] h-[22px] text-[#34c759] flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.5l1.5 8 8 1.5-8 1.5-1.5 8-1.5-8-8-1.5 8-1.5z"/>
                    </svg>
                    <div class="flex flex-col gap-[5px]">
                        <p class="font-heading font-medium text-[14px] text-text whitespace-nowrap">Maximize Your Earnings</p>
                        <p class="font-sans text-[12px] text-muted leading-relaxed">Upgrade your package to unlock higher daily returns.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
