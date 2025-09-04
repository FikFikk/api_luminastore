<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Gagal - Order #$OrderID</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
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

        .failed-header {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 50px 30px;
            text-align: center;
            position: relative;
        }

        .failed-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: shake 1s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .failed-title {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .failed-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .order-ref {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }

        .content {
            padding: 40px 30px;
            text-align: center;
        }

        .error-details {
            background: #fff5f5;
            border: 2px solid #feb2b2;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .error-code {
            font-size: 3rem;
            color: #e53e3e;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .error-message {
            color: #742a2a;
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .order-info {
            background: #f7fafc;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #4a5568;
            font-weight: 600;
        }

        .info-value {
            color: #2d3748;
            font-weight: 500;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(66, 153, 225, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #fc8181, #f56565);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 101, 101, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        .help-section {
            background: #ebf8ff;
            border: 2px solid #bee3f8;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .help-title {
            color: #2b6cb0;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-text {
            color: #2c5282;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .help-list {
            list-style: none;
            padding: 0;
        }

        .help-list li {
            color: #2c5282;
            padding: 8px 0;
            padding-left: 30px;
            position: relative;
        }

        .help-list li::before {
            content: '•';
            color: #3182ce;
            font-size: 1.5rem;
            position: absolute;
            left: 10px;
            top: 5px;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .failed-header {
                padding: 40px 20px;
            }

            .failed-title {
                font-size: 1.8rem;
            }

            .content {
                padding: 30px 20px;
            }

            .actions {
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                width: 100%;
            }

            .info-row {
                flex-direction: column;
                gap: 5px;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Failed Header -->
        <div class="failed-header">
            <div class="failed-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1 class="failed-title">Pembayaran Gagal</h1>
            <p class="failed-subtitle">Maaf, pembayaran Anda tidak dapat diproses</p>
            <div class="order-ref">Order #$OrderID</div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Error Details -->
            <div class="error-details">
                <% if $ErrorCode %>
                <div class="error-code">Error $ErrorCode</div>
                <% end_if %>
                <div class="error-message">$ErrorMessage</div>
            </div>

            <!-- Order Information -->
            <div class="order-info">
                <h3 style="color: #2d3748; margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Informasi Pesanan
                </h3>
                
                <div class="info-row">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#$OrderID</span>
                </div>
                
                <% if $OrderReference %>
                <div class="info-row">
                    <span class="info-label">Referensi:</span>
                    <span class="info-value">$OrderReference</span>
                </div>
                <% end_if %>
                
                <div class="info-row">
                    <span class="info-label">Total Pembayaran:</span>
                    <span class="info-value">Rp $TotalPrice</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Tanggal Order:</span>
                    <span class="info-value">$OrderDate.Format('d/m/Y H:i')</span>
                </div>
            </div>

            <!-- Help Section -->
            <div class="help-section">
                <h3 class="help-title">
                    <i class="fas fa-question-circle"></i>
                    Apa yang bisa Anda lakukan?
                </h3>
                
                <p class="help-text">Jangan khawatir! Berikut beberapa langkah yang bisa Anda coba:</p>
                
                <ul class="help-list">
                    <li>Periksa kembali saldo atau limit kartu kredit Anda</li>
                    <li>Coba gunakan metode pembayaran yang berbeda</li>
                    <li>Pastikan koneksi internet Anda stabil</li>
                    <li>Hubungi customer service jika masalah berlanjut</li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="actions">
                <a href="/payment/retry/$OrderID" class="btn btn-danger">
                    <i class="fas fa-redo"></i> Coba Lagi
                </a>
                
                <a href="/" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Kembali ke Beranda
                </a>
                
                <a href="/contact" class="btn btn-primary">
                    <i class="fas fa-headset"></i> Hubungi Support
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate content on load
            const elements = document.querySelectorAll('.error-details, .order-info, .help-section');
            
            elements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';
                element.style.transition = `all 0.6s ease ${index * 0.2}s`;
                
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 200);
            });

            // Add click feedback for buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    ripple.style.position = 'absolute';
                    ripple.style.borderRadius = '50%';
                    ripple.style.background = 'rgba(255,255,255,0.6)';
                    ripple.style.transform = 'scale(0)';
                    ripple.style.animation = 'ripple 0.6s linear';
                    ripple.style.left = (e.offsetX - 10) + 'px';
                    ripple.style.top = (e.offsetY - 10) + 'px';
                    ripple.style.width = '20px';
                    ripple.style.height = '20px';
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
        });

        // Add ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>