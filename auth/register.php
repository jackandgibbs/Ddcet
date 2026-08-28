<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/supabase_auth.php';

if (currentUser()) {
    header('Location: ' . BASE_PATH . 'dashboard.php');
    exit;
}

$googleAuthUrl = getSupabaseAuthUrl();
$colleges = supabaseRest('colleges?select=id,name,city&order=name') ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f8f9fa; --card: #ffffff; --accent: #4361ee; --green: #10b981; --text: #1a1a2e; --text-sec: #6c757d; --text-muted: #adb5bd; --border: #e9ecef; --radius: 12px; --font: 'DM Sans', sans-serif; --mono: 'DM Mono', monospace; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: var(--font); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
        .login-wrapper { display: grid; grid-template-columns: 1fr 1fr; max-width: 900px; width: 100%; background: var(--card); border-radius: 16px; overflow: hidden; box-shadow: 0 16px 48px rgba(0,0,0,0.06); border: 1px solid var(--border); }
        .login-left { background: linear-gradient(135deg, #1a1a2e, #2d1b69); padding: 48px; display: flex; flex-direction: column; justify-content: center; color: #fff; }
        .login-left h2 { font-size: 28px; font-weight: 800; margin-bottom: 12px; line-height: 1.2; }
        .login-left p { font-size: 14px; opacity: 0.8; line-height: 1.7; margin-bottom: 32px; }
        
        .login-right { padding: 48px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .login-right h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .login-right h1 span { color: var(--accent); }
        .login-right .subtitle { color: var(--text-sec); font-size: 14px; margin-bottom: 24px; text-align: center; }
        
        /* Form Styles */
        .auth-form { width: 100%; max-width: 380px; display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .input-group { display: flex; flex-direction: column; text-align: left; gap: 6px; }
        .input-group label { font-size: 13px; font-weight: 600; color: var(--text); }
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-family: var(--font); font-size: 14px; outline: none; transition: border-color 0.2s; background: var(--bg); }
        .form-control:focus { border-color: var(--accent); }
        .btn-primary { background: var(--accent); color: #fff; border: none; padding: 14px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background 0.2s; font-family: var(--font); width: 100%; margin-top: 8px; }
        .btn-primary:hover { background: #324fc7; }
        .btn-primary:disabled { background: #a5b4fc; cursor: not-allowed; }
        
        .divider { display: flex; align-items: center; width: 100%; max-width: 380px; margin-bottom: 24px; color: var(--text-muted); font-size: 13px; }
        .divider::before, .divider::after { content: ""; flex: 1; border-bottom: 1px solid var(--border); }
        .divider::before { margin-right: 16px; }
        .divider::after { margin-left: 16px; }

        .google-btn { display: inline-flex; align-items: center; justify-content: center; gap: 12px; width: 100%; max-width: 380px; background: var(--card); color: var(--text); padding: 14px; border-radius: 8px; font-weight: 600; font-size: 15px; transition: all 0.2s; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); text-decoration: none; }
        .google-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.08); border-color: var(--accent); }
        .google-btn svg { width: 20px; height: 20px; }
        
        .footer-text { margin-top: 24px; font-size: 13px; color: var(--text-sec); text-align: center; }
        .footer-text a { color: var(--accent); font-weight: 600; text-decoration: none; }
        
        .back-link { position: absolute; top: 24px; left: 24px; font-size: 13px; color: var(--text-sec); text-decoration: none; font-weight: 500;}
        .back-link:hover { color: var(--accent); }

        #otpSection { display: none; }

        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-left { display: none; }
            .login-right { padding: 40px 24px; }
        }
    </style>
</head>
<body>
    <a href="<?= BASE_PATH ?>" class="back-link">← Back to home</a>
    <style>
    .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
    .toast { background: #10b981; color: white; border-radius: 10px; padding: 14px 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 10px; font-size: 14px; max-width: 360px; animation: slideIn 0.3s ease; font-weight: 500; }
    .toast-error { background: #ef4444; }
    .toast-close { cursor: pointer; margin-left: 10px; font-size: 18px; opacity: 0.8; }
    .toast-close:hover { opacity: 1; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
    <script>
    function showToast(msg, type = 'success', duration = 5000) {
        let container = document.querySelector('.toast-container');
        if (!container) { container = document.createElement('div'); container.className = 'toast-container'; document.body.appendChild(container); }
        const toast = document.createElement('div');
        toast.className = 'toast ' + (type === 'error' ? 'toast-error' : '');
        toast.innerHTML = (type === 'error' ? '⚠ ' : '✓ ') + msg + '<span class="toast-close" onclick="this.parentElement.remove()">✕</span>';
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, duration);
    }
    </script>
    
    <div class="login-wrapper">
        <div class="login-left">
            <h2>Your DDCET Prep Journey Starts Here</h2>
            <p>Join 500+ students already preparing smarter with AI-powered analytics, real-pattern mock tests, and a competitive community.</p>
        </div>
        <div class="login-right">
            <h1 style="margin-bottom: 8px;">Create Account</h1>
            <p class="subtitle">Join DDCET Prep to access tests & analytics</p>

            <form class="auth-form" id="registerForm" onsubmit="return false;">
                
                <div id="detailsSection">
                    <div class="form-row">
                        <div class="input-group">
                            <label>First Name *</label>
                            <input type="text" id="name" class="form-control" placeholder="John" required>
                        </div>
                        <div class="input-group">
                            <label>Surname *</label>
                            <input type="text" id="surname" class="form-control" placeholder="Doe" required>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>College *</label>
                        <select id="college" class="form-control" required>
                            <option value="">Select your college...</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Semester</label>
                            <select id="semester" class="form-control">
                                <option value="">Select...</option>
                                <option value="1">1st Sem</option>
                                <option value="2">2nd Sem</option>
                                <option value="3">3rd Sem</option>
                                <option value="4">4th Sem</option>
                                <option value="5">5th Sem</option>
                                <option value="6">6th Sem</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Branch</label>
                            <select id="branch" class="form-control">
                                <option value="">Select...</option>
                                <option value="CE/IT">CE / IT</option>
                                <option value="Mechanical">Mechanical</option>
                                <option value="Civil">Civil</option>
                                <option value="Electrical">Electrical</option>
                                <option value="EC">EC</option>
                                <option value="Chemical">Chemical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="input-group" style="margin-top: 4px;">
                        <label>Mobile Number or Email *</label>
                        <input type="text" id="identifier" name="identifier" class="form-control" placeholder="e.g. 9876543210 or email@domain.com" required>
                    </div>
                    
                    <div id="msg91-captcha"></div>
                    <button type="button" class="btn-primary" id="btnSendOtp" onclick="requestMsg91Otp()" style="width: 100%;">Continue & Verify</button>
                </div>
                
                <div id="otpSection">
                    <div class="input-group" style="margin-bottom: 16px;">
                        <label>Enter 6-digit OTP</label>
                        <input type="text" id="otp" class="form-control" placeholder="123456" maxlength="6" style="text-align: center; letter-spacing: 4px; font-family: var(--mono); font-size: 18px;">
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; text-align: right;">Code sent to <span id="sentTarget" style="font-weight: 600; color: var(--text);"></span> <a href="#" onclick="resetForm(); return false;" style="color: var(--accent);">Edit</a></div>
                    </div>
                    <button type="button" class="btn-primary" id="btnVerifyOtp" onclick="verifyOtp()">Register & Login</button>
                </div>
                
            </form>

            <div class="divider">OR</div>

            <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="google-btn">
                <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                Continue with Google
            </a>
            
            <p class="footer-text">Already have an account? <a href="<?= BASE_PATH ?>auth/login.php">Log in here</a></p>
        </div>
    </div>
    
    <script>
    var msg91Configuration = {
        widgetId: "366842675751383432313038",
        tokenAuth: "<?= $_ENV['MSG91_AUTH_TOKEN'] ?? '{token}' ?>",
        exposeMethods: true,
        captchaRenderId: "",
        success: async (data) => {
            // MSG91 verified successfully! Tell our backend.
            const identifier = document.getElementById('identifier').value.trim();
            const payload = {
                action: 'register',
                identifier: identifier,
                msg91_verified: true,
                msg91_data: data,
                name: document.getElementById('name').value.trim(),
                surname: document.getElementById('surname').value.trim(),
                college_id: document.getElementById('college').value,
                semester: document.getElementById('semester').value,
                branch: document.getElementById('branch').value
            };
            
            const btn = document.getElementById('btnVerifyOtp');
            btn.disabled = true;
            btn.innerText = 'Registering...';
            
            try {
                const res = await fetch('<?= BASE_PATH ?>api/auth_verify_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const responseData = await res.json();
                
                if (responseData.error) {
                    showToast(responseData.error, 'error');
                    btn.disabled = false;
                    btn.innerText = 'Register & Login';
                } else if (responseData.success && responseData.redirect) {
                    window.location.href = responseData.redirect;
                }
            } catch (e) {
                showToast('Network error while completing registration. Please try again.', 'error');
                btn.disabled = false;
                btn.innerText = 'Register & Login';
            }
        },
        failure: (error) => {
            showToast(error.message || 'Verification failed. Please check the OTP.', 'error');
            const btn = document.getElementById('btnVerifyOtp');
            btn.disabled = false;
            btn.innerText = 'Register & Login';
        }
    };
    </script>
    <script type="text/javascript" onload="initSendOTP(msg91Configuration)" src="https://verify.msg91.com/otp-provider.js"></script>

    <script>
    function requestMsg91Otp() {
        const name = document.getElementById('name').value.trim();
        const surname = document.getElementById('surname').value.trim();
        const college = document.getElementById('college').value;
        const identifier = document.getElementById('identifier').value.trim();
        
        if (!name || !surname || !college || !identifier) {
            showToast('Please fill out all required fields marked with *.', 'error');
            return;
        }
        
        if (typeof window.sendOtp !== 'function') {
            showToast('MSG91 Widget failed to load. Please check your internet connection or disable adblockers.', 'error');
            return;
        }
        
        const btn = document.getElementById('btnSendOtp');
        btn.disabled = true;
        btn.innerText = 'Sending...';
        
        // Strip '+' and country code for MSG91 (requires country code without + according to docs)
        // If it's a 10 digit Indian number, append 91
        let msg91Identifier = identifier;
        if (/^\d{10}$/.test(msg91Identifier)) {
            msg91Identifier = '91' + msg91Identifier;
        } else if (msg91Identifier.startsWith('+')) {
            msg91Identifier = msg91Identifier.substring(1);
        }
        
        try {
            window.sendOtp(
                msg91Identifier,
                (data) => { console.log('OTP sent response:', data); },
                (error) => { console.error('OTP send error:', error); }
            );
            
            // Optimistically switch to the OTP entry UI
            setTimeout(() => {
                showToast('OTP request fired.', 'success');
                document.getElementById('detailsSection').style.display = 'none';
                document.getElementById('otpSection').style.display = 'block';
                document.getElementById('sentTarget').innerText = identifier;
                document.getElementById('otp').focus();
            }, 800);
            
        } catch (e) {
            console.error("Exception calling window.sendOtp:", e);
            showToast('An unexpected error occurred. Please check the console.', 'error');
            btn.disabled = false;
            btn.innerText = 'Continue & Verify';
        }
    }
    
    function verifyOtp() {
        const otp = document.getElementById('otp').value.trim();
        
        if (!otp || otp.length < 4) {
            showToast('Please enter the OTP.', 'error');
            return;
        }
        
        const btn = document.getElementById('btnVerifyOtp');
        btn.disabled = true;
        btn.innerText = 'Verifying...';
        
        window.verifyOtp(otp);
        // The success/failure callbacks defined in msg91Configuration will handle the result.
    }
    
    function resetForm() {
        document.getElementById('otpSection').style.display = 'none';
        document.getElementById('detailsSection').style.display = 'block';
        document.getElementById('otp').value = '';
        
        const btn = document.getElementById('btnSendOtp');
        btn.disabled = false;
        btn.innerText = 'Continue & Verify';
    }
    </script>
</body>
</html>
