@php
    $sections = [
        [
            'heading' => 'Information We Collect',
            'intro' => 'We may collect the following types of information:',
            'items' => [
                'Personal Information (e.g., name, email address, phone number).',
                'Account Information (e.g., login details, profile data).',
                'Data usage (e.g., app interactions, features used).',
                'Device Information (e.g., IP address, device type, operating system).',
            ],
        ],
        [
            'heading' => 'How We Use Your Information',
            'intro' => 'We use your information to:',
            'items' => [
                'Provide and improve our services.',
                'Personalize your experience.',
                'Process transactions and payments.',
                'Communicate updates, promotions, or support messages.',
                'Ensure platform security and prevent fraud.',
            ],
        ],
        [
            'heading' => 'Data Security',
            'intro' => 'We implement appropriate security measures to protect your data from unauthorized access, loss, or misuse. However, no system is completely secure.',
            'items' => [],
        ],
        [
            'heading' => 'Your Rights',
            'intro' => 'You have the right to:',
            'items' => [
                'Access your personal data.',
                'Request corrections or updates.',
                'Request deletion of your data.',
                'Withdraw consent at any time.',
            ],
        ],
        [
            'heading' => 'Cookies & Tracking Technologies',
            'intro' => 'We use cookies and similar technology to enhance your experience, analyze site usage, and improve our services.',
            'items' => [],
        ],
    ];
@endphp

<div>
    <x-public.page-hero badge="Privacy Policy" title="Our<br>Privacy Policy" breadcrumb="Privacy Policy" />

    <x-public.shell class="bg-surface">
        <div class="max-w-[760px] mx-auto flex flex-col gap-8">

            <div class="flex flex-col gap-3">
                <h2 class="font-heading font-medium text-primary text-[18px] md:text-[22px]">Our Privacy Policy</h2>
                <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">
                    At Nextel, we value your privacy and are committed to protecting your personal information.
                    This Privacy Policy explains how we collect, use, and safeguard your data when you use them.
                </p>
            </div>

            @foreach ($sections as $section)
                <div class="flex flex-col gap-3">
                    <h2 class="font-heading font-medium text-primary text-[18px] md:text-[22px]">{{ $section['heading'] }}</h2>
                    <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">{{ $section['intro'] }}</p>
                    @if (! empty($section['items']))
                        <ol class="list-decimal pl-5 flex flex-col gap-2 font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">
                            @foreach ($section['items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </div>
    </x-public.shell>

    <x-public.cta />
</div>
