        </main>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: var(--z-toast);"></div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} slide-up`;
            toast.style.marginTop = '10px';
            toast.style.minWidth = '300px';
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
        
        // Format currency
        function formatCurrency(amount, currency = 'TRY') {
            const symbols = { TRY: '₺', USD: '$', EUR: '€' };
            return symbols[currency] + new Intl.NumberFormat('tr-TR', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            }).format(amount);
        }
        
        // Mobile sidebar toggle
        const sidebar = document.querySelector('.sidebar');
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024) {
                if (e.target.closest('.sidebar-toggle')) {
                    sidebar.classList.toggle('open');
                } else if (!e.target.closest('.sidebar')) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>
