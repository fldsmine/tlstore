@php
    $sections = [
        [
            'heading' => 'Use of the Platform',
            'body' => 'You agree to use this platform only for lawful purposes. You must not misuse the platform, attempt unauthorized access, or engage in any activity that may harm the system or other users.',
        ],
        [
            'heading' => 'User Account',
            'body' => 'To access certain features, you may be required to create an account. You are responsible for maintaining the confidentiality of your account details and for all activities under your account.',
        ],
        [
            'heading' => 'Payment & Transactions',
            'body' => 'All payments made on the platform are processed securely. By making a payment, you agree to provide accurate and complete information. Transactions are subject to our pricing and earning periods.',
        ],
        [
            'heading' => 'Intellectual Property',
            'body' => 'We may update these Terms and Conditions from time to time. Continued use of the platform means you accept any changes made.',
        ],
        [
            'heading' => 'Cookies & Tracking Technologies',
            'body' => 'We use cookies and similar technology to enhance your experience, analyse usage, and improve our services.',
        ],
    ];
@endphp

<div>
    <x-public.page-hero badge="Terms" title="Our<br>Terms &amp; Condition" breadcrumb="Terms" />

    <x-public.shell class="bg-surface">
        <div class="max-w-[760px] mx-auto flex flex-col gap-8">

            <div class="flex flex-col gap-3">
                <h2 class="font-heading font-medium text-primary text-[18px] md:text-[22px]">Our Terms &amp; Condition</h2>
                <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">
                    By accessing or using our platform, you agree to be bound by these Terms and Conditions.
                    Please read them carefully.
                </p>
            </div>

            @foreach ($sections as $section)
                <div class="flex flex-col gap-3">
                    <h2 class="font-heading font-medium text-primary text-[18px] md:text-[22px]">{{ $section['heading'] }}</h2>
                    <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">{{ $section['body'] }}</p>
                </div>
            @endforeach
        </div>
    </x-public.shell>

    <x-public.cta />
</div>
