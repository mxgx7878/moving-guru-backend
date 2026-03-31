<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>

<!-- Popper + Bootstrap (your picks) -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"
        crossorigin="anonymous"></script>


<!-- Header -->
 <script>
// Mobile Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.layout-sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth < 992) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }
    
    // Mobile Search Toggle
    const mobileSearchToggle = document.getElementById('mobileSearchToggle');
    const mobileSearchCollapse = document.getElementById('mobileSearchCollapse');
    
    if (mobileSearchToggle && mobileSearchCollapse) {
        mobileSearchToggle.addEventListener('click', function() {
            const bsCollapse = new bootstrap.Collapse(mobileSearchCollapse, {
                toggle: true
            });
        });
    }
});
</script>
<!-- Header -->




@stack('scripts')
