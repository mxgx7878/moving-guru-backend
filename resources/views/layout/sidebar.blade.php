<aside class="layout-sidebar">
    <nav class="p-3 h-100 d-flex flex-column">
        <!-- Brand Section -->
        <div class="mb-4 pb-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary text-white rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="bi bi-box-seam" style="font-size: 1.25rem;"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold">Material Connect</h6>
                    <small class="text-muted">Admin Panel</small>
                </div>
            </div>
        </div>
        @if(auth()->user() && auth()->user()->role=="admin")
        <!-- Navigation Links -->
        <div class="flex-grow-1 overflow-auto">
            <!-- Dashboard -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Overview</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link active d-flex align-items-center gap-2 rounded-3" href="{{ url('/dashboard') }}">
                            <i class="bi bi-speedometer2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/analytics') }}">
                            <i class="bi bi-graph-up"></i>
                            <span>Analytics</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Core Business -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Core Business</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ route('clients') }}">
                            <i class="bi bi-people"></i>
                            <span>Clients</span>
                            <span class="ms-auto badge bg-primary-subtle text-primary rounded-pill">84</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/projects') }}">
                            <i class="bi bi-folder"></i>
                            <span>Projects</span>
                            <span class="ms-auto badge bg-warning-subtle text-warning rounded-pill">127</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/orders') }}">
                            <i class="bi bi-cart-check"></i>
                            <span>Orders</span>
                            <span class="ms-auto badge bg-success-subtle text-success rounded-pill">156</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Inventory & Suppliers -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Inventory</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/products') }}">
                            <i class="bi bi-box-seam"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/suppliers') }}">
                            <i class="bi bi-truck"></i>
                            <span>Suppliers</span>
                            <span class="ms-auto badge bg-info-subtle text-info rounded-pill">42</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/supplier-offers') }}">
                            <i class="bi bi-tag"></i>
                            <span>Supplier Offers</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Financial -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Financial</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/payments') }}">
                            <i class="bi bi-credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/invoices') }}">
                            <i class="bi bi-receipt"></i>
                            <span>Invoices</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/pricing-rules') }}">
                            <i class="bi bi-percent"></i>
                            <span>Pricing Rules</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- System Configuration -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Configuration</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/delivery-zones') }}">
                            <i class="bi bi-geo-alt"></i>
                            <span>Delivery Zones</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/approvals') }}">
                            <i class="bi bi-check-circle"></i>
                            <span>Approvals</span>
                            <span class="ms-auto badge bg-danger rounded-pill">3</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/settings') }}">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Reports -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted mb-2 px-2" style="font-size: 0.7rem; letter-spacing: 0.5px;">Reports</h6>
                <ul class="nav nav-pills flex-column gap-1">
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/reports/revenue') }}">
                            <i class="bi bi-bar-chart-line"></i>
                            <span>Revenue Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/reports/suppliers') }}">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Supplier Performance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2 rounded-3" href="{{ url('/reports/clients') }}">
                            <i class="bi bi-person-lines-fill"></i>
                            <span>Client Spending</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Bottom Section - User & Logout -->
        <div class="mt-auto pt-3 border-top">
            <div class="d-flex align-items-center gap-2 p-2 rounded-3 bg-light">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="bi bi-person"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold small">Admin User</div>
                    <small class="text-muted" style="font-size: 0.7rem;">admin@material.com</small>
                </div>
                <a href="{{ url('/logout') }}" class="text-muted" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
        @endif
    </nav>
</aside>

