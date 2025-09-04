<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TechEdge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #224abe;
            --background-color: #f8f9fa;
            --card-bg-color: #ffffff;
            --text-color: #2c3e50;
            --text-secondary: #555555;
            --font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .reset-card {
            background-color: var(--card-bg-color);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            border: none;
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px 40px;
            text-align: center;
        }

        .logo {
            display: inline-block;
            width: 60px;
            height: 60px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin-bottom: 15px;
            line-height: 60px;
            font-size: 24px;
            font-weight: bold;
        }

        .card-header-custom h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .card-header-custom p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin: 5px 0 0 0;
        }

        .card-body-custom {
            padding: 40px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .btn-submit {
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            padding: 14px 28px;
            width: 100%;
            transition: background-color 0.2s ease;
        }
        .btn-submit:hover {
            background-color: var(--secondary-color);
        }

        #messageContainer {
            margin-top: 20px;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class="card reset-card">
        <div class="card-header-custom">
            <div class="logo">TE</div>
            <h1>TechEdge</h1>
            <p>Atur Ulang Password Anda</p>
        </div>
        <div class="card-body card-body-custom">
            <form id="resetForm" method="POST" action="/api/auth/set_password">
                <input type="hidden" name="Email" value="$Email">
                <input type="hidden" name="Timestamp" value="$Timestamp">
                <input type="hidden" name="Token" value="$Token">

                <div class="mb-4">
                    <label for="newPassword" class="form-label">Password Baru</label>
                    <input class="form-control" type="password" id="newPassword" name="NewPassword" placeholder="Minimal 8 karakter" required minlength="8">
                </div>

                <button class="btn btn-primary btn-submit" type="submit">🔐 Ubah Password</button>
            </form>
            
            <div id="messageContainer"></div>
        </div>
    </div>

    <script>
        // Ambil API key dari URL parameter (jika ada)
        const apiKey = new URLSearchParams(window.location.search).get('key');

        const resetForm = document.getElementById('resetForm');
        const messageContainer = document.getElementById('messageContainer');

        resetForm.addEventListener('submit', async function(e) {
            e.preventDefault(); // Mencegah form submit secara default
            
            const formData = Object.fromEntries(new FormData(this).entries());
            const submitButton = this.querySelector('button[type="submit"]');

            // Reset pesan sebelumnya
            messageContainer.innerHTML = '';
            submitButton.disabled = true;
            submitButton.textContent = 'Memproses...';

            try {
                const response = await fetch('/api/auth/set_password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey || '' // Kirim API Key jika ada
                    },
                    body: JSON.stringify(formData)
                });

                const text = await response.text();
                let data;

                try {
                    data = JSON.parse(text); // Coba parse respons sebagai JSON
                } catch (err) {
                    // Jika gagal, server mungkin mengembalikan HTML error (misal: 404 Not Found)
                    throw new Error("Gagal memproses permintaan. Server tidak merespons dengan format yang benar.");
                }

                if (response.ok) {
                    // Sukses
                    messageContainer.className = 'alert alert-success';
                    messageContainer.textContent = data.message || 'Password berhasil diubah!';
                    resetForm.reset(); // Kosongkan form setelah sukses
                } else {
                    // Error dari API
                    messageContainer.className = 'alert alert-danger';
                    messageContainer.textContent = data.message || 'Terjadi kesalahan yang tidak diketahui.';
                }

            } catch (err) {
                // Error jaringan atau error parsing
                console.error(err);
                messageContainer.className = 'alert alert-danger';
                messageContainer.textContent = "Terjadi kesalahan: " + err.message;
            } finally {
                // Kembalikan tombol ke keadaan semula
                submitButton.disabled = false;
                submitButton.textContent = '🔐 Ubah Password';
            }
        });
    </script>
</body>
</html>