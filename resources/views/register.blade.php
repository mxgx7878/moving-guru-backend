<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - Material Connect</title>
  
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
      padding: 40px 20px;
    }

    .auth-container {
      width: 100%;
      max-width: 900px;
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

    .form-label .required {
      color: #dc3545;
      margin-left: 2px;
    }

    .form-control, .form-select {
      padding: 12px 16px;
      border: 2px solid #e9ecef;
      border-radius: 10px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
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
      z-index: 10;
    }

    .password-toggle:hover {
      color: var(--primary-color);
    }

    /* Password Strength Meter */
    .password-strength {
      margin-top: 8px;
    }

    .strength-meter {
      height: 4px;
      background: #e9ecef;
      border-radius: 2px;
      overflow: hidden;
      margin-bottom: 8px;
    }

    .strength-meter-fill {
      height: 100%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .strength-weak { width: 33%; background: #dc3545; }
    .strength-medium { width: 66%; background: #ffc107; }
    .strength-strong { width: 100%; background: #28a745; }

    .password-requirements {
      font-size: 0.8rem;
      color: #6c757d;
    }

    .password-requirements .requirement {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 4px;
    }

    .requirement.met {
      color: #28a745;
    }

    .requirement i {
      font-size: 0.9rem;
    }

    /* Image Upload */
    .image-upload-wrapper {
      text-align: center;
      padding: 30px;
      border: 2px dashed #dee2e6;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #f8f9fa;
    }

    .image-upload-wrapper:hover {
      border-color: var(--primary-color);
      background: #f0f7ff;
    }

    .image-upload-wrapper.has-image {
      border-color: #28a745;
      background: #f0fff4;
    }

    .image-preview {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 15px;
      display: none;
      border: 3px solid #28a745;
    }

    .image-preview.show {
      display: block;
    }

    .upload-icon {
      font-size: 3rem;
      color: #6c757d;
      margin-bottom: 10px;
    }

    .upload-text {
      color: #495057;
      font-weight: 500;
      margin-bottom: 5px;
    }

    .upload-hint {
      color: #6c757d;
      font-size: 0.85rem;
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

    .login-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e9ecef;
      color: #6c757d;
      font-size: 0.95rem;
    }

    .login-link a {
      color: var(--primary-color);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s;
    }

    .login-link a:hover {
      color: #0b5ed7;
      text-decoration: underline;
    }

    .alert {
      border-radius: 10px;
      border: none;
      font-size: 0.9rem;
      padding: 12px 16px;
    }

    .section-title {
      font-size: 1rem;
      font-weight: 600;
      color: #495057;
      margin-bottom: 15px;
      padding-bottom: 8px;
      border-bottom: 2px solid #e9ecef;
    }

    /* Responsive */
    @media (max-width: 768px) {
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
        <h2 class="auth-title">Create Your Account</h2>
        <p class="auth-subtitle">Join Material Connect and start managing your construction materials</p>
      </div>

      <!-- Body -->
      <div class="auth-body">
        <!-- Error Messages -->
        @if($errors->any())
        <div class="alert alert-danger d-flex align-items-start mb-3" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
          <div>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2 ps-3">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        </div>
        @endif

        <!-- Registration Form -->
        <form action="{{route('register.submit')}}" method="POST" enctype="multipart/form-data" id="registerForm">
          @csrf
          
          <!-- Company Information -->
          <div class="section-title">
            <i class="bi bi-building me-2"></i>Company Information
          </div>
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="companyName" class="form-label">Company Name <span class="required">*</span></label>
              <input 
                type="text" 
                class="form-control @error('company_name') is-invalid @enderror" 
                id="companyName" 
                name="company_name" 
                placeholder="Enter company name"
                value="{{ old('company_name') }}"
                required>
              @error('company_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 mb-3">
              <label for="name" class="form-label">Contact Name <span class="required">*</span></label>
              <input 
                type="text" 
                class="form-control @error('name') is-invalid @enderror" 
                id="name" 
                name="name" 
                placeholder="Enter your full name"
                value="{{ old('name') }}"
                required>
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Contact Information -->
          <div class="section-title mt-4">
            <i class="bi bi-person-lines-fill me-2"></i>Contact Information
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="email" class="form-label">Email Address <span class="required">*</span></label>
              <input 
                type="email" 
                class="form-control @error('email') is-invalid @enderror" 
                id="email" 
                name="email" 
                placeholder="Enter your email"
                value="{{ old('email') }}"
                required>
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="col-md-6 mb-3">
              <label for="phone" class="form-label">Phone Number <span class="required">*</span></label>
              <input 
                type="tel" 
                class="form-control @error('phone') is-invalid @enderror" 
                id="phone" 
                name="phone" 
                placeholder="Enter phone number"
                value="{{ old('phone') }}"
                required>
              @error('phone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Address Information -->
          <div class="section-title mt-4">
            <i class="bi bi-geo-alt-fill me-2"></i>Address Information
          </div>

          <div class="mb-3">
            <label for="shippingAddress" class="form-label">Shipping Address <span class="required">*</span></label>
            <textarea 
              class="form-control @error('shipping_address') is-invalid @enderror" 
              id="shippingAddress" 
              name="shipping_address" 
              rows="3" 
              placeholder="Enter shipping address"
              required>{{ old('shipping_address') }}</textarea>
            @error('shipping_address')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="form-check mb-3">
            <input 
              class="form-check-input" 
              type="checkbox" 
              id="sameAsBilling" 
              name="same_as_billing"
              onchange="toggleDeliveryAddress()">
            <label class="form-check-label" for="sameAsBilling">
              Delivery address same as billing address
            </label>
          </div>

          <div class="mb-3" id="deliveryAddressGroup">
            <label for="deliveryAddress" class="form-label">Delivery Address <span class="required">*</span></label>
            <textarea 
              class="form-control @error('delivery_address') is-invalid @enderror" 
              id="deliveryAddress" 
              name="delivery_address" 
              rows="3" 
              placeholder="Enter delivery address"
              required>{{ old('delivery_address') }}</textarea>
            @error('delivery_address')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <!-- Profile Image -->
          <div class="section-title mt-4">
            <i class="bi bi-image me-2"></i>Profile Image (Optional)
          </div>

          <div class="mb-3">
            <input type="file" id="profileImage" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(event)">
            <div class="image-upload-wrapper" onclick="document.getElementById('profileImage').click()">
              <img id="imagePreview" class="image-preview" alt="Preview">
              <div id="uploadPlaceholder">
                <i class="bi bi-cloud-arrow-up upload-icon"></i>
                <div class="upload-text">Click to upload profile image</div>
                <div class="upload-hint">PNG, JPG up to 5MB</div>
              </div>
            </div>
          </div>

          <!-- Security -->
          <div class="section-title mt-4">
            <i class="bi bi-shield-lock me-2"></i>Security
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="password" class="form-label">Password <span class="required">*</span></label>
              <div class="password-wrapper">
                <input 
                  type="password" 
                  class="form-control @error('password') is-invalid @enderror" 
                  id="password" 
                  name="password" 
                  placeholder="Create a password"
                  onkeyup="checkPasswordStrength()"
                  required>
                <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                  <i class="bi bi-eye" style="font-size: 1.2rem;"></i>
                </button>
              </div>
              @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
              
              <!-- Password Strength Meter -->
              <div class="password-strength">
                <div class="strength-meter">
                  <div class="strength-meter-fill" id="strengthMeter"></div>
                </div>
                <div class="password-requirements">
                  <div class="requirement" id="req-length">
                    <i class="bi bi-x-circle"></i>
                    <span>At least 8 characters</span>
                  </div>
                  <div class="requirement" id="req-uppercase">
                    <i class="bi bi-x-circle"></i>
                    <span>One uppercase letter</span>
                  </div>
                  <div class="requirement" id="req-lowercase">
                    <i class="bi bi-x-circle"></i>
                    <span>One lowercase letter</span>
                  </div>
                  <div class="requirement" id="req-number">
                    <i class="bi bi-x-circle"></i>
                    <span>One number</span>
                  </div>
                  <div class="requirement" id="req-special">
                    <i class="bi bi-x-circle"></i>
                    <span>One special character (!@#$%^&*)</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-6 mb-3">
              <label for="confirmPassword" class="form-label">Confirm Password <span class="required">*</span></label>
              <div class="password-wrapper">
                <input 
                  type="password" 
                  class="form-control @error('password_confirmation') is-invalid @enderror" 
                  id="confirmPassword" 
                  name="password_confirmation" 
                  placeholder="Confirm your password"
                  required>
                <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                  <i class="bi bi-eye" style="font-size: 1.2rem;"></i>
                </button>
              </div>
              @error('password_confirmation')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Terms and Conditions -->
          <div class="mt-4">
            <div class="form-check">
              <input 
                class="form-check-input @error('terms') is-invalid @enderror" 
                type="checkbox" 
                id="agreeTerms" 
                name="terms"
                required>
              <label class="form-check-label" for="agreeTerms">
                I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a>
              </label>
              @error('terms')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>Create Account
          </button>
        </form>

        <!-- Login Link -->
        <div class="login-link">
          Already have an account? <a href="{{ route('login') }}">Sign In</a>
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

    // Toggle Delivery Address
    function toggleDeliveryAddress() {
      const checkbox = document.getElementById('sameAsBilling');
      const deliveryGroup = document.getElementById('deliveryAddressGroup');
      const deliveryAddress = document.getElementById('deliveryAddress');
      const billingAddress = document.getElementById('billingAddress');
      
      if (checkbox.checked) {
        deliveryAddress.value = billingAddress.value;
        deliveryAddress.readOnly = true;
        deliveryGroup.style.opacity = '0.6';
      } else {
        deliveryAddress.readOnly = false;
        deliveryGroup.style.opacity = '1';
      }
    }

    // Copy billing address when changed if checkbox is checked
    document.getElementById('billingAddress').addEventListener('input', function() {
      const checkbox = document.getElementById('sameAsBilling');
      if (checkbox.checked) {
        document.getElementById('deliveryAddress').value = this.value;
      }
    });

    // Preview Image
    function previewImage(event) {
      const file = event.target.files[0];
      const preview = document.getElementById('imagePreview');
      const placeholder = document.getElementById('uploadPlaceholder');
      const wrapper = document.querySelector('.image-upload-wrapper');
      
      if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.classList.add('show');
          placeholder.style.display = 'none';
          wrapper.classList.add('has-image');
        }
        
        reader.readAsDataURL(file);
      }
    }

    // Password Strength Checker
    function checkPasswordStrength() {
      const password = document.getElementById('password').value;
      const meter = document.getElementById('strengthMeter');
      
      // Requirements
      const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
      };

      // Update requirement indicators
      updateRequirement('req-length', requirements.length);
      updateRequirement('req-uppercase', requirements.uppercase);
      updateRequirement('req-lowercase', requirements.lowercase);
      updateRequirement('req-number', requirements.number);
      updateRequirement('req-special', requirements.special);

      // Calculate strength score
      const score = Object.values(requirements).filter(Boolean).length;
      
      // Update strength meter
      meter.className = 'strength-meter-fill';
      if (score <= 2) {
        meter.classList.add('strength-weak');
      } else if (score <= 4) {
        meter.classList.add('strength-medium');
      } else {
        meter.classList.add('strength-strong');
      }
    }

    function updateRequirement(id, met) {
      const element = document.getElementById(id);
      const icon = element.querySelector('i');
      
      if (met) {
        element.classList.add('met');
        icon.classList.remove('bi-x-circle');
        icon.classList.add('bi-check-circle-fill');
      } else {
        element.classList.remove('met');
        icon.classList.remove('bi-check-circle-fill');
        icon.classList.add('bi-x-circle');
      }
    }

    // Form Validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      
      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
      }

      // Check password strength
      const requirements = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
      };

      const allMet = Object.values(requirements).every(Boolean);
      
      if (!allMet) {
        e.preventDefault();
        alert('Please ensure your password meets all requirements!');
        return false;
      }

      // Check terms acceptance
      const termsChecked = document.getElementById('agreeTerms').checked;
      if (!termsChecked) {
        e.preventDefault();
        alert('Please accept the Terms and Conditions to continue!');
        return false;
      }
    });
  </script>
</body>
</html>