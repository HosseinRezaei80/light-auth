(function () {
    'use strict';

    // ==========================================
    // ابزارهای کمکی
    // ==========================================
    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    var AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';
    var OTP_LENGTH = (typeof la_ajax !== 'undefined' && Number(la_ajax.otpLength) === 5) ? 5 : 6;
    var RESEND_SECONDS = Number(la_ajax.resendSeconds) || 60;
    var NETWORK_ERROR = 'ارتباط با سرور برقرار نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';
    var GENERIC_ERROR = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';

    // تبدیل اعداد فارسی و عربی به انگلیسی
    function toEnDigits(str) {
        return String(str || '').replace(/[۰-۹٠-٩]/g, function (d) {
            var i = FA_DIGITS.indexOf(d);
            return String(i > -1 ? i : AR_DIGITS.indexOf(d));
        });
    }

    // یکسان‌سازی شماره: پشتیبانی از +98، 0098 و شماره بدون صفر
    function normalizePhone(raw) {
        var d = toEnDigits(raw).replace(/\D/g, '');
        if (d.indexOf('0098') === 0) {
            d = '0' + d.slice(4);
        } else if (d.indexOf('98') === 0 && d.length === 12) {
            d = '0' + d.slice(2);
        } else if (d.charAt(0) === '9' && d.length === 10) {
            d = '0' + d;
        }
        return d; // Never silently trim extra digits: show the validation error instead.
    }

    function isValidPhone(phone) {
        return /^09[0-9]{9}$/.test(phone);
    }

    function formatPhone(phone) {
        return isValidPhone(phone)
            ? phone.slice(0, 4) + ' ' + phone.slice(4, 7) + ' ' + phone.slice(7)
            : phone;
    }

    // استخراج کد تنظیم‌شده (حتی اگر کل متن پیامک Paste شود)
    function normalizeOtp(raw) {
        var en = toEnDigits(raw);
        var digits = en.replace(/\D/g, '');
        if (digits.length > OTP_LENGTH) {
            var match = en.match(new RegExp('\\d{' + OTP_LENGTH + '}'));
            if (match) return match[0];
        }
        return digits.slice(0, OTP_LENGTH);
    }

    function getResponseMessage(res) {
        if (!res) return '';
        if (typeof res.data === 'string') return res.data;
        return (res.data && res.data.message) || '';
    }

    // Fetch anti-CSRF nonce on demand so full-page cache cannot serve an expired token.
    // The nonce is cached briefly in memory only; no cookies or storage are added.
    var authNonce = '';
    var nonceUntil = 0;
    var noncePending = null;
    function freshNonce(force) {
        if (!force && authNonce && Date.now() < nonceUntil) return Promise.resolve(authNonce);
        if (!force && noncePending) return noncePending;
        var formData = new FormData();
        formData.append('action', 'la_get_nonce');
        var promise = fetch(la_ajax.url, {
            method: 'POST', body: formData, credentials: 'same-origin', cache: 'no-store'
        }).then(function (res) {
            if (!res.ok) throw new Error('Nonce request failed');
            return res.json();
        }).then(function (json) {
            if (!json || !json.success || !json.data || !json.data.nonce) throw new Error('Invalid nonce response');
            authNonce = String(json.data.nonce);
            nonceUntil = Date.now() + 10 * 60 * 1000;
            return authNonce;
        });
        noncePending = promise;
        return promise.then(function (nonce) {
            noncePending = null;
            return nonce;
        }, function (err) {
            noncePending = null;
            throw err;
        });
    }

    function post(data) {
        function submit(nonce, mayRetry) {
            var formData = new FormData();
            Object.keys(data).forEach(function (key) { formData.append(key, data[key]); });
            formData.append('security', nonce);
            return fetch(la_ajax.url, {
                method: 'POST', body: formData, credentials: 'same-origin', cache: 'no-store'
            }).then(function (res) {
                return res.text().then(function (body) {
                    // WordPress returns -1 when check_ajax_referer() rejects a nonce.
                    if (mayRetry && (body.trim() === '-1' || res.status === 403)) {
                        authNonce = '';
                        nonceUntil = 0;
                        return freshNonce(true).then(function (newNonce) { return submit(newNonce, false); });
                    }
                    return JSON.parse(body);
                });
            });
        }
        return freshNonce(false).then(function (nonce) { return submit(nonce, true); });
    }

    function shake(el) {
        el.classList.remove('la-shake');
        void el.offsetWidth; // ری‌استارت انیمیشن
        el.classList.add('la-shake');
        setTimeout(function () { el.classList.remove('la-shake'); }, 450);
    }

    function setLoading(btn, isLoading) {
        var textEl = btn.querySelector('.la-btn-text');
        if (textEl && !btn.dataset.defaultText) {
            btn.dataset.defaultText = textEl.textContent;
        }
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        if (textEl) {
            textEl.textContent = isLoading
                ? (btn.dataset.loadingText || btn.dataset.defaultText)
                : btn.dataset.defaultText;
        }
    }

    function setButtonText(btn, text) {
        var textEl = btn.querySelector('.la-btn-text');
        btn.dataset.defaultText = text;
        if (textEl) textEl.textContent = text;
    }

    function isVisible(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    // ==========================================
    // فرم ورود/ثبت‌نام (داخل صفحه و پاپ‌آپ)
    // ==========================================
    function initAuthBox(box) {
        var phoneForm = box.querySelector('.la-step-phone');
        var verifyForm = box.querySelector('.la-step-verify');
        if (!phoneForm || !verifyForm) return;

        var el = {
            title: box.querySelector('.la-title'),
            subtitle: box.querySelector('.la-subtitle'),
            msg: box.querySelector('.la-message'),
            phone: box.querySelector('.la-phone-input'),
            phoneErr: phoneForm.querySelector('.la-field-error'),
            sendBtn: phoneForm.querySelector('.la-btn'),
            hiddenPhone: box.querySelector('.la-hidden-phone'),
            phoneDisplay: box.querySelector('.la-phone-display'),
            editPhone: box.querySelector('.la-edit-phone'),
            nameWrap: box.querySelector('.la-fullname-wrap'),
            name: box.querySelector('.la-fullname-input'),
            otp: box.querySelector('.la-otp-input'),
            remember: box.querySelector('.la-remember'),
            verifyBtn: verifyForm.querySelector('.la-btn[type="submit"]'),
            timer: box.querySelector('.la-timer'),
            timerValue: box.querySelector('.la-timer-value'),
            resend: box.querySelector('.la-resend'),
            success: box.querySelector('.la-success'),
            successText: box.querySelector('.la-success-text')
        };
        el.phoneWrap = el.phone.closest('.la-input-wrap');
        el.nameErr = el.nameWrap.querySelector('.la-field-error');
        el.otpErr = el.otp.closest('.la-field').querySelector('.la-field-error');

        var TEXT = {
            phoneTitle: el.title.textContent,
            phoneSubtitle: el.subtitle.textContent,
            loginTitle: 'کد تایید را وارد کنید',
            loginSubtitle: 'کد ' + OTP_LENGTH + ' رقمی پیامک‌شده را وارد کنید',
            loginBtn: 'ورود به حساب',
            registerTitle: 'ساخت حساب جدید',
            registerSubtitle: 'شماره شما تأیید شد. نام خود را برای تکمیل ثبت‌نام وارد کنید',
            registerBtn: 'ساخت حساب و ورود'
        };

        var state = {
            busy: false,
            isNewUser: false,
            sentPhone: '',
            timerEnd: 0,
            timerId: null,
            lastAutoOtp: ''
        };

        // ---------- نمایش پیام و خطا ----------
        function showMessage(text, type) {
            el.msg.textContent = text;
            el.msg.className = 'la-message ' + type;
            el.msg.hidden = false;
        }

        function hideMessage() {
            el.msg.hidden = true;
            el.msg.textContent = '';
        }

        function setFieldError(input, errEl, text) {
            var field = input.closest('.la-field');
            field.classList.toggle('has-error', !!text);
            input.setAttribute('aria-invalid', text ? 'true' : 'false');
            errEl.textContent = text || '';
            errEl.hidden = !text;
        }

        // ---------- مراحل ----------
        function setStep(step) {
            box.setAttribute('data-step', step);
            phoneForm.classList.toggle('active', step === 'phone');
            verifyForm.classList.toggle('active', step === 'verify');
            el.success.hidden = step !== 'success';

            if (step === 'phone') {
                el.title.textContent = TEXT.phoneTitle;
                el.subtitle.textContent = TEXT.phoneSubtitle;
            } else if (step === 'verify') {
                el.title.textContent = state.isNewUser ? TEXT.registerTitle : TEXT.loginTitle;
                el.subtitle.textContent = state.isNewUser ? TEXT.registerSubtitle : TEXT.loginSubtitle;
            }
        }

        function configureVerifyStep() {
            el.nameWrap.hidden = !state.isNewUser;
            el.name.required = state.isNewUser;
            setButtonText(el.verifyBtn, state.isNewUser ? TEXT.registerBtn : TEXT.loginBtn);
            el.phoneDisplay.textContent = formatPhone(state.sentPhone);
            el.hiddenPhone.value = state.sentPhone;
        }

        function focusCurrent() {
            var step = box.getAttribute('data-step');
            if (step === 'phone') {
                el.phone.focus();
            } else if (step === 'verify') {
                if (state.isNewUser && !el.name.value.trim()) el.name.focus();
                else el.otp.focus();
            }
        }
        box.laFocusCurrent = focusCurrent;

        // ---------- تایمر ارسال مجدد ----------
        function renderTimer() {
            var left = Math.max(0, Math.ceil((state.timerEnd - Date.now()) / 1000));
            var mins = String(Math.floor(left / 60)).padStart(2, '0');
            var secs = String(left % 60).padStart(2, '0');
            el.timerValue.textContent = mins + ':' + secs;

            if (left <= 0) {
                clearInterval(state.timerId);
                state.timerId = null;
                el.timer.hidden = true;
                el.resend.hidden = false;
            }
        }

        function startTimer(seconds) {
            clearInterval(state.timerId);
            state.timerEnd = Date.now() + seconds * 1000;
            el.timer.hidden = false;
            el.resend.hidden = true;
            renderTimer();
            state.timerId = setInterval(renderTimer, 500);
        }

        // ---------- ارسال کد ----------
        function sendOtp(phone, isResend) {
            if (state.busy) return;
            state.busy = true;

            var btn = isResend ? el.resend : el.sendBtn;
            setLoading(btn, true);
            hideMessage();

            post({ action: 'la_send_otp', phone: phone })
                .then(function (res) {
                    if (res && res.success) {
                        state.sentPhone = phone;
                        // Never infer registration status before the OTP is verified.
                        state.isNewUser = false;
                        state.lastAutoOtp = '';
                        el.otp.value = '';
                        setFieldError(el.otp, el.otpErr, '');

                        configureVerifyStep();
                        setStep('verify');
                        startTimer(Number(res.data && res.data.resend_after) || RESEND_SECONDS);
                        if (isResend) showMessage('کد جدید ارسال شد.', 'success');
                        focusCurrent();
                    } else {
                        var message = getResponseMessage(res) || GENERIC_ERROR;
                        if (isResend) {
                            if (res.data && res.data.retry_after && Number(res.data.retry_after) <= 180) {
                                startTimer(Number(res.data.retry_after));
                            }
                            showMessage(message, 'error');
                        } else {
                            setFieldError(el.phone, el.phoneErr, message);
                            shake(phoneForm);
                            el.phone.focus();
                        }
                    }
                })
                .catch(function () {
                    showMessage(NETWORK_ERROR, 'error');
                })
                .then(function () {
                    state.busy = false;
                    setLoading(btn, false);
                });
        }

        // ---------- تایید کد ----------
        function verifyOtp() {
            if (state.busy) return;

            var otp = normalizeOtp(el.otp.value);
            var fullname = el.name.value.trim();
            var firstInvalid = null;

            if (state.isNewUser && !fullname) {
                setFieldError(el.name, el.nameErr, 'لطفاً نام و نام خانوادگی خود را وارد کنید.');
                firstInvalid = firstInvalid || el.name;
            }
            if (otp.length !== OTP_LENGTH) {
                setFieldError(el.otp, el.otpErr, otp.length ? 'کد تأیید باید ' + OTP_LENGTH + ' رقم باشد.' : 'کد تأیید پیامک‌شده را وارد کنید.');
                firstInvalid = firstInvalid || el.otp;
            }
            if (firstInvalid) {
                shake(verifyForm);
                firstInvalid.focus();
                return;
            }

            state.busy = true;
            setLoading(el.verifyBtn, true);
            hideMessage();

            post({
                action: 'la_verify_otp',
                phone: state.sentPhone,
                otp: otp,
                fullname: state.isNewUser ? fullname : '',
                remember_me: el.remember && el.remember.checked ? '1' : '0'
            })
                .then(function (res) {
                    if (res && res.success) {
                        if (res.data && res.data.needs_name) {
                            // Account discovery only happens after the correct OTP.
                            state.isNewUser = true;
                            configureVerifyStep();
                            setStep('verify');
                            showMessage(getResponseMessage(res), 'success');
                            state.busy = false;
                            setLoading(el.verifyBtn, false);
                            el.name.focus();
                            return;
                        }
                        clearInterval(state.timerId);
                        el.successText.textContent = getResponseMessage(res);
                        setStep('success');
                        setTimeout(function () {
                            if (la_ajax.redirect) window.location.assign(la_ajax.redirect);
                            else window.location.reload();
                        }, 850);
                        return;
                    }

                    var message = getResponseMessage(res) || GENERIC_ERROR;
                    state.busy = false;
                    setLoading(el.verifyBtn, false);

                    if (message.indexOf('نام') > -1 && state.isNewUser) {
                        setFieldError(el.name, el.nameErr, message);
                        el.name.focus();
                    } else if (message.indexOf('کد') > -1) {
                        setFieldError(el.otp, el.otpErr, message);
                        el.otp.focus();
                        el.otp.select();
                    } else {
                        showMessage(message, 'error');
                    }
                    shake(verifyForm);
                })
                .catch(function () {
                    state.busy = false;
                    setLoading(el.verifyBtn, false);
                    showMessage(NETWORK_ERROR, 'error');
                });
        }

        // ---------- رویدادها ----------
        el.phone.addEventListener('input', function () {
            var normalized = normalizePhone(el.phone.value);
            if (normalized !== el.phone.value) el.phone.value = normalized;
            el.phoneWrap.classList.toggle('is-valid', isValidPhone(normalized));
            setFieldError(el.phone, el.phoneErr, '');
        });

        phoneForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var phone = normalizePhone(el.phone.value);
            el.phone.value = phone;

            if (!phone) {
                setFieldError(el.phone, el.phoneErr, 'لطفاً شماره موبایل خود را وارد کنید.');
            } else if (!isValidPhone(phone)) {
                setFieldError(el.phone, el.phoneErr, 'شماره موبایل باید ۱۱ رقم باشد و با ۰۹ شروع شود.');
            } else if (phone === state.sentPhone && Date.now() < state.timerEnd) {
                // کد همین شماره هنوز معتبر است؛ بدون درخواست مجدد به مرحله بعد برگرد
                setStep('verify');
                focusCurrent();
                return;
            } else {
                sendOtp(phone, false);
                return;
            }
            shake(phoneForm);
            el.phone.focus();
        });

        verifyForm.addEventListener('submit', function (e) {
            e.preventDefault();
            verifyOtp();
        });

        el.otp.addEventListener('input', function () {
            var otp = normalizeOtp(el.otp.value);
            if (otp !== el.otp.value) el.otp.value = otp;
            setFieldError(el.otp, el.otpErr, '');

            // ارسال خودکار وقتی کد کامل شد
            if (otp.length === OTP_LENGTH && otp !== state.lastAutoOtp && !state.busy) {
                if (state.isNewUser && !el.name.value.trim()) {
                    setFieldError(el.name, el.nameErr, 'برای ساخت حساب، نام و نام خانوادگی خود را وارد کنید.');
                    el.name.focus();
                    return;
                }
                state.lastAutoOtp = otp;
                verifyOtp();
            }
        });

        el.name.addEventListener('input', function () {
            setFieldError(el.name, el.nameErr, '');
        });

        // Enter در فیلد نام: اگر کد کامل نیست، برو سراغ فیلد کد
        el.name.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && normalizeOtp(el.otp.value).length !== OTP_LENGTH) {
                e.preventDefault();
                el.otp.focus();
            }
        });

        el.editPhone.addEventListener('click', function () {
            hideMessage();
            el.otp.value = '';
            state.lastAutoOtp = '';
            setFieldError(el.otp, el.otpErr, '');
            setFieldError(el.name, el.nameErr, '');
            setStep('phone');
            el.phone.focus();
            el.phone.select();
        });

        el.resend.addEventListener('click', function () {
            if (state.sentPhone) sendOtp(state.sentPhone, true);
        });
    }

    // ==========================================
    // پاپ‌آپ (Modal)
    // ==========================================
    function initModal() {
        var overlay = document.getElementById('la-modal-overlay');
        if (!overlay) return;

        var box = overlay.querySelector('.la-box');
        var lastFocused = null;
        var pressStartedOnOverlay = false;

        function isOpen() {
            return overlay.classList.contains('active');
        }

        function open() {
            if (isOpen()) return;
            lastFocused = document.activeElement;
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('la-no-scroll');
            setTimeout(function () {
                if (box && box.laFocusCurrent) box.laFocusCurrent();
            }, 120);
        }

        function close() {
            if (!isOpen()) return;
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('la-no-scroll');
            if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
        }

        // باز کردن: هر دکمه با کلاس la-open-modal یا لینک #light-auth (حتی در منو)
        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.la-open-modal, a[href="#light-auth"], a[href$="/#light-auth"]');
            if (!trigger) return;
            e.preventDefault();
            open();
        });

        overlay.querySelectorAll('.la-close-modal').forEach(function (btn) {
            btn.addEventListener('click', close);
        });

        // بستن با کلیک روی فضای خالی (اگر کلیک از همان‌جا شروع شده باشد)
        overlay.addEventListener('mousedown', function (e) {
            pressStartedOnOverlay = e.target === overlay;
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay && pressStartedOnOverlay) close();
            pressStartedOnOverlay = false;
        });

        document.addEventListener('keydown', function (e) {
            if (!isOpen()) return;

            if (e.key === 'Escape') {
                close();
                return;
            }

            // نگه داشتن فوکوس داخل پاپ‌آپ
            if (e.key === 'Tab') {
                var focusables = Array.prototype.filter.call(
                    overlay.querySelectorAll('button, a[href], input:not([type="hidden"]), [tabindex]:not([tabindex="-1"])'),
                    function (node) { return !node.disabled && isVisible(node); }
                );
                if (!focusables.length) return;
                var first = focusables[0];
                var last = focusables[focusables.length - 1];

                if (e.shiftKey && (document.activeElement === first || !overlay.contains(document.activeElement))) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && (document.activeElement === last || !overlay.contains(document.activeElement))) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // باز شدن خودکار وقتی آدرس صفحه با #light-auth باز شود
        if (window.location.hash === '#light-auth') open();
    }

    // ==========================================
    // منوی کاربر لاگین‌شده
    // ==========================================
    function initUserMenus() {
        var menus = document.querySelectorAll('.la-user-menu');
        if (!menus.length) return;

        function setOpen(menu, shouldOpen) {
            var btn = menu.querySelector('.la-logged-btn');
            menu.classList.toggle('is-open', shouldOpen);
            btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            if (shouldOpen) {
                // اگر منو از لبه صفحه بیرون می‌زند، جهتش را برعکس کن
                menu.classList.remove('la-flip');
                var rect = menu.querySelector('.la-user-dropdown').getBoundingClientRect();
                if (rect.left < 8) menu.classList.add('la-flip');
            }
        }

        function closeAll(except) {
            menus.forEach(function (menu) {
                if (menu !== except) setOpen(menu, false);
            });
        }

        menus.forEach(function (menu) {
            var btn = menu.querySelector('.la-logged-btn');
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var willOpen = !menu.classList.contains('is-open');
                closeAll(menu);
                setOpen(menu, willOpen);
                if (willOpen) {
                    var firstItem = menu.querySelector('.la-menu-item');
                    if (firstItem && e.detail === 0) firstItem.focus(); // فقط هنگام باز کردن با کیبورد
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.la-user-menu')) closeAll();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            menus.forEach(function (menu) {
                if (menu.classList.contains('is-open')) {
                    setOpen(menu, false);
                    menu.querySelector('.la-logged-btn').focus();
                }
            });
        });
    }

    // ==========================================
    // تم روشن/تیره (پیش‌فرض: تیره)
    // ==========================================
    function initThemeToggle() {
        var root = document.documentElement;

        function currentTheme() {
            return root.getAttribute('data-la-theme') === 'light' ? 'light' : 'dark';
        }

        function syncToggles() {
            var label = currentTheme() === 'light' ? 'تغییر به تم تیره' : 'تغییر به تم روشن';
            document.querySelectorAll('.la-theme-toggle').forEach(function (btn) {
                btn.setAttribute('aria-label', label);
                btn.setAttribute('title', label);
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.la-theme-toggle');
            if (!btn) return;
            e.preventDefault();
            var next = currentTheme() === 'light' ? 'dark' : 'light';
            root.setAttribute('data-la-theme', next);
            try { localStorage.setItem('la_theme', next); } catch (err) {}
            syncToggles();
        });

        syncToggles();
    }

    // ==========================================
    // راه‌اندازی
    // ==========================================
    function init() {
        initThemeToggle();
        document.querySelectorAll('.la-box').forEach(initAuthBox);
        initModal();
        initUserMenus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
