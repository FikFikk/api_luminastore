<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil - Order #$OrderID</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .success-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 0.5;
                transform: scale(0.8);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.2);
            }
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 1s ease-in-out;
        }

        @keyframes bounce {
            0%, 20%, 60%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            80% {
                transform: translateY(-10px);
            }
        }

        .success-title {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .success-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .order-ref {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .content {
            padding: 40px 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            border-left: 5px solid #4CAF50;
        }

        .info-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 5px;
        }

        .items-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .items-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .items-header h3 {
            color: #333;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .total-items {
            background: #4CAF50;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .order-item:hover {
            background: rgba(76, 175, 80, 0.05);
            border-radius: 10px;
            padding-left: 10px;
            padding-right: 10px;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            margin-right: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .item-placeholder {
            width: 80px;
            height: 80px;
            background: #ddd;
            border-radius: 10px;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 2rem;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .item-quantity {
            color: #666;
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: 600;
            color: #4CAF50;
            font-size: 1.1rem;
            text-align: right;
        }

        .price-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            border: 2px solid #e9ecef;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .price-row:last-child {
            border-bottom: none;
            border-top: 2px solid #4CAF50;
            margin-top: 15px;
            padding-top: 20px;
            font-weight: bold;
            font-size: 1.2rem;
            color: #333;
        }

        .price-label {
            color: #666;
        }

        .price-value {
            font-weight: 600;
            color: #333;
        }

        .total-value {
            color: #4CAF50;
            font-size: 1.3rem;
        }

        .actions {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #f1f3f4;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #333;
            border: 2px solid #ddd;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        .status-badges {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .success-header {
                padding: 30px 20px;
            }

            .success-title {
                font-size: 1.8rem;
            }

            .content {
                padding: 30px 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .order-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .item-details {
                text-align: center;
            }

            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="success-title">Pembayaran Berhasil!</h1>
            <p class="success-subtitle">Terima kasih atas pembelian Anda</p>
            <div class="order-ref">Order #$OrderID</div>
            <div class="status-badges">
                <span class="status-badge status-paid">
                    <i class="fas fa-credit-card"></i> $PaymentStatus
                </span>
                <span class="status-badge status-pending">
                    <i class="fas fa-truck"></i> $ShippingStatus
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Order Information -->
            <div class="info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-receipt"></i> Detail Pesanan</h3>
                    <p><strong>Order ID:</strong> #$OrderID</p>
                    <p><strong>Tanggal:</strong> $OrderDate.Format('d/m/Y H:i')</p>
                    <p><strong>Referensi:</strong> $OrderReference</p>
                    <% if $PaymentReference %>
                    <p><strong>Payment Ref:</strong> $PaymentReference</p>
                    <% end_if %>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Informasi Pembeli</h3>
                    <p><strong>Nama:</strong> $CustomerName</p>
                    <p><strong>Email:</strong> $CustomerEmail</p>
                    <% if $CustomerPhone %>
                    <p><strong>Telepon:</strong> $CustomerPhone</p>
                    <% end_if %>
                </div>

                <div class="info-card">
                    <h3><i class="fas fa-shipping-fast"></i> Pengiriman</h3>
                    <p><strong>Kurir:</strong> $Courier $Service</p>
                    <p><strong>Alamat:</strong><br>$ShippingAddress</p>
                </div>
            </div>

            <!-- Order Items -->
            <div class="items-section">
                <div class="items-header">
                    <h3><i class="fas fa-shopping-bag"></i> Item Pesanan</h3>
                    <span class="total-items">$TotalItems Item</span>
                </div>

                <% loop $Items %>
                <div class="order-item">
                    <!-- Diperbaiki: Variabel ProductImage dari loop -->
                    <% if $ProductImage %>
                        <img src="$ProductImage" alt="$ProductTitle" class="item-image">
                    <% else %>
                        <div class="item-placeholder"><i class="fas fa-image"></i></div>
                    <% end_if %>
                    
                    <div class="item-details">
                        <!-- Diperbaiki: Variabel dari loop -->
                        <div class="item-name">$ProductTitle</div>
                        <div class="item-quantity">Jumlah: $Quantity &times; Rp $Price</div>
                    </div>
                    
                    <div class="item-price">
                        <!-- Diperbaiki: Variabel Subtotal dari loop -->
                        Rp $Subtotal
                    </div>
                </div>
                <% end_loop %>


            </div>

            <!-- Price Summary -->
            <div class="price-summary">
                <h3 style="margin-bottom: 20px; color: #333;">
                    <i class="fas fa-calculator"></i> Ringkasan Pembayaran
                </h3>
                
                <div class="price-row">
                    <span class="price-label">Subtotal:</span>
                    <span class="price-value">Rp $SubTotal</span>
                </div>
                
                <div class="price-row">
                    <span class="price-label">Ongkos Kirim ($Courier $Service):</span>
                    <span class="price-value">Rp $ShippingCost</span>
                </div>
                
                <div class="price-row">
                    <span class="price-label">Admin Fee:</span>
                    <span class="price-value">Rp $Fee</span>
                </div>
                
                <div class="price-row">
                    <span class="price-label">Total Pembayaran:</span>
                    <span class="price-value total-value">Rp $TotalPrice</span>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions">
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
                <a href="/my-orders" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Lihat Pesanan Saya
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate order items on scroll
            const items = document.querySelectorAll('.order-item');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateX(0)';
                    }
                });
            });

            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateX(-20px)';
                item.style.transition = `all 0.6s ease ${index * 0.1}s`;
                observer.observe(item);
            });

            // Confetti effect (simple)
            setTimeout(() => {
                createConfetti();
            }, 500);
        });

        function createConfetti() {
            const confetti = document.createElement('div');
            confetti.innerHTML = '🎉';
            confetti.style.position = 'fixed';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.fontSize = '2rem';
            confetti.style.zIndex = '9999';
            confetti.style.pointerEvents = 'none';
            
            document.body.appendChild(confetti);
            
            const animation = confetti.animate([
                { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
                { transform: 'translateY(100vh) rotate(360deg)', opacity: 0 }
            ], {
                duration: 3000,
                easing: 'linear'
            });
            
            animation.addEventListener('finish', () => {
                confetti.remove();
            });
        }

        // Create multiple confetti
        for (let i = 0; i < 10; i++) {
            setTimeout(createConfetti, i * 200);
        }
    </script>
</body>
</html>