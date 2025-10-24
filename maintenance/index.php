<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FaroDash - Coming Soon</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background Elements */
        .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }

        .floating-element {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-element:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-element:nth-child(2) {
            top: 20%;
            right: 15%;
            animation-delay: 2s;
        }

        .floating-element:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        .floating-element:nth-child(4) {
            bottom: 10%;
            right: 10%;
            animation-delay: 1s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        /* Main Container */
        .container {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            text-align: center;
        }

        /* Logo */
        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 40px;
            animation: logoGlow 3s ease-in-out infinite alternate;
        }

        @keyframes logoGlow {
            0% {
                filter: drop-shadow(0 0 10px rgba(237, 27, 38, 0.3));
                transform: scale(1);
            }
            100% {
                filter: drop-shadow(0 0 20px rgba(237, 27, 38, 0.6));
                transform: scale(1.05);
            }
        }

        /* Main Title */
        .title {
            font-size: clamp(2.5rem, 8vw, 4rem);
            font-weight: 800;
            color: #000000;
            margin-bottom: 20px;
            letter-spacing: -0.02em;
            animation: slideInUp 1s ease-out;
        }

        .subtitle {
            font-size: clamp(1.1rem, 4vw, 1.5rem);
            font-weight: 500;
            color: #666666;
            margin-bottom: 50px;
            animation: slideInUp 1s ease-out 0.3s both;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animation Scene */
        .animation-scene {
            position: relative;
            width: 100%;
            max-width: 600px;
            height: 300px;
            margin: 40px 0;
            animation: sceneAppear 1s ease-out 0.6s both;
        }

        @keyframes sceneAppear {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Phone Animation */
        .phone {
            position: absolute;
            left: 50px;
            top: 50%;
            transform: translateY(-50%);
            width: 80px;
            height: 140px;
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 15px;
            border: 3px solid #ED1B26;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: phoneFloat 4s ease-in-out infinite;
        }

        .phone::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            background-image: url('/phone-screen.png');
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            animation: screenGlow 2s ease-in-out infinite alternate;
        }

        @keyframes phoneFloat {
            0%, 100% {
                transform: translateY(-50%) rotate(-2deg);
            }
            50% {
                transform: translateY(-60%) rotate(2deg);
            }
        }

        @keyframes screenGlow {
            0% {
                box-shadow: inset 0 0 10px rgba(237, 27, 38, 0.3);
            }
            100% {
                box-shadow: inset 0 0 20px rgba(237, 27, 38, 0.6);
            }
        }

        /* Food Items Animation */
        .food-item {
            position: absolute;
            width: 90px;
            height: 90px;
            background-size: cover;
            background-position: center;
            border-radius: 50%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .food1 {
            background-image: url('/food1.png');
            top: 20px;
            left: 200px;
            animation: foodFloat1 5s ease-in-out infinite;
        }

        .food2 {
            background-image: url('/food2.png');
            top: 80px;
            left: 300px;
            animation: foodFloat2 6s ease-in-out infinite;
        }

        .food3 {
            background-image: url('/food3.png');
            bottom: 80px;
            left: 180px;
            animation: foodFloat3 4.5s ease-in-out infinite;
        }

        @keyframes foodFloat1 {
            0%, 100% {
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            33% {
                transform: translateY(-15px) rotate(120deg) scale(1.1);
            }
            66% {
                transform: translateY(-10px) rotate(240deg) scale(0.9);
            }
        }

        @keyframes foodFloat2 {
            0%, 100% {
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            50% {
                transform: translateY(-20px) rotate(180deg) scale(1.2);
            }
        }

        @keyframes foodFloat3 {
            0%, 100% {
                transform: translateY(0px) rotate(0deg) scale(1);
            }
            25% {
                transform: translateY(-10px) rotate(90deg) scale(1.05);
            }
            75% {
                transform: translateY(-15px) rotate(270deg) scale(0.95);
            }
        }

        /* Delivery Vehicle */
        .delivery-vehicle {
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            width: 100px;
            height: 60px;
            background-image: url('/delivery-bike.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            animation: deliveryMove 8s ease-in-out infinite;
        }

        @keyframes deliveryMove {
            0%, 100% {
                transform: translateY(-50%) translateX(0px);
            }
            25% {
                transform: translateY(-55%) translateX(-10px);
            }
            50% {
                transform: translateY(-45%) translateX(-5px);
            }
            75% {
                transform: translateY(-55%) translateX(-15px);
            }
        }

        /* Connecting Lines Animation */
        .connection-line {
            position: absolute;
            height: 2px;
            background: linear-gradient(90deg, #ED1B26, transparent);
            animation: lineGrow 3s ease-in-out infinite;
        }

        .line1 {
            top: 50%;
            left: 140px;
            width: 100px;
            animation-delay: 0s;
        }

        .line2 {
            top: 60%;
            left: 260px;
            width: 120px;
            animation-delay: 1s;
        }

        @keyframes lineGrow {
            0%, 100% {
                width: 0;
                opacity: 0;
            }
            50% {
                width: 100px;
                opacity: 1;
            }
        }

        /* Progress Indicator */
        .progress-container {
            margin-top: 60px;
            animation: slideInUp 1s ease-out 0.9s both;
        }

        .progress-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ED1B26;
            margin-bottom: 20px;
        }

        .progress-bar {
            width: 300px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            margin: 0 auto;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ED1B26, #ff4757);
            border-radius: 3px;
            animation: progressFill 4s ease-in-out infinite;
        }

        @keyframes progressFill {
            0% {
                width: 20%;
                transform: translateX(-100px);
            }
            50% {
                width: 80%;
                transform: translateX(0);
            }
            100% {
                width: 95%;
                transform: translateX(10px);
            }
        }

        /* Call to Action */
        .cta {
            margin-top: 50px;
            animation: slideInUp 1s ease-out 1.2s both;
        }

        .cta-text {
            font-size: 1rem;
            color: #666;
            margin-bottom: 20px;
        }

        .email-input {
            display: inline-flex;
            max-width: 400px;
            width: 100%;
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .email-input:focus-within {
            border-color: #ED1B26;
        }

        .email-input input {
            flex: 1;
            border: none;
            padding: 16px 24px;
            font-size: 16px;
            outline: none;
            font-family: 'Outfit', sans-serif;
        }

        .email-input button {
            background: #ED1B26;
            color: white;
            border: none;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            font-family: 'Outfit', sans-serif;
        }

        .email-input button:hover {
            background: #d41420;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .animation-scene {
                height: 250px;
                transform: scale(0.8);
            }

            .phone {
                left: 30px;
                width: 60px;
                height: 110px;
            }

            .food-item {
                width: 70px;
                height: 70px;
            }

            .delivery-vehicle {
                right: 30px;
                width: 80px;
                height: 45px;
            }

            .progress-bar {
                width: 250px;
            }

            .email-input {
                flex-direction: column;
                border-radius: 16px;
            }

            .email-input input,
            .email-input button {
                border-radius: 0;
            }

            .email-input button {
                border-radius: 0 0 14px 14px;
            }

            .email-input input {
                border-radius: 14px 14px 0 0;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            .logo {
                width: 100px;
                margin-bottom: 30px;
            }

            .animation-scene {
                transform: scale(0.7);
                height: 200px;
            }
        }

        /* Loading Animation for Logo */
        .logo-container {
            position: relative;
        }

        .loading-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            height: 140px;
            border: 3px solid transparent;
            border-top: 3px solid #ED1B26;
            border-radius: 50%;
            animation: spin 2s linear infinite;
            opacity: 0.3;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="floating-element" style="width: 30px; height: 30px; background: radial-gradient(circle, #ED1B26, transparent); border-radius: 50%;"></div>
        <div class="floating-element" style="width: 20px; height: 20px; background: radial-gradient(circle, #ED1B26, transparent); border-radius: 50%;"></div>
        <div class="floating-element" style="width: 25px; height: 25px; background: radial-gradient(circle, #ED1B26, transparent); border-radius: 50%;"></div>
        <div class="floating-element" style="width: 35px; height: 35px; background: radial-gradient(circle, #ED1B26, transparent); border-radius: 50%;"></div>
    </div>

    <div class="container">
        <div class="logo-container">
            <div class="loading-ring"></div>
            <img src="/logo.png" alt="FaroDash Logo" class="logo">
        </div>

        <h1 class="title">Coming Soon</h1>
        <p class="subtitle">Your favorite meals, delivered fresh and fast</p>

        <div class="animation-scene">
            <div class="phone"></div>
            <div class="food-item food1"></div>
            <div class="food-item food2"></div>
            <div class="food-item food3"></div>
            <div class="connection-line line1"></div>
            <div class="connection-line line2"></div>
            <div class="delivery-vehicle"></div>
        </div>

        <div class="progress-container">
            <div class="progress-text">Getting ready to serve you...</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <div class="cta">
            <p class="cta-text">Be the first to know when we launch!</p>
            <div class="email-input">
                <input type="email" placeholder="Enter your email address" required>
                <button type="submit">Notify Me</button>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            const emailForm = document.querySelector('.email-input');
            const emailInput = emailForm.querySelector('input');
            const submitBtn = emailForm.querySelector('button');

            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (emailInput.value && emailInput.value.includes('@')) {
                    submitBtn.textContent = 'Thank You!';
                    submitBtn.style.background = '#28a745';
                    setTimeout(() => {
                        submitBtn.textContent = 'Notify Me';
                        submitBtn.style.background = '#ED1B26';
                        emailInput.value = '';
                    }, 2000);
                } else {
                    emailInput.style.borderColor = '#ED1B26';
                    emailInput.focus();
                }
            });

            // Add parallax effect to floating elements
            document.addEventListener('mousemove', function(e) {
                const elements = document.querySelectorAll('.floating-element');
                const x = e.clientX / window.innerWidth;
                const y = e.clientY / window.innerHeight;

                elements.forEach((element, index) => {
                    const speed = (index + 1) * 0.5;
                    const xPos = (x - 0.5) * speed * 20;
                    const yPos = (y - 0.5) * speed * 20;
                    element.style.transform = `translate(${xPos}px, ${yPos}px)`;
                });
            });
        });
    </script>
</body>
</html>