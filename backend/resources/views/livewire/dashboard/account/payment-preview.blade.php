<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Payment'" />

        <div class="flex flex-col gap-[22px] lg:max-w-[620px] lg:mx-auto lg:w-full">

        <div class="flex flex-col items-end text-right">
            <p class="font-sans text-black">
                <span class="text-muted text-[14px]">USD</span>
                <span class="font-semibold text-[16px] ml-1">$10.00</span>
            </p>
            <p class="font-sans text-muted text-[12px] mt-0.5">Johndee18@gmail.com</p>
        </div>

        <div class="border-t border-[#E5E1D7]"></div>

        <p class="font-sans text-muted text-[14px]">Enter your card details to pay</p>

        <div class="flex flex-col gap-[10px]">
            <x-auth.input label="CARD NUMBER" name="card_number" inputmode="numeric" placeholder="0000 0000 0000 0000" />

            <div class="flex gap-[10px]">
                <div class="flex-1 min-w-0">
                    <x-auth.input label="VALID TILL" name="valid_till" placeholder="MM/YY" />
                </div>
                <div class="flex-1 min-w-0">
                    <x-auth.input label="CVV" name="cvv" inputmode="numeric" placeholder="123" />
                </div>
            </div>
        </div>

        <a href="#" class="flex h-[45px] items-center justify-center rounded-[30px] bg-primary text-surface font-sans text-[14px] hover:opacity-95 transition">
            Pay Now&nbsp;&nbsp;$10.00
        </a>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
