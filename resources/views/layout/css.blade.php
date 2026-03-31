<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* CSS Variables */
:root {
  --header-h: 64px;
  --sidebar-w: 260px;
  --primary-color: #0d6efd;
  --sidebar-bg: #ffffff;
  --content-bg: #f8f9fa;
}

/* Base Styles */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  padding-top: var(--header-h);
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  background-color: var(--content-bg);
  color: #212529;
}

/* Layout Structure */
.layout-sidebar {
  position: fixed;
  top: var(--header-h);
  left: 0;
  width: var(--sidebar-w);
  height: calc(100vh - var(--header-h));
  overflow-y: auto;
  background: var(--sidebar-bg);
  border-right: 1px solid #e9ecef;
  z-index: 1000;
  transition: transform 0.3s ease;
}

.layout-content {
  margin-left: 0;
  padding: 24px;
  min-height: calc(100vh - var(--header-h));
  transition: margin-left 0.3s ease;
}

/* Desktop: Show sidebar */
@media (min-width: 992px) {
  .layout-content {
    margin-left: var(--sidebar-w);
  }
}

/* Mobile: Hide sidebar by default */
@media (max-width: 991.98px) {
  .layout-sidebar {
    transform: translateX(-100%);
    box-shadow: none;
  }
  
  .layout-sidebar.show {
    transform: translateX(0);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
  }
  
  /* Overlay when sidebar is open on mobile */
  .layout-sidebar.show::before {
    content: '';
    position: fixed;
    top: 0;
    left: var(--sidebar-w);
    width: calc(100vw - var(--sidebar-w));
    height: 100vh;
    background: rgba(0, 0, 0, 0.5);
    z-index: -1;
  }
}

/* Sidebar Scrollbar */
.layout-sidebar::-webkit-scrollbar {
  width: 6px;
}

.layout-sidebar::-webkit-scrollbar-track {
  background: transparent;
}

.layout-sidebar::-webkit-scrollbar-thumb {
  background: #dee2e6;
  border-radius: 3px;
}

.layout-sidebar::-webkit-scrollbar-thumb:hover {
  background: #adb5bd;
}

/* Card Enhancements */
.card {
  border: none;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: box-shadow 0.3s ease;
}

.card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.card-title {
  font-weight: 600;
  color: #212529;
}

/* Table Improvements */
.table {
  font-size: 0.9rem;
}

.table thead th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.75rem;
  letter-spacing: 0.5px;
  color: #6c757d;
  border-bottom: 2px solid #e9ecef;
}

.table-hover tbody tr:hover {
  background-color: #f8f9fa;
  cursor: pointer;
}

/* Badge Enhancements */
.badge {
  font-weight: 500;
  padding: 0.35em 0.65em;
}

/* Button Enhancements */
.btn {
  font-weight: 500;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: #0b5ed7;
  border-color: #0a58ca;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
}

/* Form Control Enhancements */
.form-control:focus,
.form-select:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

/* Utility Classes */
.text-primary {
  color: var(--primary-color) !important;
}

.bg-primary {
  background-color: var(--primary-color) !important;
}

/* Responsive Typography */
h1, h2, h3, h4, h5, h6 {
  font-weight: 600;
  line-height: 1.2;
}

/* Loading States */
.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Print Styles */
@media print {
  .layout-sidebar,
  header,
  .no-print {
    display: none !important;
  }
  
  .layout-content {
    margin-left: 0 !important;
    padding: 0 !important;
  }
}

/* Accessibility Improvements */
.btn:focus,
.form-control:focus,
.form-select:focus {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* Dark mode support (optional) */
@media (prefers-color-scheme: dark) {
  /* Add dark mode styles here if needed */
}
</style>



<style>
/* Header Styles */
header {
    backdrop-filter: blur(10px);
}

header .form-control {
    border: 1px solid #e9ecef;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

header .form-control:focus {
    background-color: #ffffff;
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
}

/* Dropdown Improvements */
.dropdown-menu {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-top: 0.5rem;
}

.dropdown-item {
    padding: 0.625rem 1rem;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item i {
    font-size: 1.1rem;
    width: 20px;
}

/* Notification Badge Animation */
@keyframes pulse {
    0% {
        transform: translate(-50%, -50%) scale(1);
    }
    50% {
        transform: translate(-50%, -50%) scale(1.1);
    }
    100% {
        transform: translate(-50%, -50%) scale(1);
    }
}

.badge.bg-danger {
    animation: pulse 2s infinite;
}
</style>




<style>
    /* Enhanced Sidebar Styles */
.layout-sidebar .nav-link {
    color: #495057;
    padding: 0.625rem 0.75rem;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
}

.layout-sidebar .nav-link:hover {
    background-color: #f8f9fa;
    color: #0d6efd;
}

.layout-sidebar .nav-link.active {
    background-color: #0d6efd;
    color: #ffffff;
}

.layout-sidebar .nav-link.active:hover {
    background-color: #0b5ed7;
}

.layout-sidebar .nav-link i {
    font-size: 1.1rem;
    width: 20px;
}
</style>



@stack('styles')


