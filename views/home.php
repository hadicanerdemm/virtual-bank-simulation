<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TurkPay - Sanal Banka ve Ödeme Sistemi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/banka/public/assets/css/style.css">
    <style>
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .hero-nav {
            padding: var(--space-lg) var(--space-xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .hero-content {
            flex: 1;
            display: flex;
            align-items: center;
            padding: var(--space-3xl);
        }
        
        .hero-left {
            flex: 1;
            max-width: 600px;
        }
        
        .hero-right {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: var(--space-lg);
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            margin-bottom: var(--space-2xl);
            line-height: 1.6;
        }
        
        .hero-buttons {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-3xl);
        }
        
        .hero-features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-xl);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .feature-text {
            font-size: 0.9375rem;
            color: var(--text-secondary);
        }
        
        .hero-card-stack {
            position: relative;
            width: 380px;
            height: 450px;
        }
        
        .hero-card-stack .virtual-card {
            position: absolute;
            box-shadow: var(--shadow-lg);
        }
        
        .hero-card-stack .virtual-card:nth-child(1) {
            top: 0;
            left: 0;
            transform: rotate(-8deg);
            z-index: 1;
        }
        
        .hero-card-stack .virtual-card:nth-child(2) {
            top: 80px;
            left: 40px;
            transform: rotate(5deg);
            z-index: 2;
        }
        
        .stats-section {
            background: var(--bg-secondary);
            padding: var(--space-3xl);
            border-top: 1px solid var(--border-color);
        }
        
        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-xl);
            text-align: center;
        }
        
        .stat-number {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-text {
            color: var(--text-secondary);
            margin-top: var(--space-sm);
        }
        
        .features-section {
            padding: var(--space-3xl) * 2;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: var(--space-lg);
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 1.125rem;
            margin-bottom: var(--space-3xl);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-xl);
        }
        
        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: var(--space-2xl);
            text-align: center;
            transition: all var(--transition-normal);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-primary);
            box-shadow: var(--shadow-glow);
        }
        
        .feature-card-icon {
            width: 72px;
            height: 72px;
            background: var(--gradient-primary);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto var(--space-lg);
        }
        
        .feature-card-title {
            font-size: 1.25rem;
            margin-bottom: var(--space-md);
        }
        
        .feature-card-text {
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        @media (max-width: 1024px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-right {
                margin-top: var(--space-3xl);
            }
            
            .hero-features {
                grid-template-columns: 1fr;
                gap: var(--space-md);
            }
            
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .hero-card-stack {
                transform: scale(0.8);
            }
            
            .features-grid, .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <nav class="hero-nav">
            <div class="nav-brand">
                <div class="nav-logo">₺</div>
                <span class="nav-title">TurkPay</span>
            </div>
            <div class="hero-nav-links">
                <a href="/banka/public/login" class="btn btn-ghost">Giriş Yap</a>
                <a href="/banka/public/register" class="btn btn-primary">Hesap Oluştur</a>
            </div>
        </nav>
        
        <div class="hero-content container">
            <div class="hero-left">
                <h1 class="hero-title">
                    Geleceğin <span class="text-gradient">Dijital Bankası</span> Burada
                </h1>
                <p class="hero-subtitle">
                    Güvenli para transferleri, anlık ödemeler, sanal kartlar ve güçlü API entegrasyonu ile 
                    işletmenizi bir sonraki seviyeye taşıyın.
                </p>
                <div class="hero-buttons">
                    <a href="/banka/public/register" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket"></i>
                        Ücretsiz Başla
                    </a>
                    <a href="/banka/public/demo/shop" class="btn btn-secondary btn-lg">
                        <i class="fas fa-play-circle"></i>
                        Demo'yu İncele
                    </a>
                </div>
                <div class="hero-features">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <span class="feature-text">3D Secure</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <span class="feature-text">Anlık Transfer</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-code"></i>
                        </div>
                        <span class="feature-text">RESTful API</span>
                    </div>
                </div>
            </div>
            
            <div class="hero-right">
                <div class="hero-card-stack">
                    <div class="virtual-card visa">
                        <div class="card-chip"></div>
                        <div class="card-number">4532 •••• •••• 7890</div>
                        <div class="card-info">
                            <div>
                                <div class="card-holder">Kart Sahibi</div>
                                <div class="card-holder-name">AHMET YILMAZ</div>
                            </div>
                            <div>
                                <div class="card-expiry-label">Valid Thru</div>
                                <div class="card-expiry">12/28</div>
                            </div>
                        </div>
                        <div class="card-type-logo">VISA</div>
                    </div>
                    <div class="virtual-card mastercard">
                        <div class="card-chip"></div>
                        <div class="card-number">5412 •••• •••• 3456</div>
                        <div class="card-info">
                            <div>
                                <div class="card-holder">Kart Sahibi</div>
                                <div class="card-holder-name">MEHMET ÖZTÜRK</div>
                            </div>
                            <div>
                                <div class="card-expiry-label">Valid Thru</div>
                                <div class="card-expiry">09/27</div>
                            </div>
                        </div>
                        <div class="card-type-logo">MC</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-item">
                <div class="stat-number">10M+</div>
                <div class="stat-text">İşlem Hacmi</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-text">Aktif Kullanıcı</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">99.9%</div>
                <div class="stat-text">Uptime</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">0.01s</div>
                <div class="stat-text">İşlem Süresi</div>
            </div>
        </div>
    </section>
    
    <section class="features-section">
        <h2 class="section-title">Neden <span class="text-gradient">TurkPay</span>?</h2>
        <p class="section-subtitle">
            Modern bankacılık altyapımız ile işletmenizin tüm ödeme ihtiyaçlarını karşılayın
        </p>
        
        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-card-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h3 class="feature-card-title">Sanal Kartlar</h3>
                <p class="feature-card-text">
                    Anında sanal kart oluşturun. Online alışverişleriniz için güvenli, limit 
                    ayarlanabilir kartlar.
                </p>
            </div>
            
            <div class="feature-card fade-in" style="animation-delay: 0.1s">
                <div class="feature-card-icon" style="background: var(--gradient-success);">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3 class="feature-card-title">Çoklu Para Birimi</h3>
                <p class="feature-card-text">
                    TRY, USD ve EUR cüzdanlarınız arasında anlık döviz çevirimi yapın. 
                    Gerçek zamanlı kurlar.
                </p>
            </div>
            
            <div class="feature-card fade-in" style="animation-delay: 0.2s">
                <div class="feature-card-icon" style="background: var(--gradient-accent);">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="feature-card-title">3D Secure</h3>
                <p class="feature-card-text">
                    Tüm ödemeleriniz 3D Secure ile korunur. SMS OTP doğrulaması ile 
                    ekstra güvenlik.
                </p>
            </div>
            
            <div class="feature-card fade-in" style="animation-delay: 0.3s">
                <div class="feature-card-icon" style="background: linear-gradient(135deg, #ec4899, #f43f5e);">
                    <i class="fas fa-webhook"></i>
                </div>
                <h3 class="feature-card-title">Webhook Bildirimleri</h3>
                <p class="feature-card-text">
                    Ödeme durumlarını anında öğrenin. Webhook entegrasyonu ile 
                    otomatik sipariş güncellemeleri.
                </p>
            </div>
            
            <div class="feature-card fade-in" style="animation-delay: 0.4s">
                <div class="feature-card-icon" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="feature-card-title">Detaylı Raporlar</h3>
                <p class="feature-card-text">
                    Tüm işlemlerinizi detaylı grafikler ve raporlar ile takip edin. 
                    CSV ve PDF export.
                </p>
            </div>
            
            <div class="feature-card fade-in" style="animation-delay: 0.5s">
                <div class="feature-card-icon" style="background: linear-gradient(135deg, #8b5cf6, #06b6d4);">
                    <i class="fas fa-shield-virus"></i>
                </div>
                <h3 class="feature-card-title">Fraud Koruması</h3>
                <p class="feature-card-text">
                    Yapay zeka destekli dolandırıcılık koruması. Şüpheli işlemler 
                    otomatik engellenir.
                </p>
            </div>
        </div>
    </section>
    
    <footer style="background: var(--bg-secondary); padding: var(--space-2xl); text-align: center; border-top: 1px solid var(--border-color);">
        <div class="nav-brand" style="justify-content: center; margin-bottom: var(--space-lg);">
            <div class="nav-logo">₺</div>
            <span class="nav-title">TurkPay</span>
        </div>
        <p style="color: var(--text-muted); font-size: 0.875rem;">
            © 2026 TurkPay. Bu proje eğitim amaçlıdır. Gerçek bankacılık hizmeti değildir.
        </p>
    </footer>
</body>
</html>
