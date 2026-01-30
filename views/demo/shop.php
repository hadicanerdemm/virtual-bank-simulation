<?php
/**
 * Demo E-Commerce Shop
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechShop - Demo Mağaza</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
    <style>
        .shop-header {
            background: var(--bg-secondary);
            padding: var(--space-lg) var(--space-xl);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .shop-brand {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-cyan);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--space-xl);
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-2xl);
        }
        
        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            overflow: hidden;
            transition: all var(--transition-normal);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-glow);
        }
        
        .product-image {
            height: 200px;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
        
        .product-info {
            padding: var(--space-lg);
        }
        
        .product-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--space-sm);
        }
        
        .product-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--space-md);
        }
        
        .product-price {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success);
            margin-bottom: var(--space-md);
        }
        
        .checkout-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .checkout-modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .checkout-panel {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 450px;
            padding: var(--space-2xl);
        }
    </style>
</head>
<body>
    <header class="shop-header">
        <div class="shop-brand">
            <i class="fas fa-laptop"></i> TechShop
        </div>
        <div style="display: flex; align-items: center; gap: var(--space-md);">
            <span class="badge badge-info">Demo Mağaza</span>
            <a href="/banka/public/dashboard" class="btn btn-ghost btn-sm">
                <i class="fas fa-arrow-left"></i> TurkPay'e Dön
            </a>
        </div>
    </header>
    
    <div style="max-width: 1200px; margin: 0 auto; padding: var(--space-xl);">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Demo Mağaza</strong> - Bu sayfa TurkPay entegrasyonunu test etmek için oluşturulmuştur. 
                "Satın Al" butonuna tıklayarak ödeme akışını deneyimleyebilirsiniz.
            </div>
        </div>
    </div>
    
    <div class="product-grid">
        <div class="product-card fade-in">
            <div class="product-image">📱</div>
            <div class="product-info">
                <h3 class="product-title">iPhone 15 Pro Max</h3>
                <p class="product-description">256GB, Titanium Black, 5G destekli</p>
                <div class="product-price">₺74.999</div>
                <button class="btn btn-primary w-full" onclick="checkout('iPhone 15 Pro Max', 74999)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
        
        <div class="product-card fade-in" style="animation-delay: 0.1s">
            <div class="product-image">💻</div>
            <div class="product-info">
                <h3 class="product-title">MacBook Pro 16"</h3>
                <p class="product-description">M3 Pro, 18GB RAM, 512GB SSD</p>
                <div class="product-price">₺129.999</div>
                <button class="btn btn-primary w-full" onclick="checkout('MacBook Pro 16', 129999)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
        
        <div class="product-card fade-in" style="animation-delay: 0.2s">
            <div class="product-image">🎧</div>
            <div class="product-info">
                <h3 class="product-title">AirPods Pro 2</h3>
                <p class="product-description">USB-C, Aktif Gürültü Önleme</p>
                <div class="product-price">₺9.499</div>
                <button class="btn btn-primary w-full" onclick="checkout('AirPods Pro 2', 9499)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
        
        <div class="product-card fade-in" style="animation-delay: 0.3s">
            <div class="product-image">⌚</div>
            <div class="product-info">
                <h3 class="product-title">Apple Watch Ultra 2</h3>
                <p class="product-description">49mm, Titanyum, GPS + Cellular</p>
                <div class="product-price">₺42.999</div>
                <button class="btn btn-primary w-full" onclick="checkout('Apple Watch Ultra 2', 42999)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
        
        <div class="product-card fade-in" style="animation-delay: 0.4s">
            <div class="product-image">🖥️</div>
            <div class="product-info">
                <h3 class="product-title">iMac 24"</h3>
                <p class="product-description">M3, 8GB RAM, 256GB SSD, Blue</p>
                <div class="product-price">₺54.999</div>
                <button class="btn btn-primary w-full" onclick="checkout('iMac 24', 54999)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
        
        <div class="product-card fade-in" style="animation-delay: 0.5s">
            <div class="product-image">📺</div>
            <div class="product-info">
                <h3 class="product-title">iPad Pro 12.9"</h3>
                <p class="product-description">M2 chip, 256GB, Wi-Fi + Cellular</p>
                <div class="product-price">₺49.999</div>
                <button class="btn btn-primary w-full" onclick="checkout('iPad Pro 12.9', 49999)">
                    <i class="fas fa-shopping-cart"></i> Satın Al
                </button>
            </div>
        </div>
    </div>
    
    <!-- Checkout Modal -->
    <div class="checkout-modal" id="checkoutModal">
        <div class="checkout-panel">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-xl);">
                <h2 style="font-size: 1.25rem; margin: 0;">Sipariş Özeti</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="orderSummary" style="padding: var(--space-lg); background: var(--bg-glass); border-radius: var(--radius-md); margin-bottom: var(--space-xl);">
                <!-- Filled by JS -->
            </div>
            
            <form id="checkoutForm" method="POST" action="/banka/public/demo/shop/checkout">
                <input type="hidden" name="product_name" id="productName">
                <input type="hidden" name="amount" id="productAmount">
                
                <div class="form-group">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="email" class="form-input" required placeholder="ornek@email.com">
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg w-full">
                    <i class="fas fa-lock"></i>
                    TurkPay ile Öde
                </button>
            </form>
            
            <div style="margin-top: var(--space-lg); text-align: center;">
                <img src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 30'><text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle' fill='%236366f1' font-family='sans-serif' font-size='10' font-weight='bold'>TurkPay ile Güvenle Ödeyin</text></svg>" alt="TurkPay" style="height: 30px; opacity: 0.8;">
            </div>
        </div>
    </div>
    
    <script>
        function checkout(name, price) {
            document.getElementById('productName').value = name;
            document.getElementById('productAmount').value = price;
            document.getElementById('orderSummary').innerHTML = `
                <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-md);">
                    <span>${name}</span>
                    <span class="font-mono">₺${price.toLocaleString('tr-TR')}</span>
                </div>
                <hr style="border: none; border-top: 1px solid var(--border-color); margin: var(--space-md) 0;">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                    <span>Toplam</span>
                    <span class="font-mono text-success">₺${price.toLocaleString('tr-TR')}</span>
                </div>
            `;
            document.getElementById('checkoutModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('checkoutModal').classList.remove('active');
        }
        
        document.getElementById('checkoutModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeModal();
        });
    </script>
</body>
</html>
