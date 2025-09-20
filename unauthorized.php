<?php
session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-md">
        <div class="mx-auto w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Access Denied</h1>
        <p class="text-gray-600 mb-6">You don't have permission to access this page.</p>
        <a href="dashboard.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
            <i class="fas fa-arrow-left mr-2"></i>Go to Dashboard
        </a>
    </div>
</body>
</html>
