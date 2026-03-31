<header class="fixed-top bg-white border-bottom shadow-sm" style="height: var(--header-h); z-index: 1030;">
  <div class="container-fluid h-100">
    <div class="d-flex align-items-center justify-content-between h-100 px-3">
      
      <!-- Left Section: Mobile Toggle + Logo -->
      <div class="d-flex align-items-center gap-3">
        <!-- Mobile Menu Toggle -->
        <button class="btn btn-link text-dark d-lg-none p-0" id="sidebarToggle" type="button">
          <i class="bi bi-list" style="font-size: 1.5rem;"></i>
        </button>
        
        <!-- Logo/Brand (visible on mobile when sidebar hidden) -->
        <div class="d-lg-none">
          <h5 class="mb-0 fw-bold">Material Connect</h5>
        </div>
      </div>

      <!-- Center Section: Search Bar -->
      <div class="flex-grow-1 px-4 d-none d-md-block" style="max-width: 500px;">
        <div class="position-relative">
          <input type="text" 
                 class="form-control form-control-sm rounded-pill ps-4" 
                 placeholder="Search orders, clients, products..."
                 id="globalSearch">
          <i class="bi bi-search position-absolute top-50 translate-middle-y text-muted" 
             style="left: 12px; font-size: 0.9rem;"></i>
        </div>
      </div>

      <!-- Right Section: Actions & User Menu -->
      <div class="d-flex align-items-center gap-2 gap-md-3">
        
        <!-- Search Icon (Mobile) -->
        <button class="btn btn-link text-dark d-md-none p-0" type="button" id="mobileSearchToggle">
          <i class="bi bi-search" style="font-size: 1.25rem;"></i>
        </button>

        <!-- Quick Add Dropdown -->
        <div class="dropdown">
          <button class="btn btn-primary btn-sm rounded-pill d-none d-md-flex align-items-center gap-2 px-3" 
                  type="button" 
                  id="quickAddDropdown" 
                  data-bs-toggle="dropdown" 
                  aria-expanded="false">
            <i class="bi bi-plus-lg"></i>
            <span>Quick Add</span>
          </button>
          <button class="btn btn-primary btn-sm rounded-circle d-md-none p-2" 
                  type="button" 
                  id="quickAddDropdownMobile" 
                  data-bs-toggle="dropdown" 
                  aria-expanded="false"
                  style="width: 36px; height: 36px;">
            <i class="bi bi-plus-lg"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 200px;">
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/orders/create') }}">
                <i class="bi bi-cart-plus text-primary"></i>
                <span>New Order</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/clients/create') }}">
                <i class="bi bi-person-plus text-success"></i>
                <span>New Client</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/projects/create') }}">
                <i class="bi bi-folder-plus text-warning"></i>
                <span>New Project</span>
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/products/create') }}">
                <i class="bi bi-box-seam text-info"></i>
                <span>New Product</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/suppliers/create') }}">
                <i class="bi bi-truck text-secondary"></i>
                <span>New Supplier</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- Notifications -->
        <div class="dropdown">
          <button class="btn btn-link text-dark position-relative p-0" 
                  type="button" 
                  id="notificationsDropdown" 
                  data-bs-toggle="dropdown" 
                  aria-expanded="false">
            <i class="bi bi-bell" style="font-size: 1.25rem;"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                  style="font-size: 0.65rem;">
              5
            </span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 320px; max-height: 400px; overflow-y: auto;">
            <li class="px-3 py-2 border-bottom">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Notifications</h6>
                <a href="#" class="text-decoration-none small">Mark all read</a>
              </div>
            </li>
            <li>
              <a class="dropdown-item py-3 border-bottom" href="#">
                <div class="d-flex gap-2">
                  <div class="flex-shrink-0">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                      <i class="bi bi-cart-check"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold small">New Order Received</div>
                    <div class="text-muted small">Order #1234 from ABC Construction</div>
                    <div class="text-muted small mt-1">
                      <i class="bi bi-clock"></i> 5 minutes ago
                    </div>
                  </div>
                </div>
              </a>
            </li>
            <li>
              <a class="dropdown-item py-3 border-bottom" href="#">
                <div class="d-flex gap-2">
                  <div class="flex-shrink-0">
                    <div class="bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                      <i class="bi bi-exclamation-triangle"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold small">Pending Approval</div>
                    <div class="text-muted small">Supplier needs approval</div>
                    <div class="text-muted small mt-1">
                      <i class="bi bi-clock"></i> 1 hour ago
                    </div>
                  </div>
                </div>
              </a>
            </li>
            <li>
              <a class="dropdown-item py-3 border-bottom" href="#">
                <div class="d-flex gap-2">
                  <div class="flex-shrink-0">
                    <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                      <i class="bi bi-check-circle"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold small">Payment Received</div>
                    <div class="text-muted small">$5,230 from XYZ Builders</div>
                    <div class="text-muted small mt-1">
                      <i class="bi bi-clock"></i> 2 hours ago
                    </div>
                  </div>
                </div>
              </a>
            </li>
            <li>
              <a class="dropdown-item py-3 border-bottom" href="#">
                <div class="d-flex gap-2">
                  <div class="flex-shrink-0">
                    <div class="bg-info-subtle text-info rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px;">
                      <i class="bi bi-truck"></i>
                    </div>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold small">Delivery Update</div>
                    <div class="text-muted small">Order #1201 out for delivery</div>
                    <div class="text-muted small mt-1">
                      <i class="bi bi-clock"></i> 3 hours ago
                    </div>
                  </div>
                </div>
              </a>
            </li>
            <li class="text-center py-2">
              <a href="{{ url('/notifications') }}" class="text-decoration-none small fw-semibold">View All Notifications</a>
            </li>
          </ul>
        </div>

        <!-- User Menu -->
        <div class="dropdown">
          <button class="btn btn-link text-dark d-flex align-items-center gap-2 p-0 text-decoration-none" 
                  type="button" 
                  id="userMenuDropdown" 
                  data-bs-toggle="dropdown" 
                  aria-expanded="false">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                 style="width: 36px; height: 36px;">
              <i class="bi bi-person"></i>
            </div>
            <div class="d-none d-lg-block text-start">
              <div class="fw-semibold small">Admin User</div>
              <div class="text-muted" style="font-size: 0.7rem;">Administrator</div>
            </div>
            <i class="bi bi-chevron-down d-none d-lg-block small"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 220px;">
            <li class="px-3 py-2 border-bottom d-lg-none">
              <div class="fw-semibold">Admin User</div>
              <div class="text-muted small">admin@material.com</div>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/profile') }}">
                <i class="bi bi-person"></i>
                <span>My Profile</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/settings') }}">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
              </a>
            </li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2" href="{{ url('/help') }}">
                <i class="bi bi-question-circle"></i>
                <span>Help Center</span>
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="{{ route('logout') }}">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
              </a>
            </li>
          </ul>
        </div>

      </div>
    </div>
  </div>

  <!-- Mobile Search Bar (Hidden by default) -->
  <div class="collapse border-top" id="mobileSearchCollapse">
    <div class="container-fluid py-2">
      <div class="position-relative">
        <input type="text" 
               class="form-control form-control-sm rounded-pill ps-4" 
               placeholder="Search orders, clients, products...">
        <i class="bi bi-search position-absolute top-50 translate-middle-y text-muted" 
           style="left: 12px; font-size: 0.9rem;"></i>
      </div>
    </div>
  </div>
</header>


