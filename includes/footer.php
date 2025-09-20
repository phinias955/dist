            </main>
        </div>
    </div>
    
    <!-- JavaScript for interactive features -->
    <script>
        // Mobile menu toggle
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.bg-white.shadow-lg');
            sidebar.classList.toggle('hidden');
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
        
        // Confirm delete actions
        function confirmDelete(message = 'Are you sure you want to delete this item?') {
            return confirm(message);
        }
        
        // Form validation
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (form) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        field.classList.remove('border-red-500');
                    }
                });
                
                return isValid;
            }
            return true;
        }
    </script>
</body>
</html>
