/**
 * Forgot password — requests the reset OTP (POST /password/forgot) and then
 * resets with code + new password (POST /password/reset), returning to login.
 */

import { api } from '../api.js';
import { el, toast, errMsg } from '../ui.js';
import { authShell, pillField } from '../auth-ui.js';
import { haptic } from '../telegram.js';
import { navigate, enableBack } from '../router.js';

export async function renderForgot(view) {
    enableBack('login');

    view.innerHTML = '';

    const shell = authShell({
        title: 'Forgot Password?',
        subtitle: 'We’ll email you a 6-digit reset code.',
        backTo: 'login',
        centered: true,
    });

    view.appendChild(shell);
    const body = shell.querySelector('.ma-auth-body');
    body.classList.add('ma-auth-body-low');

    body.appendChild(pillField({ label: 'Email', name: 'email', icon: 'ri-mail-line', type: 'email', placeholder: 'Enter your email address' }));

    const send = el('<button class="ma-btn ma-btn-primary" style="border-radius:16px"><i class="ri-send-plane-line"></i> Send reset code</button>');
    body.appendChild(send);

    // Reset step (revealed after the code is sent).
    const resetBlock = el(`
        <div class="hidden">
            <div class="mb-4 mt-5">
                <label class="mb-1.5 block text-[0.85rem] font-medium">Reset code</label>
                <input class="ma-input ma-pill text-center text-xl tracking-[0.4em]" name="code" inputmode="numeric" maxlength="6" placeholder="••••••">
            </div>
            ${'' /* password fields appended below via pillField */}
        </div>`);
    body.appendChild(resetBlock);

    let passwordFieldsAdded = false;
    const reset = el('<button class="ma-btn ma-btn-accent hidden mt-1" style="border-radius:16px"><i class="ri-lock-unlock-line"></i> Reset password</button>');
    body.appendChild(reset);

    const emailValue = () => body.querySelector('[name="email"]').value.trim();

    send.addEventListener('click', async () => {
        const email = emailValue();
        if (!/^\S+@\S+\.\S+$/.test(email)) return toast('Enter a valid email address.', 'error');

        send.disabled = true;
        send.textContent = 'Sending…';

        try {
            await api('/password/forgot', { method: 'POST', auth: false, body: { email } });
            haptic('success');
            toast('Reset code sent — check your inbox.', 'success');

            resetBlock.classList.remove('hidden');
            reset.classList.remove('hidden');

            if (!passwordFieldsAdded) {
                passwordFieldsAdded = true;
                resetBlock.appendChild(pillField({ label: 'New password', name: 'password', icon: 'ri-lock-2-line', type: 'password', placeholder: 'At least 8 characters' }));
                resetBlock.appendChild(pillField({ label: 'Confirm new password', name: 'password_confirmation', icon: 'ri-lock-2-line', type: 'password', placeholder: 'Repeat password' }));
            }

            body.querySelector('[name="code"]').focus();
        } catch (error) {
            toast(errMsg(error), 'error');
        } finally {
            send.disabled = false;
            send.innerHTML = '<i class="ri-send-plane-line"></i> Send reset code';
        }
    });

    reset.addEventListener('click', async () => {
        const code = body.querySelector('[name="code"]').value.trim();
        const password = body.querySelector('[name="password"]').value;
        const confirmation = body.querySelector('[name="password_confirmation"]').value;

        if (code.length !== 6) return toast('Enter the 6-digit code.', 'error');
        if (password.length < 8) return toast('Password must be at least 8 characters.', 'error');
        if (password !== confirmation) return toast('Passwords do not match.', 'error');

        reset.disabled = true;
        reset.textContent = 'Resetting…';

        try {
            await api('/password/reset', { method: 'POST', auth: false, body: { email: emailValue(), code, password } });
            haptic('success');
            toast('Password reset — log in with your new password.', 'success', 3500);
            await navigate('login');
        } catch (error) {
            toast(errMsg(error), 'error');
            reset.disabled = false;
            reset.innerHTML = '<i class="ri-lock-unlock-line"></i> Reset password';
        }
    });
}
