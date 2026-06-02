<?php
session_start();

// Jika sudah login, redirect ke index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Koneksi database
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "monitoringskjatim";
    
    $conn = mysqli_connect($host, $username, $password, $database);
    
    if (!$conn) {
        die("Koneksi database gagal: " . mysqli_connect_error());
    }
    
    $input_username = mysqli_real_escape_string($conn, $_POST['username']);
    $input_password = $_POST['password'];
    
    // Query untuk mencari user
    $query = "SELECT * FROM users WHERE username = '$input_username'";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        // Verifikasi password
        if (password_verify($input_password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            // Redirect ke index
            header('Location: index.php');
            exit();
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
    
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BMKG Monitoring System</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #200678 0%, #450234 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Floating shapes */
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: -100px;
            left: -100px;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            bottom: -50px;
            right: -50px;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
        }

        .shape-3 {
            width: 150px;
            height: 150px;
            top: 50%;
            right: 10%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02));
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 10;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.4);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .logo-icon i {
            font-size: 3rem;
            color: white;
        }

        .system-title {
            color: #2d3748;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .system-subtitle {
            color: #718096;
            font-size: 0.95rem;
            font-weight: 300;
        }

        .bmkg-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 10px;
        }

        .error-message {
            background: linear-gradient(135deg, #fff5f5, #fed7d7);
            color: #c53030;
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            text-align: center;
            border-left: 5px solid #f56565;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-message i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #4a5568;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            color: #a0aec0;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .input-wrapper input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }

        .input-wrapper input:hover {
            border-color: #667eea;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-wrapper input:focus + i {
            color: #667eea;
        }

        .input-wrapper .toggle-password {
            position: absolute;
            right: 15px;
            left: auto;
            cursor: pointer;
            color: #a0aec0;
        }

        .input-wrapper .toggle-password:hover {
            color: #667eea;
        }

        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }

        .forgot-password a {
            color: #718096;
            font-size: 0.85rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .forgot-password a:hover {
            color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .login-btn i {
            font-size: 1.2rem;
            transition: transform 0.3s;
        }

        .login-btn:hover i {
            transform: translateX(5px);
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #edf2f7;
        }

        .footer p {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .footer .copyright {
            color: #a0aec0;
            font-size: 0.8rem;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .security-badge span {
            color: #48bb78;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .security-badge i {
            color: #48bb78;
        }

        /* Loading animation */
        .login-btn.loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .login-btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .system-title {
                font-size: 1.8rem;
            }

            .logo-icon {
                width: 80px;
                height: 80px;
            }

            .logo-icon i {
                font-size: 2.5rem;
            }

            .shape-1, .shape-2, .shape-3 {
                display: none;
            }
        }

        /* Input autofill styles */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px white inset;
            -webkit-text-fill-color: #2d3748;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body>
    <!-- Floating shapes -->
    <div class="shape shape-1" data-aos="fade-right"></div>
    <div class="shape shape-2" data-aos="fade-left"></div>
    <div class="shape shape-3" data-aos="fade-up"></div>

    <div class="login-wrapper" data-aos="zoom-in" data-aos-duration="1000">
        <div class="login-container">
            <div class="logo-section">
                <div class="logo-icon" data-aos="flip-left" data-aos-delay="200">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="system-title" data-aos="fade-up" data-aos-delay="300">
                    MONITORING SYSTEM
                </h1>
                <p class="system-subtitle" data-aos="fade-up" data-aos-delay="400">
                    Stasiun Klimatologi Kelas II Jawa Timur
                </p>
                <div class="bmkg-badge" data-aos="fade-up" data-aos-delay="500">
                    <i class="fas fa-cloud-sun"></i> Ruang Server
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message" data-aos="fade-in">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group" data-aos="fade-up" data-aos-delay="600">
                    <label for="username">
                        <i class="fas fa-user" style="margin-right: 8px; color: #667eea;"></i>
                        Username
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               placeholder="Masukkan username Anda"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="700">
                    <label for="password">
                        <i class="fas fa-lock" style="margin-right: 8px; color: #667eea;"></i>
                        Password
                    </label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Masukkan password Anda">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                
                <button type="submit" class="login-btn" id="loginBtn" data-aos="fade-up" data-aos-delay="900">
                    <span>Masuk ke Sistem</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            
            <div class="footer" data-aos="fade-up" data-aos-delay="1000">
                <p>
                    <i class="fas fa-copyright"></i> 2026 BMKG Jawa Timur
                </p>
                <p class="copyright">
                    <i class="fas fa-lock" style="font-size: 0.7rem;"></i>
                    Hanya untuk pengguna terotorisasi
                </p>
                <div class="security-badge">
                    <span>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });

        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Form submission dengan loading animation
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function(e) {
            loginBtn.classList.add('loading');
            loginBtn.innerHTML = '<span>Memproses...</span><i class="fas fa-spinner"></i>';
            
            // Form akan tetap disubmit, loading akan muncul
            // Redirect akan terjadi setelah submit
        });

        // Input validation dan efek
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('i:first-child').style.color = '#3557ec';
            });

            input.addEventListener('blur', function() {
                this.parentElement.querySelector('i:first-child').style.color = '#4d0453';
            });
        });

        // Prevent form resubmit on refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Auto-hide error message after 5 seconds
        const errorMsg = document.querySelector('.error-message');
        if (errorMsg) {
            setTimeout(() => {
                errorMsg.style.transition = 'opacity 0.5s ease';
                errorMsg.style.opacity = '0';
                setTimeout(() => {
                    errorMsg.style.display = 'none';
                }, 500);
            }, 5000);
        }
    </script>
</body>
</html>