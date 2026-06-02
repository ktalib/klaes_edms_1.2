<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#e8dcc4">
  <title>SPAS Mobile - Special Assignment Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; }
    body { 
      font-family: 'Inter', sans-serif; 
      background: linear-gradient(135deg, #e8dcc4 0%, #d9cbb5 50%, #cabad5 100%);
      min-height: 100vh; 
      display: flex; 
      flex-direction: column;
      position: relative; 
      overflow-x: hidden; 
    }
    .bg-blob { 
      position: fixed; 
      border-radius: 50%; 
      filter: blur(80px); 
      opacity: 0.25; 
      z-index: 0; 
      animation: float 25s infinite ease-in-out; 
    }
    .blob-1 { width: 350px; height: 350px; background: #a0732d; top: -150px; left: -100px; }
    .blob-2 { width: 450px; height: 450px; background: #c4b957; bottom: -200px; right: -150px; animation-delay: 5s; }
    .blob-3 { width: 280px; height: 280px; background: #8b6914; top: 40%; left: 50%; transform: translate(-50%,-50%); animation-delay: 10s; opacity: 0.15; }
    @keyframes float { 0%,100% { transform: translate(0,0) scale(1); } 33% { transform: translate(40px,-40px) scale(1.05); } 66% { transform: translate(-30px,30px) scale(0.95); } }
    
    .header { 
      position: relative; 
      z-index: 20; 
      padding: 16px 20px; 
      display: flex; 
      justify-content: space-between; 
      align-items: center;
      background: rgba(255,255,255,0.5);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(160,115,45,0.1);
    }
    .header-brand { 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      flex: 1;
    }
    .brand-icon-img { 
      width: 36px; 
      height: 36px; 
      border-radius: 8px; 
      object-fit: contain;
    }
    .brand-text { 
      font-size: 14px; 
      font-weight: 700; 
      color: #3d3d3d;
      line-height: 1.2;
    }
    .brand-text .main { font-size: 14px; }
    .brand-text .sub { font-size: 11px; color: #8b7355; font-weight: 500; }
    .header-switch { 
      background: rgba(255,255,255,0.6);
      border: 1px solid rgba(160,115,45,0.15);
      border-radius: 20px;
      padding: 6px 14px;
      font-size: 11px;
      font-weight: 600;
      color: #6b7280;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .header-switch:hover { background: rgba(255,255,255,0.8); border-color: rgba(160,115,45,0.25); }

    .login-wrapper { 
      position: relative; 
      z-index: 10; 
      flex: 1;
      display: flex; 
      align-items: center; 
      justify-content: center; 
      padding: 24px 20px;
      min-height: calc(100vh - 70px);
    }
    .login-card { 
      background: rgba(255,255,255,0.98); 
      backdrop-filter: blur(20px); 
      border-radius: 32px; 
      padding: 48px 32px; 
      box-shadow: 0 20px 60px -15px rgba(139,105,20,0.25), 
                  0 0 0 1px rgba(255,255,255,0.5),
                  inset 0 1px 0 rgba(255,255,255,0.8);
      width: 100%;
      max-width: 420px;
      animation: slideUp 0.5s ease-out;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .welcome-section { text-align: center; margin-bottom: 36px; }
    .welcome-section h1 { 
      font-size: 32px; 
      font-weight: 800; 
      color: #a0732d; 
      margin-bottom: 8px;
      letter-spacing: -0.5px;
    }
    .welcome-section p { 
      font-size: 14px; 
      color: #6b7280; 
      line-height: 1.6;
      font-weight: 500;
    }
    
    .form-group { margin-bottom: 20px; }
    .input-label { 
      display: block; 
      font-size: 12px; 
      font-weight: 700; 
      color: #6b4423;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .input-wrapper { 
      position: relative; 
      display: flex;
      align-items: center;
    }
    .input-wrapper .icon-left { 
      position: absolute; 
      left: 16px; 
      color: #a0732d; 
      font-size: 16px;
      z-index: 2;
    }
    .input-wrapper input { 
      width: 100%; 
      padding: 14px 16px 14px 48px; 
      font-size: 15px; 
      font-family: 'Inter', sans-serif;
      border: 1.5px solid #e5d5c4; 
      border-radius: 12px; 
      background: #f9f6f1; 
      outline: none; 
      transition: all 0.3s ease;
      color: #1f2937;
      font-weight: 500;
    }
    .input-wrapper input::placeholder { color: #b8a89e; font-weight: 400; }
    .input-wrapper input:focus { 
      border-color: #a0732d; 
      background: white;
      box-shadow: 0 0 0 4px rgba(160,115,45,0.08);
    }
    .password-toggle { 
      position: absolute; 
      right: 16px; 
      cursor: pointer; 
      color: #a0732d;
      transition: all 0.2s;
      z-index: 2;
    }
    .password-toggle:hover { transform: scale(1.1); }

    .options-row { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      margin-bottom: 28px; 
      font-size: 12px;
    }
    .remember-me { 
      display: flex; 
      align-items: center; 
      gap: 8px; 
      cursor: pointer; 
      color: #6b7280;
      font-weight: 500;
    }
    .remember-me input { 
      width: 18px; 
      height: 18px; 
      accent-color: #a0732d; 
      cursor: pointer;
      border-radius: 4px;
    }
    .forgot-link { 
      color: #a0732d; 
      text-decoration: none; 
      font-weight: 600;
      transition: all 0.2s;
    }
    .forgot-link:hover { opacity: 0.8; }

    .login-btn { 
      width: 100%; 
      padding: 16px; 
      background: linear-gradient(135deg, #a0732d 0%, #8b6914 100%);
      border: none; 
      border-radius: 12px; 
      font-size: 15px; 
      font-weight: 700; 
      color: white; 
      cursor: pointer; 
      transition: all 0.3s ease;
      display: flex; 
      align-items: center; 
      justify-content: center; 
      gap: 10px; 
      box-shadow: 0 8px 20px rgba(160,115,45,0.3);
      margin-bottom: 12px;
      letter-spacing: 0.3px;
    }
    .login-btn:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 12px 30px rgba(160,115,45,0.4);
    }
    .login-btn:active { transform: translateY(0); }
    .login-btn:disabled { 
      opacity: 0.7; 
      cursor: not-allowed;
      transform: none;
    }

    .divider { 
      display: flex; 
      align-items: center; 
      margin: 24px 0; 
      color: #d1ccc7;
    }
    .divider::before, .divider::after { 
      content: ''; 
      flex: 1; 
      height: 1px; 
      background: currentColor;
    }
    .divider-text { 
      padding: 0 12px; 
      font-size: 12px; 
      color: #b8a89e;
      font-weight: 500;
    }

    .signup-section { 
      text-align: center; 
      font-size: 13px; 
      color: #6b7280;
    }
    .signup-section a { 
      color: #a0732d; 
      text-decoration: none; 
      font-weight: 600;
      transition: all 0.2s;
    }
    .signup-section a:hover { opacity: 0.8; }

    .footer { 
      text-align: center; 
      margin-top: 24px; 
      font-size: 10px; 
      color: #b8a89e;
      padding-top: 12px;
      border-top: 1px solid #e5d5c4;
    }

    .error-box { 
      background: #fef2f2; 
      border: 1.5px solid #fecaca; 
      border-radius: 12px; 
      padding: 12px 16px; 
      margin-bottom: 20px; 
      font-size: 13px; 
      color: #dc2626; 
      display: flex; 
      align-items: flex-start; 
      gap: 10px;
      animation: slideDown 0.3s ease-out;
    }
    .error-box i { margin-top: 2px; flex-shrink: 0; }
    .success-box { 
      background: #f0fdf4; 
      border: 1.5px solid #bbf7d0; 
      border-radius: 12px; 
      padding: 12px 16px; 
      margin-bottom: 20px; 
      font-size: 13px; 
      color: #16a34a; 
      display: flex; 
      align-items: flex-start; 
      gap: 10px;
      animation: slideDown 0.3s ease-out;
    }
    .success-box i { margin-top: 2px; flex-shrink: 0; }
    
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes spin { 
      from { transform: rotate(0deg); } 
      to { transform: rotate(360deg); } 
    }

    @media (max-width: 480px) { 
      .login-card { padding: 36px 24px; }
      .welcome-section h1 { font-size: 28px; }
      .header { padding: 12px 16px; }
    }
  </style>
</head>
<body>
  <div class="bg-blob blob-1"></div>
  <div class="bg-blob blob-2"></div>
  <div class="bg-blob blob-3"></div>

  <!-- Header -->
  <div class="header">
    <div class="header-brand">
      <img src="http://app.klaes.ng/storage/upload/logo/1.jpeg" alt="SPAS Logo" class="brand-icon-img">
      <div class="brand-text">
        <div class="main">SPAS Mobile</div>
        <div class="sub">SPECIAL ASSIGNMENT</div>
      </div>
    </div>
  </div>

  <!-- Login Form -->
  <div class="login-wrapper">
    <div class="login-card">
      <div class="welcome-section">
        <h1>Welcome Back</h1>
        <p>Sign in to access field assignments and property records</p>
      </div>

      @if ($errors->any())
        <div class="error-box">
          <i class="fas fa-circle-exclamation"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      @if (session('success'))
        <div class="success-box">
          <i class="fas fa-circle-check"></i>
          <span>{{ session('success') }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('special-assignment.mobile.login.submit') }}" id="loginForm">
        @csrf
        <div class="form-group">
          <label class="input-label" for="identifier">Username or Email</label>
          <div class="input-wrapper">
            <i class="fas fa-user icon-left"></i>
            <input 
              type="text" 
              name="identifier" 
              id="identifier"
              placeholder="Enter your username"
              value="{{ old('identifier') }}"
              autocomplete="username" 
              required>
          </div>
        </div>
        
        <div class="form-group">
          <label class="input-label" for="password">Password</label>
          <div class="input-wrapper">
            <i class="fas fa-lock icon-left"></i>
            <input 
              type="password" 
              name="password" 
              id="password"
              placeholder="Enter your password"
              autocomplete="current-password" 
              required>
            <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
          </div>
        </div>

        <div class="options-row">
          <label class="remember-me">
            <input type="checkbox" name="remember" id="rememberMe">
            <span>Remember me</span>
          </label>
          <a href="{{ route('special-assignment.mobile.forgot-password') }}" class="forgot-link">Forgot password?</a>
        </div>

        <button type="submit" class="login-btn" id="loginBtn">
          <i class="fas fa-arrow-right-to-bracket"></i>
          <span>Sign In</span>
        </button>
      </form>

      <div class="signup-section">
        Don't have an account? <a href="#">Contact administrator</a>
      </div>

      <div class="footer">
        © 2026 KLAES. All rights reserved.
      </div>
    </div>
  </div>

  <script>
    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
      });
    }

    // Form submission handling
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    
    if (loginForm) {
      loginForm.addEventListener('submit', function(e) {
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Signing in...</span>';
      });
    }
  </script>
</body>
</html>
