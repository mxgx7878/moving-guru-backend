@extends('layout.main')

@section('title', 'Dashboard - Material Connect')

@push('styles')
<style>
/* Dashboard Specific Styles */
.metric-card {
    border: none;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.metric-card .metric-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.metric-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.25rem;
}
.metric-label {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
}
.metric-change {
    font-size: 0.75rem;
    font-weight: 600;
}
.activity-item {
    border-left: 3px solid #e9ecef;
    transition: border-color 0.2s;
}
.activity-item:hover {
    border-left-color: #0d6efd;
    background-color: #f8f9fa;
}
.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.chart-card {
    height: 100%;
    min-height: 300px;
}
.quick-action-btn {
    border: 2px dashed #dee2e6;
    border-radius: 10px;
    padding: 1.25rem;
    text-align: center;
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: block;
}
.quick-action-btn:hover {
    border-color: #0d6efd;
    background-color: #f8f9ff;
    transform: translateY(-2px);
}
.quick-action-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}
</style>
@endpush

@section('content')
<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">Welcome back, Admin</h2>
                <p class="text-muted mb-0">Here's what's happening with your business today.</p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    <i class="bi bi-calendar3"></i> {{ date('l, F j, Y') }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Key Metrics Row -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="metric-icon bg-primary-subtle text-primary">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <span class="metric-change text-success">
                        <i class="bi bi-arrow-up"></i> 12.5%
                    </span>
                </div>
                <div class="metric-value text-dark">156</div>
                <div class="metric-label">Active Orders</div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="metric-icon bg-success-subtle text-success">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                    <span class="metric-change text-success">
                        <i class="bi bi-arrow-up"></i> 8.2%
                    </span>
                </div>
                <div class="metric-value text-dark">$45,230</div>
                <div class="metric-label">Monthly Revenue</div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="metric-icon bg-warning-subtle text-warning">
                        <i class="bi bi-people"></i>
                    </div>
                    <span class="metric-change text-success">
                        <i class="bi bi-arrow-up"></i> 3.1%
                    </span>
                </div>
                <div class="metric-value text-dark">84</div>
                <div class="metric-label">Active Clients</div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card metric-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="metric-icon bg-info-subtle text-info">
                        <i class="bi bi-truck"></i>
                    </div>
                    <span class="metric-change text-danger">
                        <i class="bi bi-arrow-down"></i> 2.4%
                    </span>
                </div>
                <div class="metric-value text-dark">42</div>
                <div class="metric-label">Active Suppliers</div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <h5 class="mb-3">Quick Actions</h5>
    </div>
    <div class="col-6 col-md-3">
        <a href="#" class="quick-action-btn">
            <div class="quick-action-icon text-primary">
                <i class="bi bi-plus-circle"></i>
            </div>
            <div class="fw-semibold">New Order</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="#" class="quick-action-btn">
            <div class="quick-action-icon text-success">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="fw-semibold">Add Client</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="#" class="quick-action-btn">
            <div class="quick-action-icon text-warning">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="fw-semibold">Add Product</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="#" class="quick-action-btn">
            <div class="quick-action-icon text-info">
                <i class="bi bi-building"></i>
            </div>
            <div class="fw-semibold">Add Supplier</div>
        </a>
    </div>
</div>

<!-- Charts and Activity -->
<div class="row g-3 mb-4">
    <!-- Revenue Chart -->
    <div class="col-12 col-lg-8">
        <div class="card chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0">Revenue Overview</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary">Week</button>
                        <button type="button" class="btn btn-outline-secondary active">Month</button>
                        <button type="button" class="btn btn-outline-secondary">Year</button>
                    </div>
                </div>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-bar-chart-line" style="font-size: 3rem;"></i>
                    <p class="mt-3">Chart will be rendered here using Chart.js or similar</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="col-12 col-lg-4">
        <div class="card chart-card">
            <div class="card-body">
                <h5 class="card-title mb-4">Recent Activity</h5>
                <div class="d-flex flex-column gap-3">
                    <div class="activity-item ps-3 py-2">
                        <div class="d-flex gap-3">
                            <div class="activity-icon bg-primary-subtle text-primary">
                                <i class="bi bi-cart-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">New Order #1234</div>
                                <div class="text-muted small">Construction Site A</div>
                                <div class="text-muted small">2 minutes ago</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item ps-3 py-2">
                        <div class="d-flex gap-3">
                            <div class="activity-icon bg-success-subtle text-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Payment Received</div>
                                <div class="text-muted small">ABC Construction Ltd</div>
                                <div class="text-muted small">15 minutes ago</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item ps-3 py-2">
                        <div class="d-flex gap-3">
                            <div class="activity-icon bg-warning-subtle text-warning">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">New Client Added</div>
                                <div class="text-muted small">XYZ Builders</div>
                                <div class="text-muted small">1 hour ago</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item ps-3 py-2">
                        <div class="d-flex gap-3">
                            <div class="activity-icon bg-info-subtle text-info">
                                <i class="bi bi-truck"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Supplier Approved</div>
                                <div class="text-muted small">Premium Materials Co</div>
                                <div class="text-muted small">3 hours ago</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-item ps-3 py-2">
                        <div class="d-flex gap-3">
                            <div class="activity-icon bg-danger-subtle text-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small">Order Delayed</div>
                                <div class="text-muted small">Order #1201</div>
                                <div class="text-muted small">5 hours ago</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-sm btn-link">View All Activity</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row - Orders & Suppliers -->
<div class="row g-3">
    <!-- Pending Approvals -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Pending Approvals</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-warning-subtle text-warning">Supplier</span></td>
                                <td>BuildMart Supplies</td>
                                <td class="text-muted small">Oct 1, 2025</td>
                                <td>
                                    <button class="btn btn-sm btn-success me-1">Approve</button>
                                    <button class="btn btn-sm btn-danger">Reject</button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-info-subtle text-info">Product</span></td>
                                <td>Premium Cement 50kg</td>
                                <td class="text-muted small">Sep 30, 2025</td>
                                <td>
                                    <button class="btn btn-sm btn-success me-1">Approve</button>
                                    <button class="btn btn-sm btn-danger">Reject</button>
                                </td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning-subtle text-warning">Supplier</span></td>
                                <td>Steel Solutions Inc</td>
                                <td class="text-muted small">Sep 29, 2025</td>
                                <td>
                                    <button class="btn btn-sm btn-success me-1">Approve</button>
                                    <button class="btn btn-sm btn-danger">Reject</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Suppliers -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Top Performing Suppliers</h5>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Premium Materials Co</div>
                                <div class="small text-muted">142 orders completed</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold text-success">$128,450</div>
                            <div class="small text-muted">Revenue</div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">BuildMart Supplies</div>
                                <div class="small text-muted">98 orders completed</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold text-success">$95,320</div>
                            <div class="small text-muted">Revenue</div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Steel Solutions Inc</div>
                                <div class="small text-muted">76 orders completed</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold text-success">$72,890</div>
                            <div class="small text-muted">Revenue</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
    console.log('Material Connect Dashboard Loaded');
    
    // You can initialize Chart.js or other visualization libraries here
    // Example: Revenue chart, order trends, etc.
});
</script>
@endpush