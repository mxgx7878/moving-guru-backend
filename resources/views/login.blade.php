<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Material Connect</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    /* CSS Variables */
    :root {
      --primary-color: #0d6efd;
      --bg-color: #f8f9fa;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .auth-container {
      width: 100%;
      max-width: 450px;
    }

    .auth-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
      overflow: hidden;
      animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .auth-header {
      text-align: center;
      padding: 40px 40px 20px;
    }

    .auth-logo {
      width: 200px;
      height: auto;
      margin-bottom: 20px;
    }

    .auth-title {
      font-size: 1.75rem;
      font-weight: 700;
      color: #212529;
      margin-bottom: 8px;
    }

    .auth-subtitle {
      color: #6c757d;
      font-size: 0.95rem;
    }

    .auth-body {
      padding: 20px 40px 40px;
    }

    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }

    .form-control {
      padding: 12px 16px;
      border: 2px solid #e9ecef;
      border-radius: 10px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
    }

    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }

    .password-wrapper {
      position: relative;
    }

    .password-toggle {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #6c757d;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: color 0.2s;
    }

    .password-toggle:hover {
      color: var(--primary-color);
    }

    .form-check-input {
      width: 18px;
      height: 18px;
      margin-top: 0;
      cursor: pointer;
    }

    .form-check-label {
      margin-left: 8px;
      cursor: pointer;
      font-size: 0.9rem;
      color: #495057;
    }

    .btn-primary {
      width: 100%;
      padding: 14px;
      font-weight: 600;
      font-size: 1rem;
      border-radius: 10px;
      border: none;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      transition: all 0.3s ease;
      margin-top: 10px;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .forgot-password {
      text-align: right;
      margin-top: -8px;
      margin-bottom: 20px;
    }

    .forgot-password a {
      color: var(--primary-color);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: color 0.2s;
    }

    .forgot-password a:hover {
      color: #0b5ed7;
      text-decoration: underline;
    }

    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 25px 0;
      color: #6c757d;
      font-size: 0.9rem;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #dee2e6;
    }

    .divider span {
      padding: 0 15px;
    }

    .register-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
      color: #6c757d;
      font-size: 0.95rem;
    }

    .register-link a {
      color: var(--primary-color);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s;
    }

    .register-link a:hover {
      color: #0b5ed7;
      text-decoration: underline;
    }

    .alert {
      border-radius: 10px;
      border: none;
      font-size: 0.9rem;
      padding: 12px 16px;
    }

    /* Responsive */
    @media (max-width: 576px) {
      .auth-header {
        padding: 30px 25px 15px;
      }

      .auth-body {
        padding: 15px 25px 30px;
      }

      .auth-logo {
        width: 160px;
      }

      .auth-title {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>

  <div class="auth-container">
    <div class="auth-card">
      <!-- Header -->
      <div class="auth-header">
        <img src="https://materialconnect.com.au/wp-content/uploads/2025/01/logo-black.png" alt="Material Connect" class="auth-logo">
        <h2 class="auth-title">Welcome Back</h2>
        <p class="auth-subtitle">Sign in to continue to Material Connect</p>
      </div>

      <!-- Body -->
      <div class="auth-body">
        <!-- Error Messages -->
        @if($errors->any())
        <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <div>
            @foreach($errors->all() as $error)
              {{ $error }}
            @endforeach
          </div>
        </div>
        @endif

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center mb-3" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i>
          <div>{{ session('success') }}</div>
        </div>
        @endif

        <!-- Login Form -->
        <form action="{{route('login.submit')}}" method="POST">
          @csrf
          
          <!-- Email -->
          <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input 
              type="email" 
              class="form-control @error('email') is-invalid @enderror" 
              id="email" 
              name="email" 
              placeholder="Enter your email"
              value="{{ old('email') }}"
              required 
              autofocus>
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="password-wrapper">
              <input 
                type="password" 
                class="form-control @error('password') is-invalid @enderror" 
                id="password" 
                name="password" 
                placeholder="Enter your password"
                required>
              <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                <i class="bi bi-eye" style="font-size: 1.2rem;"></i>
              </button>
            </div>
            @error('password')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
              <label class="form-check-label" for="rememberMe">
                Remember me
              </label>
            </div>
          </div>

          <div class="forgot-password">
            <a href="">Forgot Password?</a>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-primary">
            Sign In
          </button>
        </form>

        <!-- Divider -->
        <div class="divider">
          <span>New to Material Connect?</span>
        </div>

        <!-- Register Link -->
        <div class="register-link">
          Don't have an account? <a href="{{route('register')}}">Create Account</a>
        </div>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
          integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
          crossorigin="anonymous"></script>

  <!-- Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
          crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
          crossorigin="anonymous"></script>

  <script>
    // Toggle Password Visibility
    function togglePassword(inputId, button) {
      const input = document.getElementById(inputId);
      const icon = button.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
      }
    }
  </script>
</body>
</html>