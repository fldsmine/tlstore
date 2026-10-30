<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Support\DownloadLinks;

it('renders a signed PNG receipt page only for the loan owner', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->create(['user_id' => $owner->id]);
    $url = \App\Support\DownloadLinks::signed('loan.receipt', [
        'loan' => $loan->id,
        'download' => 1,
        'format' => 'png',
        'u' => $owner->id,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Loan application receipt')
        ->assertSee('download-png')
        ->assertSee('image/png');

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertNotFound();
});

it('downloads the guarantor form as a signed PDF only after verified submission', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->create([
        'user_id' => $owner->id,
        'status' => LoanStatus::APPROVED,
        'guarantor_form_fee_reference' => 'LGF-PDF-VERIFIED',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => [
            'full_name' => 'Taylor Guarantor',
            'relationship' => 'Sibling',
            'phone' => '+234 801 234 5678',
            'email' => null,
            'residential_address' => '10 Example Street, Port Harcourt',
            'occupation' => 'Teacher',
            'employer' => null,
            'signature' => 'Taylor Guarantor',
            'consent' => true,
        ],
        'guarantor_form_submitted_at' => now(),
    ]);
    $url = DownloadLinks::signed('loan.guarantor.form.download', [
        'loan' => $loan->id,
        'download' => 1,
        'u' => $owner->id,
    ]);

    $response = $this->get($url)->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertNotFound();
});

it('renders an owner-scoped web preview with back and PDF actions', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->create([
        'user_id' => $owner->id,
        'status' => LoanStatus::APPROVED,
        'guarantor_form_fee_reference' => 'LGF-PREVIEW-VERIFIED',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => [
            'full_name' => 'Taylor Guarantor',
            'relationship' => 'Sibling',
            'phone' => '+234 801 234 5678',
            'email' => null,
            'residential_address' => '10 Example Street, Port Harcourt',
            'occupation' => 'Legacy occupation should not render',
            'employer' => 'Legacy employer should not render',
            'signature' => 'Taylor Guarantor',
            'consent' => true,
        ],
        'guarantor_form_submitted_at' => now(),
    ]);
    $url = DownloadLinks::signed('loan.guarantor.form.view', [
        'loan' => $loan->id,
        'u' => $owner->id,
    ]);

    $response = $this->get($url)
        ->assertOk()
        ->assertSee('Guarantor Information Form')
        ->assertSee('Download PDF')
        ->assertSee('Back')
        ->assertDontSee('Occupation')
        ->assertDontSee('Employer')
        ->assertDontSee('Legacy occupation should not render')
        ->assertDontSee('Legacy employer should not render');

    expect($response->headers->get('content-type'))->toContain('text/html');

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertNotFound();
});

it('does not allow downloading a guarantor form before verified payment and submission', function () {
    $owner = User::factory()->create();
    $loan = Loan::factory()->create(['user_id' => $owner->id, 'status' => LoanStatus::APPROVED]);
    $url = DownloadLinks::signed('loan.guarantor.form.download', [
        'loan' => $loan->id,
        'download' => 1,
        'u' => $owner->id,
    ]);

    $this->get($url)->assertNotFound();
});
