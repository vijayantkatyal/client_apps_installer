<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VidPowr Installer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .progress-bar {
            display: flex;
            background: #f8f9fa;
            padding: 0;
            border-bottom: 1px solid #e9ecef;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            padding: 20px 10px;
            position: relative;
            font-size: 0.9em;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .progress-step.active {
            color: #667eea;
            background: #f0f4ff;
        }

        .progress-step.completed {
            color: #28a745;
            background: #f0fff4;
        }

        .progress-step::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }

        .progress-step:first-child::before {
            display: none;
        }

        .progress-step.active::before,
        .progress-step.completed::before {
            background: #667eea;
        }

        .progress-step.completed::before {
            background: #28a745;
        }

        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .progress-step.active .step-number {
            background: #667eea;
            color: white;
        }

        .progress-step.completed .step-number {
            background: #28a745;
            color: white;
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }

        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .requirements-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .status-ok {
            color: #28a745;
            font-weight: 600;
        }

        .status-error {
            color: #dc3545;
            font-weight: 600;
        }

        .status-warning {
            color: #ffc107;
            font-weight: 600;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .text-center {
            text-align: center;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .card h3 {
            margin-bottom: 15px;
            color: #333;
        }

        @media (max-width: 768px) {
            .progress-step {
                font-size: 0.8em;
                padding: 15px 5px;
            }
            
            .step-number {
                width: 25px;
                height: 25px;
                line-height: 25px;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>VidPowr</h1>
            <p>Video Player Application Installer</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-step <?php echo $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : ''; ?>">
                <div class="step-number">1</div>
                System Check
            </div>
            <div class="progress-step <?php echo $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : ''; ?>">
                <div class="step-number">2</div>
                License
            </div>
            <div class="progress-step <?php echo $currentStep >= 3 ? ($currentStep > 3 ? 'completed' : 'active') : ''; ?>">
                <div class="step-number">3</div>
                Database
            </div>
            <div class="progress-step <?php echo $currentStep >= 4 ? ($currentStep > 4 ? 'completed' : 'active') : ''; ?>">
                <div class="step-number">4</div>
                Install
            </div>
        </div>
        
        <div class="content">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
