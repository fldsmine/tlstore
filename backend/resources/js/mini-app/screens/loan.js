/**
 * Loan — terms, fee payment, required disbursement details and request history.
 */

import { api } from '../api.js';
import { el, esc, money, num, dateLabel, skeleton, errorState, emptyState, toast, errMsg, appBar } from '../ui.js';
import { enableBack } from '../router.js';
import { haptic, openLink } from '../telegram.js';

export async function renderLoan(view) {
    enableBack('services');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(appBar('Loan'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/loans');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Loan'));
        view.appendChild(errorState(errMsg(error), () => load(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('Loan'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    if (!data.enabled) {
        shell.appendChild(emptyState('ri-hand-coin-line', 'Loans are not enabled', 'This feature rolls out gradually.'));
        return;
    }

    const fee = data.application_fee ?? {};
    const channel = data.application_fee_payment ?? {};
    const guarantorPayment = data.guarantor_form_payment ?? {};
    const minAmount = Number(data.min_amount_usd ?? 1);
    const feeType = fee.type ?? 'fixed';
    const channelCurrency = channel.currency ?? fee.currency ?? '';
    const feeCopy = feeType === 'percentage'
        ? `${num(fee.percentage)}% of the requested principal${channelCurrency ? `, payable in ${esc(channelCurrency)} through the active channel` : ''}. Vendor fee coupons are not available.`
        : `Fixed fee: ${esc(fee.currency ?? '')} ${Number(fee.amount ?? 0).toFixed(2)}${channel.available ? `, or ${Number(channel.amount ?? 0).toFixed(2)} ${esc(channel.currency ?? '')} through the active channel` : ''}. Eligible vendor coupons are also accepted.`;

    // Terms.
    shell.appendChild(el(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">How it works</div>
            <ul class="ma-hint space-y-1.5 text-[0.78rem]">
                <li><i class="ri-arrow-up-line mr-1" style="color:var(--ma-primary-light)"></i>Borrow between <b>${money(minAmount)}</b> and <b>${money(data.max_amount_usd)}</b>.</li>
                <li><i class="ri-percent-line mr-1" style="color:var(--ma-primary-light)"></i>${num(data.service_fee_percent)}% service fee on approval.</li>
                <li><i class="ri-calendar-line mr-1" style="color:var(--ma-primary-light)"></i>Repay within ${data.repayment_days} days.</li>
                <li><i class="ri-bank-line mr-1" style="color:var(--ma-primary-light)"></i>${feeCopy}</li>
            </ul>
        </div>`));

    const hasUnresolved = data.has_unresolved_application ?? data.has_processing;
    if (!hasUnresolved) {
        const defaultDue = new Date(Date.now() + Math.min(data.repayment_days, 14) * 86400000);
        const loanTypes = Object.entries(data.loan_types ?? {});
        const loanTypeOptions = loanTypes.map(([key, label]) => `<option value="${esc(key)}">${esc(label)}</option>`).join('');
        const canPayByChannel = Boolean(channel.available);
        const form = el(`
            <div class="ma-card mb-3">
                <div class="ma-heading mb-2 text-base font-bold">Apply for a loan</div>
                <label class="ma-hint text-[0.7rem] font-semibold uppercase">Loan type</label>
                <select class="ma-input mt-1" name="loan_type">${loanTypeOptions}</select>
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Amount (USD)</label>
                <input class="ma-input mt-1" name="amount" type="number" min="${minAmount}" max="${Number(data.max_amount_usd)}" step="0.01" inputmode="decimal" placeholder="e.g. ${minAmount}">
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Repayment date</label>
                <input class="ma-input mt-1" name="due_date" type="date" value="${defaultDue.toISOString().slice(0, 10)}">
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
                ${feeType === 'fixed' ? `
                    <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Loan-fee coupon (optional)</label>
                    <input class="ma-input mt-1 uppercase" name="fee_coupon_code" maxlength="32" placeholder="Enter vendor coupon code">
                ` : ''}
                <label class="ma-hint mt-3 flex items-start gap-2 text-xs">
                    <input class="mt-0.5" name="agreed" type="checkbox">
                    <span>I accept the loan agreement, confirm the repayment date and bank details, and understand the application fee is non-refundable and does not guarantee approval.</span>
                </label>
                ${feeType === 'percentage' && !canPayByChannel ? '<p class="ma-hint mt-2 text-xs text-red-300">Percentage fees require an active payment channel. Applications cannot be submitted right now.</p>' : ''}
                <button class="ma-btn ma-btn-primary mt-3" data-apply ${feeType === 'percentage' && !canPayByChannel ? 'disabled' : ''}><i class="ri-send-plane-line"></i> ${feeType === 'fixed' ? 'Continue' : 'Pay fee and submit'}</button>
            </div>`);

        form.querySelector('[data-apply]').addEventListener('click', async () => {
            const amount = parseFloat(form.querySelector('[name="amount"]').value);
            const dueDate = form.querySelector('[name="due_date"]').value;
            const accountName = form.querySelector('[name="account_name"]').value.trim();
            const accountNumber = form.querySelector('[name="account_number"]').value.trim();
            const bankName = form.querySelector('[name="bank_name"]').value.trim();
            const agreed = form.querySelector('[name="agreed"]').checked;

            if (!Number.isFinite(amount) || amount < minAmount) return toast(`Enter a loan amount of at least ${money(minAmount)}.`, 'error');
            if (amount > data.max_amount_usd) return toast(`Maximum is ${money(data.max_amount_usd)}.`, 'error');
            if (!dueDate) return toast('Pick a repayment date.', 'error');
            if (!accountName || !accountNumber || !bankName) return toast('Enter the account name, account number and bank name for disbursement.', 'error');
            if (!agreed) return toast('Accept the loan agreement to continue.', 'error');

            const button = form.querySelector('[data-apply]');
            button.disabled = true;
            button.textContent = 'Submitting…';

            const body = {
                loan_type: form.querySelector('[name="loan_type"]').value,
                amount,
                due_date: dueDate,
                agreed,
                disbursement_account_name: accountName,
                disbursement_account_number: accountNumber,
                disbursement_bank_name: bankName,
            };
            const coupon = form.querySelector('[name="fee_coupon_code"]')?.value.trim();
            if (coupon) body.fee_coupon_code = coupon;

            try {
                const result = await api('/loans', { method: 'POST', body });
                haptic('success');

                if (result.checkout_url) {
                    const amountLabel = result.application_fee?.amount != null
                        ? `${Number(result.application_fee.amount).toFixed(2)} ${result.application_fee.currency}`
                        : 'the application fee';
                    toast(`Payment of ${amountLabel} opened. Your application will be submitted after verification.`, 'success', 4500);
                    openLink(result.checkout_url);
                } else {
                    toast('Fee coupon accepted — your loan application is now under review.', 'success', 3500);
                }

                await load(view);
            } catch (error) {
                toast(error.fieldMessage?.() || errMsg(error), 'error');
                button.disabled = false;
                button.innerHTML = `<i class="ri-send-plane-line"></i> ${feeType === 'fixed' ? 'Continue' : 'Pay fee and submit'}`;
            }
        });

        shell.appendChild(form);
    } else {
        const pending = data.pending_fee_payment;
        const card = el(`
            <div class="ma-card ma-card-tight mb-3 flex flex-col gap-3">
                <div class="flex items-center gap-2.5">
                    <i class="ri-time-line text-lg" style="color:var(--ma-warning)"></i>
                    <span class="text-[0.8rem] font-semibold">${data.has_processing ? 'An application is being reviewed.' : 'A loan-fee payment is awaiting verification.'}</span>
                </div>
                ${pending?.url ? '<button class="ma-btn ma-btn-primary" data-resume><i class="ri-bank-card-line"></i> Continue fee payment</button>' : ''}
            </div>`);
        card.querySelector('[data-resume]')?.addEventListener('click', () => openLink(pending.url));
        shell.appendChild(card);
    }

    // History.
    const loans = data.loans ?? [];
    if (!loans.length) return;

    shell.appendChild(el('<h3 class="ma-heading mb-2 mt-4 text-base font-bold">Your loans</h3>'));

    loans.forEach((loan) => {
        const chip = {
            approved: 'ma-chip-success',
            paid: 'ma-chip-success',
            processing: 'ma-chip-warning',
            guarantor_form_required: 'ma-chip-warning',
            rejected: 'ma-chip-danger',
        }[loan.status] ?? 'ma-chip-muted';
        const statusLabel = loan.status_label ?? ({
            approved: 'Approved',
            paid: 'Paid / disbursed',
            processing: 'Pending review',
            guarantor_form_required: 'Guarantor form required',
            rejected: 'Rejected',
        }[loan.status] ?? loan.status);

        shell.appendChild(el(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-hand-coin-line"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${money(loan.amount_usd)} → repay ${money(loan.total_repayment_usd)}</div>
                    <div class="ma-hint truncate text-xs">${esc(dateLabel(loan.created_at))} · due ${esc(loan.due_date)}${loan.rejection_reason ? ` · ${esc(loan.rejection_reason)}` : ''}</div>
                </div>
                <span class="ma-chip ${chip}">${esc(statusLabel)}</span>
            </div>`));

        if (loan.guarantor_form?.required) {
            const thumbnail = el(`
                <button type="button" class="ma-card ma-card-tight mt-2 w-full flex items-center gap-3 text-left" aria-label="Open guarantor form">
                    <span class="flex h-[58px] w-[48px] shrink-0 flex-col justify-between rounded-lg border border-white/15 bg-white p-1.5 shadow-sm">
                        <span class="flex items-center justify-between"><i class="ri-file-text-line text-sm" style="color:var(--ma-primary-light)"></i><span class="text-[5px] font-bold tracking-wide" style="color:var(--ma-primary-light)">NEXTEL</span></span>
                        <span class="space-y-1"><span class="block h-1 w-7 rounded bg-slate-200"></span><span class="block h-1 w-5 rounded bg-slate-200"></span><span class="block h-1 w-8 rounded bg-slate-200"></span></span>
                        <span class="text-[5px] font-bold uppercase tracking-wide text-slate-500">Form</span>
                    </span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">Guarantor form</span><span class="ma-hint mt-0.5 block text-xs">${loan.guarantor_form.pending_payment ? 'Payment pending · tap to continue' : loan.guarantor_form.fee_paid ? 'Payment verified · complete the form' : `Required · fee ₦${num(loan.guarantor_form.fee_amount_ngn)} NGN`}</span></span>
                    <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-primary-light)"></i>
                </button>`);
            thumbnail.addEventListener('click', () => openGuarantorForm(view, loan, guarantorPayment));
            shell.appendChild(thumbnail);
        } else if (loan.guarantor_form?.view_url || loan.guarantor_form?.download_url) {
            const download = el('<button type="button" class="ma-btn ma-btn-ghost mt-2"><i class="ri-file-text-line"></i> View / download guarantor form</button>');
            download.addEventListener('click', () => openLink(loan.guarantor_form.view_url || loan.guarantor_form.download_url));
            shell.appendChild(download);
        }
    });
}

function openGuarantorForm(view, loan, payment = {}) {
    const formState = loan.guarantor_form ?? {};
    const modal = el(`
        <div class="ma-sheet-backdrop" data-guarantor-modal>
            <section class="ma-sheet max-h-[92vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="guarantor-title">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="guarantor-title" class="ma-heading text-lg font-bold">Guarantor form</h2>
                        <p class="ma-hint mt-1 text-xs">${esc(loan.loan_type_label ?? 'Loan')} · before manual disbursement</p>
                    </div>
                    <button type="button" data-close class="ma-icon !h-9 !w-9" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="mt-4" data-guarantor-content></div>
            </section>
        </div>`);
    const content = modal.querySelector('[data-guarantor-content]');
    const close = () => modal.remove();

    modal.querySelector('[data-close]').addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    if (formState.form_submitted_at && (formState.view_url || formState.download_url)) {
        content.innerHTML = `
            <div class="ma-card ma-card-tight flex items-start gap-3">
                <i class="ri-checkbox-circle-line text-xl" style="color:var(--ma-success)"></i>
                <div><div class="text-sm font-semibold">Guarantor form submitted</div><p class="ma-hint mt-1 text-xs">Your form is complete. Staff can record a manual disbursement after this step.</p></div>
            </div>
            <button type="button" class="ma-btn ma-btn-primary mt-3" data-download><i class="ri-file-text-line"></i> View / download guarantor form</button>`;
        content.querySelector('[data-download]').addEventListener('click', () => openLink(formState.view_url || formState.download_url));
    } else if (formState.fee_paid) {
        content.innerHTML = `
            <div class="ma-card ma-card-tight mb-3">
                <div class="text-sm font-semibold">Payment verified · form unlocked</div>
                <p class="ma-hint mt-1 text-xs">Fee paid: ₦${num(formState.fee_amount_ngn)} NGN. Complete the guarantor’s details and confirm their consent.</p>
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
            </form>`;

        const form = content.querySelector('[data-guarantor-form]');
        const alert = content.querySelector('[data-form-alert]');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const fields = Object.fromEntries(new FormData(form).entries());
            const fullName = String(fields.full_name ?? '').trim();
            const signature = String(fields.signature ?? '').trim();
            if (signature !== fullName) {
                alert.hidden = false;
                alert.textContent = 'Type the guarantor’s full name exactly as shown above for the electronic signature.';
                return;
            }

            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;
            submit.textContent = 'Submitting…';
            alert.hidden = true;

            try {
                await api(`/loans/${encodeURIComponent(loan.id)}/guarantor-form`, {
                    method: 'POST',
                    body: {
                        full_name: fullName,
                        relationship: String(fields.relationship ?? '').trim(),
                        phone: String(fields.phone ?? '').trim(),
                        email: String(fields.email ?? '').trim() || null,
                        residential_address: String(fields.residential_address ?? '').trim(),
                        signature,
                        consent: form.querySelector('[name="consent"]').checked,
                    },
                });
                close();
                toast('Guarantor form submitted. You can now view or download the PDF.', 'success');
                await load(view);
            } catch (error) {
                alert.hidden = false;
                alert.textContent = errMsg(error);
                submit.disabled = false;
                submit.innerHTML = '<i class="ri-check-line"></i> Submit guarantor form';
            }
        });
    } else {
        const pending = formState.pending_payment;
        content.innerHTML = `
            <div class="ma-card ma-card-tight">
                <div class="text-sm font-semibold">Required before manual disbursement</div>
                <p class="ma-hint mt-1 text-xs">Pay a fixed fee of ₦${num(formState.fee_amount_ngn ?? 1500)} NGN. We verify payment before collecting guarantor details. This is not a loan transfer.</p>
            </div>
            <div class="mt-3" data-payment-actions></div>
            <p class="ma-hint mt-3 text-xs" data-payment-alert role="status"></p>`;
        const actions = content.querySelector('[data-payment-actions]');
        const alert = content.querySelector('[data-payment-alert]');

        if (pending) {
            actions.innerHTML = `
                <div class="ma-card ma-card-tight">
                    <div class="flex items-center justify-between gap-2"><span class="ma-hint text-xs">Payment status</span><span class="ma-chip ma-chip-warning">Awaiting verification</span></div>
                    <div class="ma-hint mt-2 break-all text-xs">Reference: ${esc(pending.reference)}</div>
                    ${pending.checkout_url ? '<button type="button" class="ma-btn ma-btn-primary mt-3" data-continue><i class="ri-bank-card-line"></i> Continue payment</button>' : '<p class="ma-hint mt-3 text-xs">Checkout is being prepared. Refresh shortly to continue.</p>'}
                    <button type="button" class="ma-btn ma-btn-ghost mt-2" data-cancel><i class="ri-close-circle-line"></i> Cancel unpaid request</button>
                    <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh payment status</button>
                </div>`;

            actions.querySelector('[data-continue]')?.addEventListener('click', () => openLink(pending.checkout_url));
            actions.querySelector('[data-refresh]')?.addEventListener('click', async (event) => {
                const button = event.currentTarget;
                button.disabled = true;
                button.textContent = 'Checking…';
                try {
                    const result = await api(`/loans/${encodeURIComponent(loan.id)}/guarantor-form/payment/${encodeURIComponent(pending.reference)}/refresh`, { method: 'POST', body: {} });
                    if (result.status === 'completed') {
                        toast('Payment verified. The guarantor form is unlocked.', 'success');
                        close();
                        await load(view);
                    } else {
                        alert.textContent = result.status === 'pending'
                            ? 'Payment is still pending. Continue checkout or check again shortly.'
                            : result.status === 'unavailable'
                                ? 'The provider could not confirm payment right now. Try again shortly.'
                                : 'This payment request is no longer pending. Refreshing your loan status.';
                        if (result.status !== 'pending' && result.status !== 'unavailable') {
                            close();
                            await load(view);
                        } else {
                            button.disabled = false;
                            button.innerHTML = '<i class="ri-refresh-line"></i> Refresh payment status';
                        }
                    }
                } catch (error) {
                    button.disabled = false;
                    button.innerHTML = '<i class="ri-refresh-line"></i> Refresh payment status';
                    alert.textContent = errMsg(error);
                }
            });
            actions.querySelector('[data-cancel]')?.addEventListener('click', () => confirmGuarantorCancellation({
                loanId: loan.id,
                reference: pending.reference,
                view,
                modal,
            }));
        } else if (payment.available) {
            actions.innerHTML = `
                <div class="ma-card ma-card-tight mb-2">
                    <div class="flex items-center justify-between gap-2"><span class="ma-hint text-xs">Active channel charge</span><span class="text-xs font-semibold">${num(payment.charge_amount)} ${esc(payment.currency)}</span></div>
                    <p class="ma-hint mt-1 text-[0.68rem]">Fixed fee equivalent: ₦${num(payment.fee_ngn)} NGN</p>
                </div>
                <button type="button" class="ma-btn ma-btn-primary mt-2" data-start><i class="ri-bank-card-line"></i> Pay fee and unlock form</button>
                <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh loan status</button>`;
            actions.querySelector('[data-start]').addEventListener('click', async (event) => {
                const button = event.currentTarget;
                button.disabled = true;
                button.textContent = 'Opening payment…';
                alert.textContent = '';
                try {
                    const result = await api(`/loans/${encodeURIComponent(loan.id)}/guarantor-form/payment`, { method: 'POST', body: {} });
                    openLink(result.checkout_url);
                    alert.textContent = 'Checkout opened. After payment, return here and refresh to unlock the form.';
                } catch (error) {
                    alert.textContent = errMsg(error);
                    button.disabled = false;
                    button.innerHTML = '<i class="ri-bank-card-line"></i> Pay fee and unlock form';
                }
            });
            actions.querySelector('[data-refresh]').addEventListener('click', async () => {
                close();
                await load(view);
            });
        } else {
            actions.innerHTML = `
                <p class="ma-hint text-sm">${esc(payment.reason ?? 'The guarantor-form fee channel is unavailable right now.')}</p>
                <button type="button" class="ma-btn ma-btn-ghost mt-2" data-refresh><i class="ri-refresh-line"></i> Refresh payment options</button>`;
            actions.querySelector('[data-refresh]').addEventListener('click', async () => {
                close();
                await load(view);
            });
        }
    }

    document.body.appendChild(modal);
}

function confirmGuarantorCancellation({ loanId, reference, view, modal }) {
    const confirmation = el(`
        <div class="ma-sheet-backdrop" data-cancel-confirm>
            <section class="ma-sheet" role="dialog" aria-modal="true" aria-labelledby="cancel-form-title">
                <h3 id="cancel-form-title" class="ma-heading text-base font-bold">Cancel payment request?</h3>
                <p class="ma-hint mt-2 text-sm">We’ll verify with the provider first. The request can be removed only after a confirmed failure or expiry; pending, unverifiable, and successful payments will be kept.</p>
                <div class="mt-4 flex gap-2">
                    <button type="button" data-dismiss class="ma-btn ma-btn-ghost">Keep request</button>
                    <button type="button" data-confirm class="ma-btn ma-btn-danger">Cancel request</button>
                </div>
            </section>
        </div>`);
    document.body.appendChild(confirmation);
    confirmation.querySelector('[data-dismiss]').addEventListener('click', () => confirmation.remove());
    confirmation.addEventListener('click', (event) => {
        if (event.target === confirmation) confirmation.remove();
    });
    confirmation.querySelector('[data-confirm]').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Checking…';
        try {
            const result = await api(`/loans/${encodeURIComponent(loanId)}/guarantor-form/payment/${encodeURIComponent(reference)}`, { method: 'DELETE' });
            confirmation.remove();
            modal.remove();
            const message = result.status === 'cancelled'
                ? 'The provider confirmed a failure or expiry. The request was cancelled; you can start a new payment.'
                : result.status === 'completed'
                    ? 'Payment verified. The request was kept and your form is unlocked.'
                    : result.status === 'pending'
                        ? 'Payment is still pending with the provider. The request was kept; continue checkout or check again shortly.'
                        : result.status === 'unavailable'
                            ? 'Payment status could not be verified. The request was left unchanged.'
                            : result.status === 'needs_review'
                                ? 'This payment needs staff review and cannot be deleted.'
                                : 'This payment request is no longer pending.';
            toast(message, result.status === 'cancelled' || result.status === 'completed' ? 'success' : 'info', 3600);
            await load(view);
        } catch (error) {
            button.disabled = false;
            button.textContent = 'Cancel request';
            toast(errMsg(error), 'error');
        }
    });
}
